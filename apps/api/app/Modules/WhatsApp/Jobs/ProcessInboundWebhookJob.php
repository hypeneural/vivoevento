<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppWebhookNormalizerInterface;
use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Enums\InboundEventStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\WhatsAppInstanceStatusChanged;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppInboundRouter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes an inbound webhook from a WhatsApp provider.
 *
 * Flow:
 * 1. Save raw payload to whatsapp_inbound_events (pending)
 * 2. Identify instance by instanceKey
 * 3. Normalize payload via provider-specific normalizer
 * 4. Route normalized message via WhatsAppInboundRouter
 * 5. Mark event as processed
 */
class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $providerKey,
        public readonly string $instanceKey,
        public readonly array $payload,
    ) {
        $this->onQueue(config('whatsapp.queues.inbound', 'whatsapp-inbound'));
    }

    public function handle(WhatsAppInboundRouter $inboundRouter): void
    {
        // 1. Find instance
        $instance = WhatsAppInstance::where('provider_key', $this->providerKey)
            ->where('external_instance_id', $this->instanceKey)
            ->first();
        $traceId = $this->resolveTraceId();

        if (! $instance) {
            Log::channel('whatsapp')->warning('Inbound webhook for unknown instance', [
                'provider' => $this->providerKey,
                'instance_key' => $this->instanceKey,
                'trace_id' => $traceId,
            ]);
            return;
        }

        // 2. Save raw inbound event
        $inboundEvent = WhatsAppInboundEvent::create([
            'instance_id' => $instance->id,
            'provider_key' => $this->providerKey,
            'trace_id' => $traceId,
            'provider_message_id' => $this->payload['messageId'] ?? $this->payload['ids'][0] ?? null,
            'event_type' => $this->detectEventType(),
            'payload_json' => $this->payload,
            'processing_status' => InboundEventStatus::Pending,
            'received_at' => now(),
        ]);

        try {
            match ($this->detectCallbackType()) {
                'received' => $this->processReceivedCallback($instance, $inboundEvent, $inboundRouter),
                'message_status' => $this->processMessageStatusCallback($instance, $inboundEvent),
                'connected', 'disconnected' => $this->processInstanceLifecycleCallback($instance, $inboundEvent),
                default => $this->ignoreCallback($inboundEvent),
            };

        } catch (\Throwable $e) {
            $inboundEvent->markFailed($e->getMessage());

            Log::channel('whatsapp')->error('Failed to process inbound webhook', [
                'inbound_event_id' => $inboundEvent->id,
                'instance_id' => $instance->id,
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function detectEventType(): string
    {
        return match ($this->detectCallbackType()) {
            'received' => 'message',
            'message_status' => 'delivery',
            'connected', 'disconnected' => 'status',
            'presence' => 'presence',
            'sent' => 'dispatch',
            default => 'unknown',
        };
    }

    private function resolveNormalizer(string $providerKey): WhatsAppWebhookNormalizerInterface
    {
        return match ($providerKey) {
            'zapi' => app(\App\Modules\WhatsApp\Clients\Providers\ZApi\ZApiWebhookNormalizer::class),
            default => throw new \RuntimeException("No webhook normalizer for provider: {$providerKey}"),
        };
    }

    private function detectCallbackType(): string
    {
        return match ($this->payload['type'] ?? null) {
            'ReceivedCallback' => 'received',
            'MessageStatusCallback' => 'message_status',
            'ConnectedCallback' => 'connected',
            'DisconnectedCallback' => 'disconnected',
            'PresenceChatCallback' => 'presence',
            'DeliveryCallback' => 'sent',
            default => $this->inferCallbackTypeFromPayload(),
        };
    }

    private function inferCallbackTypeFromPayload(): string
    {
        if (array_key_exists('connected', $this->payload)) {
            return 'connected';
        }

        if (array_key_exists('disconnected', $this->payload)) {
            return 'disconnected';
        }

        if (isset($this->payload['ids']) && isset($this->payload['status'])) {
            return 'message_status';
        }

        if (($this->payload['_webhook_type'] ?? null) === 'delivery') {
            return 'message_status';
        }

        if (($this->payload['_webhook_type'] ?? null) === 'status') {
            return 'unknown';
        }

        return 'received';
    }

    private function processReceivedCallback(
        WhatsAppInstance $instance,
        WhatsAppInboundEvent $inboundEvent,
        WhatsAppInboundRouter $inboundRouter,
    ): void {
        $normalizer = $this->resolveNormalizer($this->providerKey);
        $normalized = $normalizer->normalize($this->payload, $instance);

        $inboundEvent->update(['normalized_json' => $normalized->toArray()]);

        if ($this->shouldIgnoreReceivedCallback($normalized)) {
            $inboundEvent->markIgnored();
            return;
        }

        $inboundRouter->route($normalized, $instance);
        $inboundEvent->markProcessed();
    }

    private function processMessageStatusCallback(
        WhatsAppInstance $instance,
        WhatsAppInboundEvent $inboundEvent,
    ): void {
        $status = (string) ($this->payload['status'] ?? '');
        $occurredAt = $this->extractOccurredAt();
        $messageIds = collect($this->payload['ids'] ?? [$this->payload['messageId'] ?? null])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values()
            ->all();

        $inboundEvent->update([
            'normalized_json' => [
                'callback_type' => $this->payload['type'] ?? 'MessageStatusCallback',
                'message_status' => $status,
                'message_ids' => $messageIds,
                'phone' => $this->payload['phone'] ?? null,
                'is_group' => (bool) ($this->payload['isGroup'] ?? false),
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        ]);

        $mappedStatus = $this->mapProviderMessageStatus($status);

        if ($mappedStatus === null || $messageIds === []) {
            $inboundEvent->markIgnored();
            return;
        }

        $messages = WhatsAppMessage::query()
            ->where('instance_id', $instance->id)
            ->where('direction', MessageDirection::Outbound)
            ->whereIn('provider_message_id', $messageIds)
            ->get();

        if ($messages->isEmpty()) {
            Log::channel('whatsapp')->info('Outbound delivery callback ignored because no message matched', [
                'instance_id' => $instance->id,
                'trace_id' => $this->resolveTraceId(),
                'message_ids' => $messageIds,
                'provider_status' => $status,
            ]);

            $inboundEvent->markIgnored();
            return;
        }

        foreach ($messages as $message) {
            $updates = [
                'status' => $mappedStatus,
            ];

            if ($mappedStatus === MessageStatus::Sent && $message->sent_at === null) {
                $updates['sent_at'] = $occurredAt;
            }

            $existingNormalized = is_array($message->normalized_payload_json) ? $message->normalized_payload_json : [];
            $updates['normalized_payload_json'] = array_merge($existingNormalized, [
                'last_delivery_callback' => [
                    'provider_status' => $status,
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'message_ids' => $messageIds,
                ],
            ]);

            $message->update($updates);
        }

        $inboundEvent->markProcessed();
    }

    private function processInstanceLifecycleCallback(
        WhatsAppInstance $instance,
        WhatsAppInboundEvent $inboundEvent,
    ): void {
        $callbackType = $this->detectCallbackType();
        $occurredAt = $this->extractOccurredAt();
        $previousStatus = $instance->status ?? InstanceStatus::Draft;
        $newStatus = $callbackType === 'connected'
            ? InstanceStatus::Connected
            : InstanceStatus::Disconnected;

        $updates = [
            'status' => $newStatus,
            'phone_number' => $this->payload['phone'] ?? $this->payload['connectedPhone'] ?? $instance->phone_number,
            'last_status_sync_at' => $occurredAt,
            'last_health_check_at' => $occurredAt,
            'last_health_status' => $newStatus->value,
        ];

        if ($newStatus === InstanceStatus::Connected) {
            $updates['connected_at'] = $occurredAt;
            $updates['disconnected_at'] = null;
            $updates['last_error'] = null;
        } else {
            $updates['disconnected_at'] = $occurredAt;
            $updates['last_error'] = $this->payload['error'] ?? null;
        }

        $instance->update($updates);

        $inboundEvent->update([
            'normalized_json' => [
                'callback_type' => $this->payload['type'] ?? ($callbackType === 'connected' ? 'ConnectedCallback' : 'DisconnectedCallback'),
                'instance_status' => $newStatus->value,
                'phone' => $updates['phone_number'],
                'error' => $updates['last_error'] ?? null,
                'occurred_at' => $occurredAt->toIso8601String(),
            ],
        ]);

        if ($previousStatus !== $newStatus) {
            WhatsAppInstanceStatusChanged::dispatch($instance, $previousStatus, $newStatus);
        }

        $inboundEvent->markProcessed();
    }

    private function resolveTraceId(): ?string
    {
        $traceId = trim((string) ($this->payload['_trace_id'] ?? ''));

        return $traceId !== '' ? $traceId : null;
    }

    private function ignoreCallback(WhatsAppInboundEvent $inboundEvent): void
    {
        $inboundEvent->update([
            'normalized_json' => [
                'callback_type' => $this->payload['type'] ?? null,
                'webhook_type' => $this->payload['_webhook_type'] ?? null,
            ],
        ]);

        $inboundEvent->markIgnored();
    }

    private function mapProviderMessageStatus(string $providerStatus): ?MessageStatus
    {
        return match (strtoupper($providerStatus)) {
            'SENT' => MessageStatus::Sent,
            'RECEIVED' => MessageStatus::Delivered,
            'READ', 'READ_BY_ME', 'PLAYED' => MessageStatus::Read,
            default => null,
        };
    }

    private function extractOccurredAt(): CarbonImmutable
    {
        foreach (['momment', 'mommentTimestamp', 'moment', 'momentTimestamp', 'timestamp'] as $key) {
            if (! isset($this->payload[$key])) {
                continue;
            }

            $value = $this->payload[$key];

            if (is_numeric($value)) {
                return $this->timestampFromNumericValue($value);
            }

            if (is_string($value) && trim($value) !== '') {
                return CarbonImmutable::parse($value)->utc();
            }
        }

        return CarbonImmutable::now()->utc();
    }

    private function timestampFromNumericValue(int|float|string $value): CarbonImmutable
    {
        $seconds = (float) $value;

        if (abs($seconds) >= 1_000_000_000_000_000) {
            $seconds /= 1_000_000;
        } elseif (abs($seconds) > 9_999_999_999) {
            $seconds /= 1_000;
        }

        return CarbonImmutable::createFromTimestamp((int) floor($seconds))->utc();
    }

    private function shouldIgnoreReceivedCallback(\App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData $normalized): bool
    {
        if (isset($this->payload['notification'])) {
            return true;
        }

        if ($normalized->messageType === 'reaction') {
            return true;
        }

        return false;
    }
}

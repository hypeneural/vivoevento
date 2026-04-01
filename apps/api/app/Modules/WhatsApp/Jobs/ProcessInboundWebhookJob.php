<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppWebhookNormalizerInterface;
use App\Modules\WhatsApp\Enums\InboundEventStatus;
use App\Modules\WhatsApp\Models\WhatsAppInboundEvent;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppInboundRouter;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
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

    public function handle(
        WhatsAppProviderResolver $providerResolver,
        WhatsAppInboundRouter $inboundRouter,
    ): void {
        // 1. Find instance
        $instance = WhatsAppInstance::where('provider_key', $this->providerKey)
            ->where('external_instance_id', $this->instanceKey)
            ->first();

        if (! $instance) {
            Log::channel('whatsapp')->warning('Inbound webhook for unknown instance', [
                'provider' => $this->providerKey,
                'instance_key' => $this->instanceKey,
            ]);
            return;
        }

        // 2. Save raw inbound event
        $inboundEvent = WhatsAppInboundEvent::create([
            'instance_id' => $instance->id,
            'provider_key' => $this->providerKey,
            'provider_message_id' => $this->payload['messageId'] ?? $this->payload['ids'][0] ?? null,
            'event_type' => $this->detectEventType(),
            'payload_json' => $this->payload,
            'processing_status' => InboundEventStatus::Pending,
            'received_at' => now(),
        ]);

        try {
            // 3. Get the normalizer for this provider
            $normalizer = $this->resolveNormalizer($this->providerKey);
            $normalized = $normalizer->normalize($this->payload, $instance);

            // 4. Save normalized data
            $inboundEvent->update(['normalized_json' => $normalized->rawPayload]);

            // 5. Route the message
            $inboundRouter->route($normalized, $instance);

            // 6. Mark as processed
            $inboundEvent->markProcessed();

        } catch (\Throwable $e) {
            $inboundEvent->markFailed($e->getMessage());

            Log::channel('whatsapp')->error('Failed to process inbound webhook', [
                'inbound_event_id' => $inboundEvent->id,
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function detectEventType(): string
    {
        if (isset($this->payload['status'])) {
            return 'status';
        }
        if (isset($this->payload['ack'])) {
            return 'delivery';
        }
        return 'message';
    }

    private function resolveNormalizer(string $providerKey): WhatsAppWebhookNormalizerInterface
    {
        return match ($providerKey) {
            'zapi' => app(\App\Modules\WhatsApp\Clients\Providers\ZApi\ZApiWebhookNormalizer::class),
            default => throw new \RuntimeException("No webhook normalizer for provider: {$providerKey}"),
        };
    }
}

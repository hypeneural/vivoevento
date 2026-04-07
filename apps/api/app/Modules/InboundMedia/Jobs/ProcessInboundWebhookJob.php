<?php

namespace App\Modules\InboundMedia\Jobs;

use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $messageId = data_get($this->payload, '_event_context.provider_message_id')
            ?? $this->payload['messageId']
            ?? null;

        $providerUpdateId = $this->providerUpdateId();
        $values = [
            'event_channel_id' => data_get($this->payload, '_event_context.event_channel_id'),
            'message_id' => $messageId,
            'detected_type' => $this->detectType($this->payload),
            'routing_status' => 'received',
            'payload_json' => $this->payload,
        ];

        if ($providerUpdateId !== null) {
            try {
                $webhookLog = ChannelWebhookLog::query()->firstOrCreate(
                    [
                        'provider' => $this->provider,
                        'provider_update_id' => $providerUpdateId,
                    ],
                    $values,
                );
            } catch (QueryException $exception) {
                if (! $this->isDuplicateWebhookException($exception)) {
                    throw $exception;
                }

                $webhookLog = ChannelWebhookLog::query()
                    ->where('provider', $this->provider)
                    ->where('provider_update_id', $providerUpdateId)
                    ->firstOrFail();
            }

            if (! $webhookLog->wasRecentlyCreated) {
                return;
            }
        } else {
            $webhookLog = ChannelWebhookLog::query()->create(array_merge([
                'provider' => $this->provider,
                'provider_update_id' => null,
            ], $values));
        }

        NormalizeInboundMessageJob::dispatch($webhookLog->id);
    }

    private function providerUpdateId(): ?string
    {
        $value = data_get($this->payload, '_event_context.provider_update_id')
            ?? data_get($this->payload, 'provider_update_id')
            ?? ($this->provider === 'telegram' ? data_get($this->payload, 'update_id') : null);

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function detectType(array $payload): string
    {
        foreach (['image', 'video', 'audio', 'document', 'sticker', 'reaction'] as $type) {
            if (isset($payload[$type])) {
                return $type;
            }
        }

        if (isset($payload['notification'])) {
            return 'notification';
        }

        if (isset($payload['text'])) {
            return 'text';
        }

        return 'unknown';
    }

    private function isDuplicateWebhookException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}

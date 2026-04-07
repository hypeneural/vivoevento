<?php

namespace App\Modules\InboundMedia\Jobs;

use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Jobs\DownloadInboundMediaJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $webhookLogId,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $webhookLog = ChannelWebhookLog::query()->find($this->webhookLogId);

        if (! $webhookLog) {
            return;
        }

        $payload = is_array($webhookLog->payload_json) ? $webhookLog->payload_json : [];
        $context = data_get($payload, '_event_context');

        if (! is_array($context) || ! filled(data_get($context, 'event_id')) || ! filled(data_get($context, 'event_channel_id'))) {
            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'missing_event_context',
            ]);

            return;
        }

        $messageId = (string) (data_get($context, 'provider_message_id') ?? data_get($payload, 'provider_message_id') ?? $webhookLog->message_id ?? '');
        $chatExternalId = data_get($context, 'chat_external_id') ?? data_get($payload, 'chat_external_id') ?? data_get($payload, 'phone');

        if ($messageId === '') {
            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'missing_message_id',
            ]);

            return;
        }

        $identity = [
            'provider' => $webhookLog->provider,
            'message_id' => $messageId,
        ];

        if (filled($chatExternalId)) {
            $identity['chat_external_id'] = (string) $chatExternalId;
        }

        try {
            $inboundMessage = InboundMessage::query()->firstOrCreate(
                $identity,
                [
                    'event_id' => data_get($context, 'event_id'),
                    'event_channel_id' => data_get($context, 'event_channel_id'),
                    'message_type' => data_get($payload, 'message_type') ?? $this->detectType($payload),
                    'chat_external_id' => $chatExternalId,
                    'sender_external_id' => data_get($context, 'sender_external_id') ?? data_get($context, 'sender_lid') ?? data_get($context, 'sender_phone'),
                    'sender_phone' => data_get($context, 'sender_phone'),
                    'sender_lid' => data_get($context, 'sender_lid'),
                    'sender_name' => data_get($context, 'sender_name'),
                    'sender_avatar_url' => data_get($context, 'sender_avatar_url') ?? data_get($payload, 'senderPhoto'),
                    'body_text' => data_get($context, 'caption') ?? data_get($payload, 'caption') ?? data_get($payload, 'body_text') ?? data_get($payload, 'text.message'),
                    'media_url' => data_get($context, 'media_url'),
                    'reference_message_id' => data_get($payload, 'referenceMessageId') ?? data_get($payload, 'provider_message_id'),
                    'from_me' => data_get($context, 'from_me') ?? data_get($payload, 'fromMe'),
                    'normalized_payload_json' => array_merge($payload, [
                        '_event_context' => $context,
                    ]),
                    'status' => 'normalized',
                    'received_at' => $this->extractOccurredAt($payload),
                ],
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateInboundException($exception)) {
                throw $exception;
            }

            $inboundMessage = InboundMessage::query()
                ->where($identity)
                ->first();

            if (! $inboundMessage) {
                throw $exception;
            }
        }

        $webhookLog->update([
            'routing_status' => 'normalized',
            'inbound_message_id' => $inboundMessage->id,
        ]);

        if (! $this->hasDownloadableMedia($inboundMessage)) {
            $inboundMessage->update([
                'status' => 'ignored',
                'processed_at' => now(),
            ]);

            $webhookLog->update([
                'routing_status' => 'ignored',
                'error_message' => 'no_media_url',
            ]);

            return;
        }

        DownloadInboundMediaJob::dispatch($inboundMessage->id);
    }

    private function detectType(array $payload): string
    {
        foreach (['photo', 'image', 'video', 'audio', 'document', 'sticker'] as $type) {
            if (isset($payload[$type])) {
                return $type;
            }
        }

        return isset($payload['text']) ? 'text' : 'unknown';
    }

    private function hasDownloadableMedia(InboundMessage $inboundMessage): bool
    {
        if (filled($inboundMessage->media_url)) {
            return true;
        }

        return data_get($inboundMessage->normalized_payload_json, 'media.download_strategy') === 'telegram_file'
            && filled(data_get($inboundMessage->normalized_payload_json, 'media.file_id'));
    }

    private function extractOccurredAt(array $payload): Carbon
    {
        foreach (['occurred_at', 'momment', 'mommentTimestamp', 'moment', 'momentTimestamp', 'timestamp'] as $key) {
            if (! isset($payload[$key])) {
                continue;
            }

            $value = $payload[$key];

            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if (is_string($value) && trim($value) !== '') {
                return Carbon::parse($value);
            }
        }

        return Carbon::now();
    }

    private function isDuplicateInboundException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}

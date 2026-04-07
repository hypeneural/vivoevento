<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\RemoteInboundMediaDownloaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DownloadInboundMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $inboundMessageId,
    ) {
        $this->onQueue('media-download');
    }

    public function handle(): void
    {
        $inboundMessage = InboundMessage::query()->with(['event', 'channel', 'event.faceSearchSettings'])->find($this->inboundMessageId);

        if (! $inboundMessage || ! $inboundMessage->event || ! $this->hasDownloadSource($inboundMessage)) {
            return;
        }

        $existingMedia = EventMedia::query()->where('inbound_message_id', $inboundMessage->id)->first();

        if ($existingMedia) {
            $inboundMessage->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            ChannelWebhookLog::query()
                ->where('inbound_message_id', $inboundMessage->id)
                ->update(['routing_status' => 'processed']);

            return;
        }

        $download = app(RemoteInboundMediaDownloaderService::class)->download($inboundMessage);
        $inboundMessage->refresh();

        $body = $download['body'];
        $mimeType = $download['mime_type'] ?? 'application/octet-stream';

        $extension = $this->extensionFor($mimeType, $inboundMessage->message_type);
        $filename = "{$inboundMessage->message_id}.{$extension}";
        $path = "events/{$inboundMessage->event_id}/originals/{$filename}";

        Storage::disk('public')->put($path, $body);

        $eventMedia = EventMedia::query()->create([
            'event_id' => $inboundMessage->event_id,
            'inbound_message_id' => $inboundMessage->id,
            'media_type' => $this->mediaTypeFor($inboundMessage->message_type),
            'source_type' => (string) data_get($inboundMessage->normalized_payload_json, '_event_context.intake_source', 'whatsapp'),
            'source_label' => $inboundMessage->sender_name ?: ucfirst($inboundMessage->provider),
            'caption' => $inboundMessage->body_text,
            'original_filename' => $filename,
            'original_disk' => 'public',
            'original_path' => $path,
            'client_filename' => $download['client_filename'] ?? $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($body),
            'processing_status' => MediaProcessingStatus::Downloaded->value,
            'moderation_status' => ModerationStatus::Pending->value,
            'publication_status' => PublicationStatus::Draft->value,
            'safety_status' => 'queued',
            'face_index_status' => $this->shouldQueueFaceIndex($inboundMessage) ? 'queued' : 'skipped',
            'vlm_status' => 'queued',
            'pipeline_version' => 'media_ai_foundation_v1',
        ]);

        $inboundMessage->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        ChannelWebhookLog::query()
            ->where('inbound_message_id', $inboundMessage->id)
            ->update(['routing_status' => 'processed']);

        app(ModerationBroadcasterService::class)->broadcastCreated(
            $eventMedia->fresh(['event', 'variants', 'inboundMessage']),
        );

        GenerateMediaVariantsJob::dispatch($eventMedia->id);
    }

    private function mediaTypeFor(string $messageType): string
    {
        return match ($messageType) {
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'document',
            default => 'image',
        };
    }

    private function hasDownloadSource(InboundMessage $inboundMessage): bool
    {
        if (filled($inboundMessage->media_url)) {
            return true;
        }

        return data_get($inboundMessage->normalized_payload_json, 'media.download_strategy') === 'telegram_file'
            && filled(data_get($inboundMessage->normalized_payload_json, 'media.file_id'));
    }

    private function extensionFor(string $mimeType, string $messageType): string
    {
        return match (true) {
            str_contains($mimeType, 'jpeg') => 'jpg',
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'webp') => 'webp',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'mpeg') => 'mp3',
            default => match ($messageType) {
                'video' => 'mp4',
                'audio' => 'mp3',
                'document' => 'bin',
                default => 'jpg',
            },
        };
    }

    private function shouldQueueFaceIndex(InboundMessage $inboundMessage): bool
    {
        return $this->mediaTypeFor($inboundMessage->message_type) === 'image'
            && (bool) ($inboundMessage->event?->isFaceSearchEnabled() ?? false);
    }
}

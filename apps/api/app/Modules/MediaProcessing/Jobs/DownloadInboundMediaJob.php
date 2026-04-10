<?php

namespace App\Modules\MediaProcessing\Jobs;

use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaToolingStatusService;
use App\Modules\MediaProcessing\Services\ModerationBroadcasterService;
use App\Modules\MediaProcessing\Services\RemoteInboundMediaDownloaderService;
use App\Modules\MediaProcessing\Services\VideoMetadataExtractorService;
use App\Modules\Wall\Models\EventWallSetting;
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
        $mediaType = $this->mediaTypeFor($inboundMessage->message_type, $mimeType);
        $usesVariantPipeline = $this->usesVariantPipeline($mediaType, $inboundMessage);
        $usesVideoVariants = $this->usesVideoVariants($mediaType, $inboundMessage);

        $extension = $this->extensionFor($mimeType, $inboundMessage->message_type);
        $filename = "{$inboundMessage->message_id}.{$extension}";
        $path = $mediaType === 'audio'
            ? "events/{$inboundMessage->event_id}/audio-recordings/{$filename}"
            : "events/{$inboundMessage->event_id}/originals/{$filename}";

        Storage::disk('public')->put($path, $body);

        $videoMetadata = $mediaType === 'video'
            ? app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
                disk: 'public',
                path: $path,
                mimeType: $mimeType,
                hints: $this->videoMetadataHints($inboundMessage, $download),
            )
            : [];

        if ($mediaType === 'audio') {
            $inboundMessage->update([
                'capture_target' => 'event_audio',
                'stored_disk' => 'public',
                'stored_path' => $path,
                'client_filename' => $download['client_filename'] ?? $filename,
                'mime_type' => $mimeType,
                'size_bytes' => strlen($body),
                'status' => 'processed',
                'processed_at' => now(),
                'captured_at' => now(),
            ]);

            ChannelWebhookLog::query()
                ->where('inbound_message_id', $inboundMessage->id)
                ->update(['routing_status' => 'processed']);

            return;
        }

        $eventMedia = EventMedia::query()->create([
            'event_id' => $inboundMessage->event_id,
            'inbound_message_id' => $inboundMessage->id,
            'media_type' => $mediaType,
            'source_type' => (string) data_get($inboundMessage->normalized_payload_json, '_event_context.intake_source', 'whatsapp'),
            'source_label' => $inboundMessage->sender_name ?: ucfirst($inboundMessage->provider),
            'caption' => $inboundMessage->body_text,
            'original_filename' => $filename,
            'original_disk' => 'public',
            'original_path' => $path,
            'client_filename' => $download['client_filename'] ?? $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($body),
            'processing_status' => $usesVariantPipeline
                ? MediaProcessingStatus::Downloaded->value
                : MediaProcessingStatus::Processed->value,
            'moderation_status' => ModerationStatus::Pending->value,
            'publication_status' => PublicationStatus::Draft->value,
            'safety_status' => $mediaType === 'image' ? 'queued' : 'skipped',
            'face_index_status' => $this->shouldQueueFaceIndex($mediaType, $inboundMessage) ? 'queued' : 'skipped',
            'vlm_status' => $mediaType === 'image' ? 'queued' : 'skipped',
            'pipeline_version' => 'media_ai_foundation_v1',
            ...$videoMetadata,
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

        if ($usesVariantPipeline) {
            GenerateMediaVariantsJob::dispatch($eventMedia->id);

            return;
        }

        RunModerationJob::dispatch($eventMedia->id);
    }

    private function mediaTypeFor(string $messageType, ?string $mimeType = null): string
    {
        $normalizedMimeType = strtolower(trim((string) $mimeType));

        if (str_starts_with($normalizedMimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($normalizedMimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($normalizedMimeType, 'image/')) {
            return 'image';
        }

        return match ($messageType) {
            'video' => 'video',
            'audio' => 'audio',
            'document' => 'document',
            default => 'image',
        };
    }

    private function usesVariantPipeline(string $mediaType, InboundMessage $inboundMessage): bool
    {
        return $mediaType === 'image' || $this->usesVideoVariants($mediaType, $inboundMessage);
    }

    private function usesVideoVariants(string $mediaType, InboundMessage $inboundMessage): bool
    {
        if ($mediaType !== 'video') {
            return false;
        }

        if (! $this->privateInboundVideoEnabled($inboundMessage)) {
            return false;
        }

        return (bool) (app(MediaToolingStatusService::class)->payload()['ready'] ?? false);
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
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'opus') => 'ogg',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'wav') => 'wav',
            default => match ($messageType) {
                'video' => 'mp4',
                'audio' => 'ogg',
                'document' => 'bin',
                default => 'jpg',
            },
        };
    }

    private function shouldQueueFaceIndex(string $mediaType, InboundMessage $inboundMessage): bool
    {
        return $mediaType === 'image'
            && (bool) ($inboundMessage->event?->isFaceSearchEnabled() ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function videoMetadataHints(InboundMessage $inboundMessage, array $download): array
    {
        $payload = is_array($inboundMessage->normalized_payload_json)
            ? $inboundMessage->normalized_payload_json
            : [];

        return array_merge($payload, [
            'mime_type' => $download['mime_type'] ?? $inboundMessage->mime_type,
            'container' => $download['mime_type'] ?? $inboundMessage->mime_type,
        ]);
    }

    private function privateInboundVideoEnabled(InboundMessage $inboundMessage): bool
    {
        $settings = $inboundMessage->event?->relationLoaded('wallSettings')
            ? $inboundMessage->event->getRelation('wallSettings')
            : EventWallSetting::query()->where('event_id', $inboundMessage->event_id)->first();

        return $settings?->resolvedPrivateInboundVideoEnabled()
            ?? (bool) config('media_processing.private_inbound.video_enabled', true);
    }
}

<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Log;

class MediaPipelineTelemetryService
{
    /**
     * @return array<string, int|string|null>
     */
    public function publishPayload(EventMedia $media): array
    {
        $media->loadMissing('inboundMessage');

        return [
            'event_id' => $media->event_id,
            'event_media_id' => $media->id,
            'inbound_message_id' => $media->inbound_message_id,
            'source_type' => $media->source_type,
            'source_label' => $media->source_label,
            'created_at' => $media->created_at?->toIso8601String(),
            'inbound_received_at' => $media->inboundMessage?->received_at?->toIso8601String(),
            'published_at' => $media->published_at?->toIso8601String(),
            'upload_to_publish_seconds' => $this->secondsBetween($media->created_at, $media->published_at),
            'inbound_to_publish_seconds' => $this->secondsBetween($media->inboundMessage?->received_at, $media->published_at),
        ];
    }

    public function recordPublished(EventMedia $media): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->info('media_pipeline.published', $this->publishPayload($media));
    }

    private function secondsBetween(mixed $startedAt, mixed $finishedAt): ?int
    {
        if (! $startedAt || ! $finishedAt) {
            return null;
        }

        return max(0, $startedAt->diffInSeconds($finishedAt));
    }
}

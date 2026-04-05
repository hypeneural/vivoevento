<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Support\Collection;

class MediaPipelineMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function forEvent(Event $event, bool $includeDeleted = true): array
    {
        $mediaQuery = EventMedia::query()
            ->where('event_id', $event->id)
            ->with(['processingRuns', 'inboundMessage']);

        if ($includeDeleted) {
            $mediaQuery->withTrashed();
        }

        /** @var Collection<int, EventMedia> $mediaItems */
        $mediaItems = $mediaQuery->get();

        $publishLatencies = $mediaItems
            ->filter(fn (EventMedia $media) => $media->published_at !== null)
            ->map(fn (EventMedia $media) => max(0, $media->created_at?->diffInSeconds($media->published_at) ?? 0))
            ->values();

        $inboundPublishLatencies = $mediaItems
            ->map(function (EventMedia $media): ?int {
                if (! $media->published_at || ! $media->inboundMessage?->received_at) {
                    return null;
                }

                return max(0, $media->inboundMessage->received_at->diffInSeconds($media->published_at));
            })
            ->filter(static fn ($value) => $value !== null)
            ->values();

        $firstUpdateLatencies = $mediaItems
            ->map(function (EventMedia $media): ?int {
                $firstFinishedAt = $media->processingRuns
                    ->whereIn('stage_key', ['variants', 'safety', 'vlm', 'moderation', 'publish'])
                    ->whereNotNull('finished_at')
                    ->sortBy('finished_at')
                    ->first()?->finished_at;

                if (! $firstFinishedAt || ! $media->created_at) {
                    return null;
                }

                return max(0, $media->created_at->diffInSeconds($firstFinishedAt));
            })
            ->filter(static fn ($value) => $value !== null)
            ->values();

        $faceIndexLatencies = $mediaItems
            ->map(function (EventMedia $media): ?int {
                $faceFinishedAt = $media->processingRuns
                    ->where('stage_key', 'face_index')
                    ->where('status', 'completed')
                    ->sortByDesc('finished_at')
                    ->first()?->finished_at;

                if (! $faceFinishedAt || ! $media->created_at) {
                    return null;
                }

                return max(0, $media->created_at->diffInSeconds($faceFinishedAt));
            })
            ->filter(static fn ($value) => $value !== null)
            ->values();

        $processingRunBase = MediaProcessingRun::query()
            ->whereHas('media', function ($query) use ($event): void {
                $query->withTrashed()->where('event_id', $event->id);
            });

        $queueBacklog = (clone $processingRunBase)
            ->where('status', 'processing')
            ->get()
            ->groupBy(fn (MediaProcessingRun $run) => $run->queue_name ?: 'unknown')
            ->map(fn (Collection $runs, string $queueName) => [
                'queue_name' => $queueName,
                'processing_runs' => $runs->count(),
            ])
            ->values()
            ->all();

        $failureBreakdown = (clone $processingRunBase)
            ->where('status', 'failed')
            ->get()
            ->groupBy(fn (MediaProcessingRun $run) => sprintf(
                '%s:%s',
                $run->stage_key ?: 'unknown',
                $run->failure_class ?: 'unknown',
            ))
            ->map(function (Collection $runs, string $key): array {
                [$stageKey, $failureClass] = array_pad(explode(':', $key, 2), 2, 'unknown');

                return [
                    'stage_key' => $stageKey,
                    'failure_class' => $failureClass,
                    'count' => $runs->count(),
                ];
            })
            ->sortBy([
                ['stage_key', 'asc'],
                ['failure_class', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
            ],
            'summary' => [
                'media_total' => $mediaItems->count(),
                'approved_total' => $mediaItems->where('moderation_status', 'approved')->count(),
                'pending_total' => $mediaItems->where('moderation_status', 'pending')->count(),
                'rejected_total' => $mediaItems->where('moderation_status', 'rejected')->count(),
                'published_total' => $mediaItems->where('publication_status', 'published')->count(),
                'blocked_total' => $mediaItems->where('safety_status', 'block')->count(),
                'review_total' => $mediaItems->filter(function (EventMedia $media): bool {
                    return in_array($media->safety_status, ['review'], true)
                        || in_array($media->vlm_status, ['review'], true);
                })->count(),
            ],
            'sla' => [
                'upload_to_publish_seconds' => $this->distribution($publishLatencies),
                'inbound_to_publish_seconds' => $this->distribution($inboundPublishLatencies),
                'upload_to_first_update_seconds' => $this->distribution($firstUpdateLatencies),
                'upload_to_face_index_seconds' => $this->distribution($faceIndexLatencies),
            ],
            'queues' => [
                'backlog' => $queueBacklog,
            ],
            'failures' => $failureBreakdown,
        ];
    }

    /**
     * @param Collection<int, int> $values
     * @return array<string, float|int|null>
     */
    private function distribution(Collection $values): array
    {
        if ($values->isEmpty()) {
            return [
                'count' => 0,
                'avg' => null,
                'p50' => null,
                'p95' => null,
            ];
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();

        return [
            'count' => $count,
            'avg' => round((float) $sorted->avg(), 2),
            'p50' => $this->percentile($sorted, 0.50),
            'p95' => $this->percentile($sorted, 0.95),
        ];
    }

    /**
     * @param Collection<int, int> $sorted
     */
    private function percentile(Collection $sorted, float $percentile): float
    {
        $count = max(1, $sorted->count());
        $index = (int) ceil($count * $percentile) - 1;
        $index = max(0, min($count - 1, $index));

        return (float) $sorted->get($index, 0);
    }
}

<?php

namespace App\Modules\FaceSearch\Jobs;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\AwsUserVectorReadinessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAwsUserVectorJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $backoff = 20;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $eventId,
    ) {
        $this->onQueue('face-index');
    }

    public function uniqueId(): string
    {
        return "aws-user-vector:{$this->eventId}";
    }

    public function handle(
        AwsRekognitionFaceSearchBackend $backend,
        AwsUserVectorReadinessService $readiness,
    ): void {
        $event = Event::query()
            ->with('faceSearchSettings')
            ->find($this->eventId);

        if (! $event) {
            return;
        }

        $settings = $event->faceSearchSettings;

        if (! $settings?->enabled || ! $settings->recognition_enabled || $settings->search_backend_key !== 'aws_rekognition') {
            return;
        }

        $summary = $readiness->evaluate($event, $settings);

        foreach ((array) ($summary['ready_clusters'] ?? []) as $cluster) {
            $userId = is_string($cluster['user_id'] ?? null) ? $cluster['user_id'] : null;
            $faceIds = collect((array) ($cluster['face_ids'] ?? []))
                ->filter(fn (mixed $faceId): bool => is_string($faceId) && $faceId !== '')
                ->values()
                ->all();

            if ($userId === null || $faceIds === []) {
                continue;
            }

            $backend->syncUserVector($event, $settings, $userId, $faceIds);
        }
    }
}

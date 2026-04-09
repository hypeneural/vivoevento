<?php

namespace App\Modules\FaceSearch\Jobs;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileAwsCollectionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 20;

    public function __construct(
        public readonly int $eventId,
    ) {
        $this->onQueue('face-index');
    }

    public function handle(AwsRekognitionFaceSearchBackend $backend): void
    {
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

        $backend->reconcileCollection($event, $settings);
        SyncAwsUserVectorJob::dispatch($event->id);
    }
}

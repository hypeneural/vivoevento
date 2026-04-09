<?php

namespace App\Modules\FaceSearch\Jobs;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\QueueEventFaceSearchReindexAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackfillEventFaceSearchGalleryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;
    public int $backoff = 20;
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $eventId,
    ) {
        $this->onQueue('face-index');
    }

    public function uniqueId(): string
    {
        return "face-search-backfill:{$this->eventId}";
    }

    public function handle(QueueEventFaceSearchReindexAction $action): void
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

        $action->execute($event);
    }
}

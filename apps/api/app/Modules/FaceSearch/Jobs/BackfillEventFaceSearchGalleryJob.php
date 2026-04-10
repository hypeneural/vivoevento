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
use Illuminate\Support\Facades\Log;

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

        if (! is_string($settings->aws_collection_id) || trim($settings->aws_collection_id) === '') {
            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->info('face_search.backfill.waiting_for_collection', [
                    'event_id' => $event->id,
                    'queue' => $this->queue ?: 'face-index',
                ]);

            $this->release($this->backoff);

            return;
        }

        $action->execute($event, ensureBackend: false);
    }
}

<?php

namespace App\Modules\EventPeople\Jobs;

use App\Modules\EventPeople\Actions\ProjectEventPeopleReviewQueueAction;
use App\Modules\EventPeople\Support\EventPeopleQueues;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventMediaFace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProjectEventPeopleReviewQueueJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 10;
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $eventId,
        public readonly ?int $eventMediaFaceId = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(EventPeopleQueues::high());
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return sprintf('event-people-review:%d:%s', $this->eventId, $this->eventMediaFaceId ?? 'event');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(120),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [
            'event-people',
            'event-people:review-queue',
            'event:' . $this->eventId,
        ];

        if ($this->eventMediaFaceId !== null) {
            $tags[] = 'event-media-face:' . $this->eventMediaFaceId;
        }

        return $tags;
    }

    public function handle(ProjectEventPeopleReviewQueueAction $action): void
    {
        if ($this->eventMediaFaceId !== null) {
            $face = EventMediaFace::query()
                ->with('personAssignments.person')
                ->find($this->eventMediaFaceId);

            if ($face && (int) $face->event_id === (int) $this->eventId) {
                $action->executeForFace($face, reopenIgnored: true);
            }
        } else {
            $event = Event::query()->find($this->eventId);

            if ($event) {
                $action->executeForEvent($event, onlyMissing: false);
            }
        }

        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->info('event_people.review_queue.projected', [
                'event_id' => $this->eventId,
                'event_media_face_id' => $this->eventMediaFaceId,
                'queue' => $this->queue ?: EventPeopleQueues::high(),
            ]);
    }
}

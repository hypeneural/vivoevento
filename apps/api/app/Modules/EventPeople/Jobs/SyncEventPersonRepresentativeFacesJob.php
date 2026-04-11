<?php

namespace App\Modules\EventPeople\Jobs;

use App\Modules\EventPeople\Support\EventPeopleQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEventPersonRepresentativeFacesJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
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
        public readonly int $eventPersonId,
    ) {
        $this->onConnection('redis');
        $this->onQueue(EventPeopleQueues::low());
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return sprintf('event-people-representatives-sync:%d:%d', $this->eventId, $this->eventPersonId);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('event-people-aws-sync'),
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(300),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'event-people',
            'event-people:aws-sync',
            'event:' . $this->eventId,
            'event-person:' . $this->eventPersonId,
        ];
    }

    public function handle(): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->info('event_people.representative_faces.sync_requested', [
                'event_id' => $this->eventId,
                'event_person_id' => $this->eventPersonId,
                'queue' => $this->queue ?: EventPeopleQueues::low(),
            ]);
    }
}

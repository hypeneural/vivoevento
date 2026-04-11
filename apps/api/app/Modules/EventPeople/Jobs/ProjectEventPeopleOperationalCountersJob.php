<?php

namespace App\Modules\EventPeople\Jobs;

use App\Modules\EventPeople\Services\EventPeopleOperationalMetricsService;
use App\Modules\EventPeople\Support\EventPeopleQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProjectEventPeopleOperationalCountersJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
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
    ) {
        $this->onConnection('redis');
        $this->onQueue(EventPeopleQueues::medium());
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'event-people-counters:' . $this->eventId;
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
        return [
            'event-people',
            'event-people:counters',
            'event:' . $this->eventId,
        ];
    }

    public function handle(EventPeopleOperationalMetricsService $metrics): void
    {
        Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
            ->info('event_people.operational_counters.projected', $metrics->snapshot($this->eventId) + [
                'event_id' => $this->eventId,
                'queue' => $this->queue ?: EventPeopleQueues::medium(),
            ]);
    }
}

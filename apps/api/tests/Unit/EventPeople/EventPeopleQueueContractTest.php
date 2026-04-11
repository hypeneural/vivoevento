<?php

use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleReviewQueueJob;
use App\Modules\EventPeople\Jobs\SyncEventPersonRepresentativeFacesJob;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\RateLimiter;

it('uses redis queues and after commit for event people jobs', function () {
    $review = new ProjectEventPeopleReviewQueueJob(eventId: 123, eventMediaFaceId: 456);
    $counters = new ProjectEventPeopleOperationalCountersJob(eventId: 123);
    $sync = new SyncEventPersonRepresentativeFacesJob(eventId: 123, eventPersonId: 789);

    foreach ([$review, $counters, $sync] as $job) {
        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
            ->and($job)->toBeInstanceOf(ShouldBeEncrypted::class)
            ->and($job->connection)->toBe('redis')
            ->and($job->afterCommit)->toBeTrue();
    }

    expect($review->queue)->toBe('event-people-high')
        ->and($counters->queue)->toBe('event-people-medium')
        ->and($sync->queue)->toBe('event-people-low');
});

it('defines stable unique locks and horizon tags for event people jobs', function () {
    $review = new ProjectEventPeopleReviewQueueJob(eventId: 123, eventMediaFaceId: 456);
    $counters = new ProjectEventPeopleOperationalCountersJob(eventId: 123);
    $sync = new SyncEventPersonRepresentativeFacesJob(eventId: 123, eventPersonId: 789);

    expect($review->uniqueId())->toBe('event-people-review:123:456')
        ->and($review->tags())->toContain('event-people', 'event-people:review-queue', 'event:123', 'event-media-face:456')
        ->and($counters->uniqueId())->toBe('event-people-counters:123')
        ->and($counters->tags())->toContain('event-people', 'event-people:counters', 'event:123')
        ->and($sync->uniqueId())->toBe('event-people-representatives-sync:123:789')
        ->and($sync->tags())->toContain('event-people', 'event-people:aws-sync', 'event:123', 'event-person:789');
});

it('protects event people jobs with overlap and aws sync rate limit middleware', function () {
    $reviewMiddleware = (new ProjectEventPeopleReviewQueueJob(eventId: 123, eventMediaFaceId: 456))->middleware();
    $counterMiddleware = (new ProjectEventPeopleOperationalCountersJob(eventId: 123))->middleware();
    $syncMiddleware = (new SyncEventPersonRepresentativeFacesJob(eventId: 123, eventPersonId: 789))->middleware();

    expect($reviewMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($reviewMiddleware[0]->key)->toBe('event-people-review:123:456')
        ->and($counterMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($counterMiddleware[0]->key)->toBe('event-people-counters:123')
        ->and($syncMiddleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($syncMiddleware[1])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($syncMiddleware[1]->key)->toBe('event-people-representatives-sync:123:789');

    $limiterName = (new ReflectionProperty($syncMiddleware[0], 'limiterName'));
    $limiterName->setAccessible(true);

    expect($limiterName->getValue($syncMiddleware[0]))->toBe('event-people-aws-sync')
        ->and(RateLimiter::limiter('event-people-aws-sync'))->not->toBeNull();
});

it('registers horizon queues and wait thresholds for event people', function () {
    expect(config('horizon.waits.redis:event-people-high'))->toBe(20)
        ->and(config('horizon.waits.redis:event-people-medium'))->toBe(60)
        ->and(config('horizon.waits.redis:event-people-low'))->toBe(180)
        ->and(config('horizon.defaults.supervisor-event-people.connection'))->toBe('redis')
        ->and(config('horizon.defaults.supervisor-event-people.queue'))->toBe([
            'event-people-high',
            'event-people-medium',
            'event-people-low',
        ]);
});

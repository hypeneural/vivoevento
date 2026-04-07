<?php

use App\Modules\Notifications\Support\NotificationDedupeKey;

it('builds semantic dedupe keys without time buckets', function () {
    $first = NotificationDedupeKey::make('media.processing.failed', 'event', 123);
    $second = NotificationDedupeKey::make(' media.processing.failed ', ' event ', 123);

    expect($first)->toBe('media.processing.failed:event:123')
        ->and($second)->toBe($first);
});

it('keeps different notification contexts isolated', function () {
    expect(NotificationDedupeKey::make('media.processing.failed', 'event', 123))
        ->not->toBe(NotificationDedupeKey::make('media.processing.failed', 'event', 124))
        ->and(NotificationDedupeKey::make('media.processing.failed', 'event', 123))
        ->not->toBe(NotificationDedupeKey::make('media.processing.failed', 'org', 123));
});

it('rejects unsafe dedupe key segments', function () {
    expect(fn () => NotificationDedupeKey::make('', 'event', 123))->toThrow(InvalidArgumentException::class)
        ->and(fn () => NotificationDedupeKey::make('media:failed', 'event', 123))->toThrow(InvalidArgumentException::class)
        ->and(fn () => NotificationDedupeKey::make('media.processing.failed', 'event', ''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => NotificationDedupeKey::make('media.processing.failed', 'event', '12:3'))->toThrow(InvalidArgumentException::class);
});

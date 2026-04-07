<?php

use App\Modules\Notifications\Enums\NotificationStatus;
use App\Modules\Notifications\Support\NotificationIncidentLifecycle;
use Carbon\CarbonImmutable;

it('updates an active incident occurrence and makes it unread again', function () {
    $firstOccurredAt = CarbonImmutable::parse('2026-04-07 09:00:00');
    $lastOccurredAt = CarbonImmutable::parse('2026-04-07 09:20:00');
    $readAt = CarbonImmutable::parse('2026-04-07 09:30:00');
    $newOccurrenceAt = CarbonImmutable::parse('2026-04-07 10:00:00');

    $next = NotificationIncidentLifecycle::applyOccurrence([
        'status' => NotificationStatus::Active->value,
        'occurrence_count' => 2,
        'first_occurred_at' => $firstOccurredAt,
        'last_occurred_at' => $lastOccurredAt,
        'read_at' => $readAt,
        'dismissed_at' => null,
        'resolved_at' => null,
    ], $newOccurrenceAt);

    expect($next['status'])->toBe(NotificationStatus::Active->value)
        ->and($next['occurrence_count'])->toBe(3)
        ->and($next['first_occurred_at'])->toBe($firstOccurredAt)
        ->and($next['last_occurred_at'])->toBe($newOccurrenceAt)
        ->and($next['read_at'])->toBeNull()
        ->and($next['dismissed_at'])->toBeNull()
        ->and($next['resolved_at'])->toBeNull();
});

it('reopens a resolved incident when the same problem happens again', function () {
    $resolvedAt = CarbonImmutable::parse('2026-04-07 11:00:00');
    $newOccurrenceAt = CarbonImmutable::parse('2026-04-07 12:00:00');

    $next = NotificationIncidentLifecycle::applyOccurrence([
        'status' => NotificationStatus::Resolved->value,
        'occurrence_count' => 1,
        'first_occurred_at' => CarbonImmutable::parse('2026-04-07 08:00:00'),
        'last_occurred_at' => CarbonImmutable::parse('2026-04-07 08:00:00'),
        'read_at' => CarbonImmutable::parse('2026-04-07 08:05:00'),
        'dismissed_at' => null,
        'resolved_at' => $resolvedAt,
    ], $newOccurrenceAt);

    expect($next['status'])->toBe(NotificationStatus::Active->value)
        ->and($next['occurrence_count'])->toBe(2)
        ->and($next['last_occurred_at'])->toBe($newOccurrenceAt)
        ->and($next['read_at'])->toBeNull()
        ->and($next['resolved_at'])->toBeNull();
});

it('reopens a dismissed incident when the same problem happens again', function () {
    $dismissedAt = CarbonImmutable::parse('2026-04-07 11:00:00');
    $newOccurrenceAt = CarbonImmutable::parse('2026-04-07 12:00:00');

    $next = NotificationIncidentLifecycle::applyOccurrence([
        'status' => NotificationStatus::Dismissed->value,
        'occurrence_count' => 4,
        'first_occurred_at' => CarbonImmutable::parse('2026-04-07 08:00:00'),
        'last_occurred_at' => CarbonImmutable::parse('2026-04-07 10:30:00'),
        'read_at' => CarbonImmutable::parse('2026-04-07 10:35:00'),
        'dismissed_at' => $dismissedAt,
        'resolved_at' => null,
    ], $newOccurrenceAt);

    expect($next['status'])->toBe(NotificationStatus::Active->value)
        ->and($next['occurrence_count'])->toBe(5)
        ->and($next['last_occurred_at'])->toBe($newOccurrenceAt)
        ->and($next['read_at'])->toBeNull()
        ->and($next['dismissed_at'])->toBeNull();
});

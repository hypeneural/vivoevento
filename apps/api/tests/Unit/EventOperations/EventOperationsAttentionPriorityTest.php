<?php

use App\Modules\EventOperations\Support\EventOperationsAttentionPriority;

it('elevates critical operational incidents to immediate priority', function () {
    $priority = app(EventOperationsAttentionPriority::class);

    expect($priority->resolve(
        severity: 'critical',
        urgency: 'critical',
        eventKey: 'wall.health.changed',
    ))->toBe(EventOperationsAttentionPriority::CRITICAL_IMMEDIATE);
});

it('keeps warning signals in the operational lane', function () {
    $priority = app(EventOperationsAttentionPriority::class);

    expect($priority->resolve(
        severity: 'warning',
        urgency: 'high',
        eventKey: 'media.moderation.rejected',
    ))->toBe(EventOperationsAttentionPriority::OPERATIONAL_NORMAL);
});

it('marks low value throughput updates as timeline coalescible', function () {
    $priority = app(EventOperationsAttentionPriority::class);

    expect($priority->resolve(
        severity: 'info',
        urgency: 'low',
        eventKey: 'media.variants.generated',
    ))->toBe(EventOperationsAttentionPriority::TIMELINE_COALESCIBLE)
        ->and($priority->score(EventOperationsAttentionPriority::CRITICAL_IMMEDIATE))
        ->toBeGreaterThan($priority->score(EventOperationsAttentionPriority::OPERATIONAL_NORMAL))
        ->and($priority->score(EventOperationsAttentionPriority::OPERATIONAL_NORMAL))
        ->toBeGreaterThan($priority->score(EventOperationsAttentionPriority::TIMELINE_COALESCIBLE));
});

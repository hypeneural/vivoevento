<?php

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Enums\EventType;
use App\Modules\Organizations\Enums\OrganizationType;

// ─── EventStatus ─────────────────────────────────────────

it('has all expected event statuses', function () {
    expect(EventStatus::cases())->toHaveCount(6);
    expect(EventStatus::Draft->value)->toBe('draft');
    expect(EventStatus::Active->value)->toBe('active');
    expect(EventStatus::Archived->value)->toBe('archived');
    expect(EventStatus::Scheduled->value)->toBe('scheduled');
    expect(EventStatus::Paused->value)->toBe('paused');
    expect(EventStatus::Ended->value)->toBe('ended');
});

// ─── EventType ───────────────────────────────────────────

it('has all expected event types', function () {
    expect(EventType::cases())->toHaveCount(7);
    expect(EventType::Wedding->value)->toBe('wedding');
    expect(EventType::Birthday->value)->toBe('birthday');
    expect(EventType::Corporate->value)->toBe('corporate');
});

// ─── OrganizationType ────────────────────────────────────

it('has all expected organization types', function () {
    $cases = OrganizationType::cases();
    expect($cases)->not->toBeEmpty();

    // All values should be strings
    foreach ($cases as $case) {
        expect($case->value)->toBeString();
    }
});

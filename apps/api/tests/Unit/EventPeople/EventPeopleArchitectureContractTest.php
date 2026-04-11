<?php

use App\Modules\EventPeople\Enums\EventPersonAssignmentStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonType;
use App\Modules\EventPeople\Providers\EventPeopleServiceProvider;
use App\Modules\EventPeople\Support\PersonPairKey;

it('registers the event people module provider', function () {
    expect(config('modules.modules.EventPeople'))->toBe(EventPeopleServiceProvider::class);
});

it('keeps the phase-zero enum contract stable', function () {
    expect(EventPersonType::Bride->value)->toBe('bride')
        ->and(EventPersonType::Vendor->value)->toBe('vendor')
        ->and(EventPersonAssignmentStatus::Confirmed->value)->toBe('confirmed')
        ->and(EventPersonReviewQueueStatus::Conflict->value)->toBe('conflict');
});

it('normalizes pair keys so relation and cooccurrence reads cannot duplicate A-B and B-A', function () {
    expect(PersonPairKey::make(9, 2))->toBe('2:9')
        ->and(PersonPairKey::make(2, 9))->toBe('2:9');
});

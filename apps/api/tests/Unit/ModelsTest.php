<?php

use App\Modules\Events\Models\Event;

// ─── Event Model ─────────────────────────────────────────

it('auto-generates uuid on creation', function () {
    $event = Event::factory()->create();

    expect($event->uuid)->not->toBeNull();
    expect(strlen($event->uuid))->toBe(36); // UUID format
});

it('auto-generates upload_slug on creation', function () {
    $event = Event::factory()->create();

    expect($event->upload_slug)->not->toBeNull();
    expect(strlen($event->upload_slug))->toBe(12);
});

it('detects auto moderation correctly', function () {
    $event = Event::factory()->create(['moderation_mode' => 'auto']);
    expect($event->isAutoModeration())->toBeTrue();

    $event2 = Event::factory()->create(['moderation_mode' => 'manual']);
    expect($event2->isAutoModeration())->toBeFalse();
});

it('detects draft status correctly', function () {
    $event = Event::factory()->draft()->create();
    expect($event->isDraft())->toBeTrue();
    expect($event->isActive())->toBeFalse();
});

it('detects active status correctly', function () {
    $event = Event::factory()->active()->create();
    expect($event->isActive())->toBeTrue();
    expect($event->isDraft())->toBeFalse();
});

it('scopes by organization', function () {
    $org1 = \App\Modules\Organizations\Models\Organization::factory()->create();
    $org2 = \App\Modules\Organizations\Models\Organization::factory()->create();

    Event::factory()->count(3)->create(['organization_id' => $org1->id]);
    Event::factory()->count(2)->create(['organization_id' => $org2->id]);

    expect(Event::forOrganization($org1->id)->count())->toBe(3);
    expect(Event::forOrganization($org2->id)->count())->toBe(2);
});

// ─── Organization Model ──────────────────────────────────

it('auto-generates uuid for organization', function () {
    $org = \App\Modules\Organizations\Models\Organization::factory()->create();

    expect($org->uuid)->not->toBeNull();
    expect(strlen($org->uuid))->toBe(36);
});

it('returns display name from trade_name', function () {
    $org = \App\Modules\Organizations\Models\Organization::factory()->create([
        'trade_name' => 'Studio Lumière',
        'legal_name' => 'Studio Lumière LTDA',
    ]);

    expect($org->displayName())->toBe('Studio Lumière');
});

<?php

use App\Modules\Events\Enums\EventModerationMode;
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

it('detects moderation modes correctly', function () {
    $event = Event::factory()->create(['moderation_mode' => 'none']);
    expect($event->isNoModeration())->toBeTrue()
        ->and($event->isAutoModeration())->toBeTrue()
        ->and($event->isManualModeration())->toBeFalse()
        ->and($event->isAiModeration())->toBeFalse();

    $manualEvent = Event::factory()->create(['moderation_mode' => 'manual']);
    expect($manualEvent->isNoModeration())->toBeFalse()
        ->and($manualEvent->isManualModeration())->toBeTrue()
        ->and($manualEvent->isAiModeration())->toBeFalse();

    $aiEvent = Event::factory()->create(['moderation_mode' => 'ai']);
    expect($aiEvent->isNoModeration())->toBeFalse()
        ->and($aiEvent->isManualModeration())->toBeFalse()
        ->and($aiEvent->isAiModeration())->toBeTrue();
});

it('normalizes legacy moderation mode values', function () {
    expect(EventModerationMode::normalize('auto'))->toBe('none')
        ->and(EventModerationMode::fromStorage('auto'))->toBe(EventModerationMode::None)
        ->and(EventModerationMode::fromStorage('ai'))->toBe(EventModerationMode::Ai)
        ->and(EventModerationMode::fromStorage(null))->toBeNull();
});

it('detects face search settings correctly', function () {
    $event = Event::factory()->create();

    expect($event->isFaceSearchEnabled())->toBeFalse()
        ->and($event->allowsPublicSelfieSearch())->toBeFalse();

    \Database\Factories\EventFaceSearchSettingFactory::new()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'allow_public_selfie_search' => true,
    ]);

    $event->refresh()->load('faceSearchSettings');

    expect($event->isFaceSearchEnabled())->toBeTrue()
        ->and($event->allowsPublicSelfieSearch())->toBeTrue();
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

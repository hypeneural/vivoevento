<?php

use App\Modules\Events\Models\Event;

it('returns effective event branding inherited from the organization by default', function () {
    [$user, $organization] = $this->actingAsOwner();

    $organization->update([
        'logo_path' => 'organizations/branding/org-logo.webp',
        'cover_path' => 'organizations/branding/org-cover.webp',
        'primary_color' => '#111111',
        'secondary_color' => '#222222',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $user->id,
        'logo_path' => null,
        'cover_image_path' => null,
        'primary_color' => null,
        'secondary_color' => null,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    expect($response->json('data.inherit_branding'))->toBeTrue();
    expect($response->json('data.effective_branding.logo_path'))->toBe('organizations/branding/org-logo.webp');
    expect($response->json('data.effective_branding.cover_image_path'))->toBe('organizations/branding/org-cover.webp');
    expect($response->json('data.effective_branding.primary_color'))->toBe('#111111');
    expect($response->json('data.effective_branding.secondary_color'))->toBe('#222222');
    expect($response->json('data.effective_branding.source'))->toBe('organization');
});

it('keeps explicit event branding overrides while inheriting missing fields', function () {
    [$user, $organization] = $this->actingAsOwner();

    $organization->update([
        'logo_path' => 'organizations/branding/org-logo.webp',
        'cover_path' => 'organizations/branding/org-cover.webp',
        'primary_color' => '#111111',
        'secondary_color' => '#222222',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $user->id,
        'logo_path' => 'events/branding/event-logo.webp',
        'cover_image_path' => null,
        'primary_color' => '#ff6600',
        'secondary_color' => null,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    expect($response->json('data.effective_branding.logo_path'))->toBe('events/branding/event-logo.webp');
    expect($response->json('data.effective_branding.cover_image_path'))->toBe('organizations/branding/org-cover.webp');
    expect($response->json('data.effective_branding.primary_color'))->toBe('#ff6600');
    expect($response->json('data.effective_branding.secondary_color'))->toBe('#222222');
    expect($response->json('data.effective_branding.source'))->toBe('mixed');
});

it('can disable organization branding inheritance for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $organization->update([
        'logo_path' => 'organizations/branding/org-logo.webp',
        'cover_path' => 'organizations/branding/org-cover.webp',
        'primary_color' => '#111111',
        'secondary_color' => '#222222',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'created_by' => $user->id,
        'logo_path' => null,
        'cover_image_path' => null,
        'primary_color' => null,
        'secondary_color' => null,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'branding' => [
            'inherit_branding' => false,
        ],
    ]);

    $this->assertApiSuccess($response);

    expect($response->json('data.inherit_branding'))->toBeFalse();
    expect($response->json('data.effective_branding.logo_path'))->toBeNull();
    expect($response->json('data.effective_branding.cover_image_path'))->toBeNull();
    expect($response->json('data.effective_branding.primary_color'))->toBeNull();
    expect($response->json('data.effective_branding.source'))->toBe('event');
});

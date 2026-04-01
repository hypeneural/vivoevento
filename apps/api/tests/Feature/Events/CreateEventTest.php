<?php

use App\Modules\Events\Models\Event;
use App\Modules\Clients\Models\Client;

// ─── Create Event ────────────────────────────────────────

it('creates an event with full payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $client = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'title' => 'Casamento Ana & Pedro',
        'event_type' => 'wedding',
        'starts_at' => now()->addMonth()->toISOString(),
        'ends_at' => now()->addMonth()->addHours(6)->toISOString(),
        'branding' => [
            'primary_color' => '#7c3aed',
            'secondary_color' => '#3b82f6',
        ],
        'modules' => [
            'live' => true,
            'wall' => true,
            'play' => true,
            'hub' => true,
        ],
        'privacy' => [
            'moderation_mode' => 'manual',
            'retention_days' => 30,
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $response->assertJsonStructure([
        'data' => [
            'id', 'uuid', 'title', 'status', 'slug',
            'upload_slug', 'public_url', 'upload_url', 'upload_api_url',
            'modules', 'links', 'qr',
        ],
    ]);

    $data = $response->json('data');
    expect($data['title'])->toBe('Casamento Ana & Pedro');
    expect($data['status'])->toBe('draft');
    expect($data['upload_slug'])->toHaveLength(12);
    expect($data['links'])->toHaveKeys(['public_hub', 'upload', 'upload_api', 'wall']);
    expect($data['qr']['status'])->toBe('pending');

    $this->assertDatabaseHas('events', [
        'title' => 'Casamento Ana & Pedro',
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'moderation_mode' => 'manual',
    ]);
});

it('creates event modules correctly', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento com módulos',
        'event_type' => 'birthday',
        'modules' => [
            'live' => true,
            'wall' => false,
            'play' => true,
            'hub' => false,
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.id');
    $modules = $response->json('data.modules');

    expect($modules['live'])->toBeTrue();
    expect($modules['wall'])->toBeFalse();
    expect($modules['play'])->toBeTrue();
    expect($modules['hub'])->toBeFalse();
});

it('validates required fields on event creation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', []);

    $this->assertApiValidationError($response, [
        'organization_id',
        'title',
        'event_type',
    ]);
});

it('validates event_type enum', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Test Event',
        'event_type' => 'tipo_invalido',
    ]);

    $this->assertApiValidationError($response, ['event_type']);
});

it('generates unique slug automatically', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Meu Evento',
        'event_type' => 'wedding',
    ]);

    $this->assertApiSuccess($response, 201);
    expect($response->json('data.slug'))->not->toBeNull();
});

// ─── List Events ─────────────────────────────────────────

it('lists events with pagination', function () {
    [$user, $organization] = $this->actingAsOwner();

    Event::factory()->count(5)->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet('/events?organization_id=' . $organization->id);

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);

    expect($response->json('data'))->toHaveCount(5);
});

// ─── Show Event ──────────────────────────────────────────

it('shows event details with all relations', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'data' => [
            'id', 'uuid', 'title', 'slug', 'status',
        ],
        'meta' => ['request_id'],
    ]);
});

// ─── Delete Event ────────────────────────────────────────

it('deletes an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiDelete("/events/{$event->id}");

    $response->assertStatus(204);
    $this->assertSoftDeleted('events', ['id' => $event->id]);
});

// ─── Unauthenticated ─────────────────────────────────────

it('rejects event creation for unauthenticated user', function () {
    $response = $this->apiPost('/events', [
        'title' => 'Test',
        'event_type' => 'wedding',
    ]);

    $this->assertApiUnauthorized($response);
});

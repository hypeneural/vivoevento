<?php

use App\Modules\Events\Models\Event;
use App\Modules\Clients\Models\Client;
use Illuminate\Support\Facades\DB;

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
    expect($data['commercial_mode'])->toBe('none');
    expect($data['current_entitlements'])->toBeArray();
    expect($data['upload_slug'])->toHaveLength(12);
    expect($data['links'])->toHaveKeys(['public_hub', 'upload', 'upload_api', 'wall']);
    expect($data['qr']['status'])->toBe('pending');

    $this->assertDatabaseHas('events', [
        'title' => 'Casamento Ana & Pedro',
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'moderation_mode' => 'manual',
        'commercial_mode' => 'none',
    ]);
});

it('accepts none moderation mode on event creation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento sem moderacao',
        'event_type' => 'birthday',
        'privacy' => [
            'moderation_mode' => 'none',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.moderation_mode'))->toBe('none');

    $this->assertDatabaseHas('events', [
        'title' => 'Evento sem moderacao',
        'organization_id' => $organization->id,
        'moderation_mode' => 'none',
    ]);
});

it('accepts ai moderation mode on event creation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento com moderacao IA',
        'event_type' => 'corporate',
        'privacy' => [
            'moderation_mode' => 'ai',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.moderation_mode'))->toBe('ai');

    $this->assertDatabaseHas('events', [
        'title' => 'Evento com moderacao IA',
        'organization_id' => $organization->id,
        'moderation_mode' => 'ai',
    ]);
});

it('persists face search settings during event creation', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento com busca por selfie',
        'event_type' => 'graduation',
        'face_search' => [
            'enabled' => true,
            'allow_public_selfie_search' => true,
            'selfie_retention_hours' => 48,
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.face_search.enabled'))->toBeTrue()
        ->and($response->json('data.face_search.allow_public_selfie_search'))->toBeTrue()
        ->and($response->json('data.face_search.selfie_retention_hours'))->toBe(48);

    $eventId = $response->json('data.id');

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $eventId,
        'enabled' => true,
        'allow_public_selfie_search' => true,
        'selfie_retention_hours' => 48,
    ]);
});

it('migrates legacy auto moderation mode records to none', function () {
    [$user, $organization] = $this->actingAsOwner();

    $legacyEventId = DB::table('events')->insertGetId([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $organization->id,
        'client_id' => null,
        'created_by' => $user->id,
        'title' => 'Evento legado auto',
        'slug' => 'evento-legado-auto-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
        'upload_slug' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(12)),
        'event_type' => 'birthday',
        'status' => 'draft',
        'visibility' => 'public',
        'moderation_mode' => 'auto',
        'starts_at' => now(),
        'ends_at' => now()->addHours(4),
        'location_name' => 'Teste legado',
        'description' => 'Registro legado para compatibilidade de migracao.',
        'retention_days' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require base_path('database/migrations/2026_04_01_160000_update_event_moderation_modes_to_none_manual_ai.php');
    $migration->up();

    expect(DB::table('events')->where('id', $legacyEventId)->value('moderation_mode'))->toBe('none');

    $event = Event::query()->findOrFail($legacyEventId);

    expect($event->moderation_mode)->toBe(\App\Modules\Events\Enums\EventModerationMode::None)
        ->and($event->isNoModeration())->toBeTrue();
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

    \Database\Factories\EventFaceSearchSettingFactory::new()->publicSelfieSearch()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonStructure([
        'data' => [
            'id', 'uuid', 'title', 'slug', 'status', 'commercial_mode', 'current_entitlements',
        ],
        'meta' => ['request_id'],
    ]);
    $response->assertJsonPath('data.face_search.enabled', true);
    $response->assertJsonPath('data.face_search.allow_public_selfie_search', true);
});

it('updates an event with nested branding privacy and modules payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $currentClient = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $newClient = Client::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'client_id' => $currentClient->id,
        'title' => 'Evento original',
        'slug' => 'evento-original',
        'event_type' => 'birthday',
        'visibility' => 'public',
        'moderation_mode' => 'manual',
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'client_id' => $newClient->id,
        'title' => 'Evento atualizado',
        'event_type' => 'corporate',
        'slug' => 'evento-atualizado',
        'starts_at' => now()->addDays(20)->toISOString(),
        'ends_at' => now()->addDays(20)->addHours(5)->toISOString(),
        'location_name' => 'Centro de Eventos Expo',
        'description' => 'Descricao atualizada',
        'branding' => [
            'primary_color' => '#111827',
            'secondary_color' => '#f97316',
            'cover_image_path' => 'https://example.com/cover.jpg',
            'logo_path' => 'https://example.com/logo.png',
        ],
        'modules' => [
            'live' => true,
            'wall' => true,
            'play' => false,
            'hub' => true,
        ],
        'privacy' => [
            'visibility' => 'private',
            'moderation_mode' => 'ai',
            'retention_days' => 90,
        ],
        'face_search' => [
            'enabled' => true,
            'allow_public_selfie_search' => false,
            'selfie_retention_hours' => 72,
        ],
    ]);

    $this->assertApiSuccess($response);
    expect($response->json('data.title'))->toBe('Evento atualizado');
    expect($response->json('data.slug'))->toBe('evento-atualizado');
    expect($response->json('data.client_id'))->toBe($newClient->id);
    expect($response->json('data.event_type'))->toBe('corporate');
    expect($response->json('data.visibility'))->toBe('private');
    expect($response->json('data.moderation_mode'))->toBe('ai');
    expect($response->json('data.face_search.enabled'))->toBeTrue();
    expect($response->json('data.face_search.selfie_retention_hours'))->toBe(72);
    expect($response->json('data.enabled_modules'))->toContain('wall', 'hub');
    expect($response->json('data.cover_image_path'))->toBe('https://example.com/cover.jpg');

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => 'Evento atualizado',
        'slug' => 'evento-atualizado',
        'client_id' => $newClient->id,
        'event_type' => 'corporate',
        'visibility' => 'private',
        'moderation_mode' => 'ai',
        'retention_days' => 90,
        'cover_image_path' => 'https://example.com/cover.jpg',
        'logo_path' => 'https://example.com/logo.png',
    ]);

    $this->assertDatabaseHas('event_modules', [
        'event_id' => $event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'enabled' => true,
        'allow_public_selfie_search' => false,
        'selfie_retention_hours' => 72,
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

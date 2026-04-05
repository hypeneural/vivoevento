<?php

use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;

it('creates a lightweight public trial account with event and active trial grant', function () {
    $this->seedPermissions();

    $response = $this->apiPost('/public/trial-events', [
        'responsible_name' => 'Fernanda Souza',
        'whatsapp' => '(48) 99999-1111',
        'email' => 'fernanda@example.com',
        'organization_name' => 'Fernanda Eventos',
        'device_name' => 'trial-web',
        'event' => [
            'title' => 'Casamento Fernanda & Lucas',
            'event_type' => 'wedding',
            'event_date' => '2026-10-12',
            'city' => 'Tijucas',
            'description' => 'Evento teste para validar a experiencia.',
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.event.id');
    $userId = $response->json('data.user.id');
    $organizationId = $response->json('data.organization.id');

    expect($response->json('data.token'))->toBeString()->not->toBe('');
    expect($response->json('data.organization.type'))->toBe('partner');
    expect($response->json('data.user.phone'))->toBe('5548999991111');
    expect($response->json('data.commercial_status.commercial_mode'))->toBe('trial');
    expect($response->json('data.trial.limits.max_photos'))->toBe(20);
    expect($response->json('data.trial.branding.watermark'))->toBeTrue();
    expect($response->json('data.onboarding.next_path'))->toBe("/events/{$eventId}");

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organizationId,
        'user_id' => $userId,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('event_access_grants', [
        'organization_id' => $organizationId,
        'event_id' => $eventId,
        'source_type' => 'trial',
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $userId,
        'tokenable_type' => User::class,
        'name' => 'trial-web',
    ]);

    $event = Event::query()->findOrFail($eventId);

    expect($event->commercial_mode?->value)->toBe('trial');
    expect($event->current_entitlements_json['limits']['max_photos'] ?? null)->toBe(20);
    expect($event->current_entitlements_json['branding']['watermark'] ?? null)->toBeTrue();
});

it('rejects public trial creation when whatsapp already belongs to an existing account', function () {
    User::factory()->create([
        'phone' => '5548999991111',
    ]);

    $response = $this->apiPost('/public/trial-events', [
        'responsible_name' => 'Fernanda Souza',
        'whatsapp' => '(48) 99999-1111',
        'event' => [
            'title' => 'Casamento Fernanda & Lucas',
            'event_type' => 'wedding',
        ],
    ]);

    $this->assertApiValidationError($response, ['whatsapp']);
});

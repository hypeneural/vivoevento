<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Users\Models\User;

it('returns paginated audit logs with scoped detailed payload', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $otherOrganization = $this->createOrganization();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento principal',
    ]);

    $otherEvent = Event::factory()->create([
        'organization_id' => $otherOrganization->id,
        'title' => 'Evento externo',
    ]);

    activity()
        ->performedOn($event)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'old' => ['status' => 'draft'],
            'attributes' => ['status' => 'active', 'title' => $event->title],
        ])
        ->log('Evento atualizado');

    activity()
        ->performedOn($otherEvent)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $otherOrganization->id,
            'event_id' => $otherEvent->id,
        ])
        ->log('Evento externo atualizado');

    $response = $this->apiGet('/audit');

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);

    $records = collect($response->json('data'));
    $entry = $records->firstWhere('description', 'Evento atualizado');

    expect($records->pluck('description')->all())->toContain('Evento atualizado');
    expect($records->pluck('description')->all())->not->toContain('Evento externo atualizado');
    expect($entry)->not->toBeNull();
    expect(data_get($entry, 'subject.type'))->toBe('event');
    expect(data_get($entry, 'related_event.title'))->toBe('Evento principal');
    expect(data_get($entry, 'changes.fields'))->toContain('status');
    expect($response->json('meta.scope.is_global'))->toBeFalse();
    expect($response->json('meta.scope.organization_id'))->toBe($organization->id);
});

it('filters audit logs by actor subject type and changes', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $otherActor = User::factory()->create();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $otherActor->id,
        'role_key' => 'partner-manager',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Noite premium',
    ]);

    activity()
        ->performedOn($event)
        ->causedBy($otherActor)
        ->withProperties([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'old' => ['status' => 'draft'],
            'attributes' => ['status' => 'active', 'title' => 'Noite premium'],
        ])
        ->log('Evento atualizado');

    activity()
        ->performedOn($event)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $organization->id,
        ])
        ->log('Evento visualizado');

    $response = $this->apiGet('/audit?' . http_build_query([
        'actor_id' => $otherActor->id,
        'subject_type' => 'event',
        'has_changes' => true,
        'search' => 'premium',
    ]));

    $this->assertApiSuccess($response);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.actor.name'))->toBe($otherActor->name);
    expect($response->json('data.0.changes.count'))->toBeGreaterThan(0);
});

it('returns scoped audit filter options', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    activity()
        ->performedOn($event)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
        ])
        ->log('Evento publicado');

    $response = $this->apiGet('/audit/filters');

    $this->assertApiSuccess($response);

    expect($response->json('data.actors.0.name'))->toBe($user->name);
    expect(collect($response->json('data.subject_types'))->pluck('key')->all())->toContain('event');
});

it('returns media audit entries with media subject metadata and filter option', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Festival Visual',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'caption' => 'Luz no palco',
        'original_filename' => 'palco.jpg',
    ]);

    activity()
        ->event('media.approved')
        ->performedOn($media)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'caption' => 'Luz no palco',
            'original_filename' => 'palco.jpg',
        ])
        ->log('Midia aprovada');

    $response = $this->apiGet('/audit?subject_type=media');

    $this->assertApiSuccess($response);

    expect($response->json('data.0.subject.type'))->toBe('media');
    expect($response->json('data.0.subject.type_label'))->toBe('Midia');
    expect($response->json('data.0.subject.label'))->toBe('Luz no palco');
    expect($response->json('data.0.related_event.title'))->toBe('Festival Visual');

    $filtersResponse = $this->apiGet('/audit/filters');

    $this->assertApiSuccess($filtersResponse);
    expect(collect($filtersResponse->json('data.subject_types'))->pluck('key')->all())->toContain('media');
});

it('returns audit entries generated by real client and event endpoints', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $clientResponse = $this->apiPost('/clients', [
        'name' => 'Cliente Timeline',
        'type' => 'empresa',
        'email' => 'timeline@cliente.test',
    ]);

    $this->assertApiSuccess($clientResponse, 201);

    $clientId = $clientResponse->json('data.id');

    $clientUpdateResponse = $this->apiPatch("/clients/{$clientId}", [
        'name' => 'Cliente Timeline Atualizado',
    ]);

    $this->assertApiSuccess($clientUpdateResponse);

    $eventResponse = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'client_id' => $clientId,
        'title' => 'Evento Timeline',
        'event_type' => 'wedding',
    ]);

    $this->assertApiSuccess($eventResponse, 201);

    $eventId = $eventResponse->json('data.id');

    $eventUpdateResponse = $this->apiPatch("/events/{$eventId}", [
        'title' => 'Evento Timeline Atualizado',
    ]);

    $this->assertApiSuccess($eventUpdateResponse);

    $response = $this->apiGet('/audit');

    $this->assertApiSuccess($response);

    $entries = collect($response->json('data'));
    $descriptions = $entries->pluck('description')->all();
    $subjects = $entries->map(fn (array $entry) => [
        'type' => data_get($entry, 'subject.type'),
        'label' => data_get($entry, 'subject.label'),
    ]);

    expect($descriptions)->toContain('Client was created');
    expect($descriptions)->toContain('Client was updated');
    expect($descriptions)->toContain('Evento criado');
    expect($descriptions)->toContain('Evento atualizado');
    expect($subjects)->toContain([
        'type' => 'client',
        'label' => 'Cliente Timeline Atualizado',
    ]);
    expect($subjects->contains([
        'type' => 'event',
        'label' => 'Evento Timeline Atualizado',
    ]))->toBeTrue();
});

it('redacts sensitive fields from audit payloads', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento seguro',
    ]);

    activity()
        ->performedOn($event)
        ->causedBy($user)
        ->withProperties([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'token' => 'tok_live_secret',
            'payload' => [
                'email' => 'cliente@example.com',
                'phone' => '+55 (11) 99999-1234',
                'code' => '123456',
            ],
            'old' => [
                'password' => 'before-secret',
                'contact_email' => 'antes@example.com',
                'contact_phone' => '+55 (11) 98888-0000',
            ],
            'attributes' => [
                'password' => 'after-secret',
                'contact_email' => 'depois@example.com',
                'contact_phone' => '+55 (11) 97777-0000',
            ],
        ])
        ->log('Senha atualizada');

    $response = $this->apiGet('/audit?search=senha');

    $this->assertApiSuccess($response);

    $entry = collect($response->json('data'))->firstWhere('description', 'Senha atualizada');

    expect($entry)->not->toBeNull();
    expect(data_get($entry, 'actor.email'))->not->toBe($user->email);
    expect(data_get($entry, 'changes.old.password'))->toBe('[REDACTED]');
    expect(data_get($entry, 'changes.new.password'))->toBe('[REDACTED]');
    expect(data_get($entry, 'metadata.token'))->toBe('[REDACTED]');
    expect(data_get($entry, 'metadata.payload.code'))->toBe('[REDACTED]');
    expect(data_get($entry, 'metadata.payload.email'))->not->toBe('cliente@example.com');
    expect(data_get($entry, 'metadata.payload.phone'))->not->toBe('+55 (11) 99999-1234');
});

it('returns timeline for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/timeline");

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

it('returns current user activity', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/users/me/activity');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

it('forbids audit access without permission', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/audit');

    $this->assertApiForbidden($response);
});

it('forbids scoping audit to another organization for non global viewers', function () {
    [$user, $organization] = $this->actingAsOwner();
    $user->givePermissionTo('audit.view');

    $otherOrganization = $this->createOrganization();

    $response = $this->apiGet('/audit?' . http_build_query([
        'organization_id' => $otherOrganization->id,
    ]));

    $this->assertApiForbidden($response);
});

it('rejects audit access for unauthenticated user', function () {
    $response = $this->apiGet('/audit');

    $this->assertApiUnauthorized($response);
});

<?php

use App\Modules\Events\Models\Event;
use App\Modules\EventTeam\Models\EventTeamMember;

it('exposes safe event access presets for the frontend instead of raw permission strings', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiGet('/access/presets');

    $this->assertApiSuccess($response);
    expect($response->json('data.event'))->toBeArray()->not->toBeEmpty();
    expect($response->json('data.organization'))->toBeArray()->not->toBeEmpty();
    expect($response->json('data.event.0'))->toHaveKeys([
        'key',
        'scope',
        'persisted_role',
        'label',
        'description',
        'capabilities',
    ]);
    expect($response->json('data.event.0'))->not->toHaveKey('permissions');
});

it('maps operar evento to wall play gallery and moderation capabilities for a single event', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiGet('/access/presets');

    $this->assertApiSuccess($response);

    $preset = collect($response->json('data.event'))->firstWhere('key', 'event.operator');

    expect($preset['persisted_role'])->toBe('operator');
    expect($preset['capabilities'])->toContain('media', 'moderation', 'wall', 'play');
});

it('maps moderar midias to media view and moderate capabilities without wall or play management', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiGet('/access/presets');

    $this->assertApiSuccess($response);

    $preset = collect($response->json('data.event'))->firstWhere('key', 'event.moderator');

    expect($preset['persisted_role'])->toBe('moderator');
    expect($preset['capabilities'])->toContain('media', 'moderation');
    expect($preset['capabilities'])->not->toContain('wall');
    expect($preset['capabilities'])->not->toContain('play');
});

it('maps ver midias to read only media and gallery capabilities for a single event', function () {
    [$user] = $this->actingAsOwner();

    $response = $this->apiGet('/access/presets');

    $this->assertApiSuccess($response);

    $preset = collect($response->json('data.event'))->firstWhere('key', 'event.media-viewer');

    expect($preset['persisted_role'])->toBe('viewer');
    expect($preset['capabilities'])->toContain('overview', 'media');
    expect($preset['capabilities'])->not->toContain('moderation');
    expect($preset['capabilities'])->not->toContain('wall');
    expect($preset['capabilities'])->not->toContain('play');
});

it('allows updating an event team member role through a preset while preserving the event scope', function () {
    [$owner, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $memberUser = $this->createUser([
        'email' => 'event-team-preset@eventovivo.test',
        'phone' => '5511988877661',
    ]);
    $memberUser->assignRole('viewer');

    $member = EventTeamMember::query()->create([
        'event_id' => $event->id,
        'user_id' => $memberUser->id,
        'role' => 'viewer',
    ]);

    $response = $this->apiPatch("/events/{$event->id}/team/{$member->id}", [
        'preset_key' => 'event.operator',
    ]);

    $this->assertApiSuccess($response);
    $member->refresh();

    expect($member->role)->toBe('operator');
    expect($response->json('data.role_key'))->toBe('event.operator');
    expect($response->json('data.capabilities'))->toContain('media', 'moderation', 'wall', 'play');
});

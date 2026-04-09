<?php

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');
    config()->set('broadcasting.connections.reverb.options.host', '127.0.0.1');
    config()->set('broadcasting.connections.reverb.options.port', 8080);
    config()->set('broadcasting.connections.reverb.options.scheme', 'http');
    config()->set('broadcasting.connections.reverb.options.useTLS', false);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
});

it('allows a same-organization operator to view wall settings', function () {
    [$user, $organization] = $this->actingAsOperator();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$domainEvent->id}/wall/settings");

    $this->assertApiSuccess($response);

    expect(EventWallSetting::query()->where('event_id', $domainEvent->id)->exists())->toBeTrue();
});

it('forbids wall settings access when the event belongs to another organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrganization = $this->createOrganization();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->apiGet("/events/{$domainEvent->id}/wall/settings");

    $this->assertApiForbidden($response);
});

it('allows a same-organization manager to update wall settings', function () {
    [$user, $organization] = $this->actingAsManager();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$domainEvent->id}/wall/settings", [
        'queue_limit' => 42,
        'show_qr' => false,
        'selection_mode' => 'inclusive',
        'event_phase' => 'reception',
        'video_enabled' => true,
        'video_playback_mode' => 'play_to_end_if_short_else_cap',
        'video_max_seconds' => 20,
        'video_resume_mode' => 'restart_from_zero',
        'video_audio_policy' => 'muted',
        'video_multi_layout_policy' => 'disallow',
        'video_preferred_variant' => 'wall_video_1080p',
        'selection_policy' => [
            'max_eligible_items_per_sender' => 3,
            'max_replays_per_item' => 1,
            'low_volume_max_items' => 5,
            'medium_volume_max_items' => 11,
            'replay_interval_low_minutes' => 6,
            'replay_interval_medium_minutes' => 11,
            'replay_interval_high_minutes' => 18,
            'sender_cooldown_seconds' => 90,
            'sender_window_limit' => 2,
            'sender_window_minutes' => 10,
            'avoid_same_sender_if_alternative_exists' => true,
            'avoid_same_duplicate_cluster_if_alternative_exists' => true,
        ],
    ]);

    $this->assertApiSuccess($response);

    $settings = EventWallSetting::query()->where('event_id', $domainEvent->id)->firstOrFail();

    expect($settings->queue_limit)->toBe(42)
        ->and($settings->show_qr)->toBeFalse()
        ->and($settings->selection_mode->value)->toBe('inclusive')
        ->and($settings->event_phase->value)->toBe('reception')
        ->and($settings->video_enabled)->toBeTrue()
        ->and($settings->video_playback_mode)->toBe('play_to_end_if_short_else_cap')
        ->and($settings->video_max_seconds)->toBe(20)
        ->and($settings->video_resume_mode)->toBe('restart_from_zero')
        ->and($settings->video_audio_policy)->toBe('muted')
        ->and($settings->video_multi_layout_policy)->toBe('disallow')
        ->and($settings->video_preferred_variant)->toBe('wall_video_1080p')
        ->and($settings->selection_policy['low_volume_max_items'])->toBe(5)
        ->and($settings->selection_policy['sender_cooldown_seconds'])->toBe(90);
});

it('allows a same-organization manager to send an operational player command', function () {
    [$user, $organization] = $this->actingAsManager();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);
    EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $response = $this->apiPost("/events/{$domainEvent->id}/wall/player-command", [
        'command' => 'clear-cache',
        'reason' => 'manager_clear_cache',
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.command', 'clear-cache')
        ->assertJsonPath('data.message', 'Comando enviado aos players do wall.');
});

it('allows private wall channel authentication for users with event access', function () {
    [$user, $organization] = $this->actingAsOwner();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.wall",
        'socket_id' => '1234.5678',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

it('rejects private wall channel authentication for users outside the event organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    $otherOrganization = $this->createOrganization();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.wall",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});

it('rejects moderation channel authentication when the user lacks moderation permission', function () {
    [$user, $organization] = $this->actingAsViewer();

    $domainEvent = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->post('/broadcasting/auth', [
        'channel_name' => "private-event.{$domainEvent->id}.moderation",
        'socket_id' => '1234.5678',
    ]);

    $response->assertForbidden();
});

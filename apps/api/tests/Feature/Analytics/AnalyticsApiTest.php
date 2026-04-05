<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameSession;
use App\Modules\Play\Models\PlayGameType;

function enableModules(Event $event, array $modules): void
{
    foreach ($modules as $module) {
        EventModule::query()->create([
            'event_id' => $event->id,
            'module_key' => $module,
            'is_enabled' => true,
        ]);
    }
}

function trackAnalyticsEvent(Event $event, string $eventName, \Carbon\CarbonInterface $occurredAt): void
{
    AnalyticsEvent::query()->create([
        'organization_id' => $event->organization_id,
        'event_id' => $event->id,
        'event_name' => $eventName,
        'actor_type' => 'guest',
        'actor_id' => 'browser-' . $event->id,
        'channel' => 'public',
        'metadata_json' => ['test' => true],
        'occurred_at' => $occurredAt,
        'created_at' => $occurredAt,
    ]);
}

it('scopes platform analytics to the current organization for partner users', function () {
    [, $organization] = $this->actingAsOwner();
    $otherOrganization = $this->createOrganization();

    $client = Client::factory()->create(['organization_id' => $organization->id]);
    $otherClient = Client::factory()->create(['organization_id' => $otherOrganization->id]);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'title' => 'Evento Alpha',
    ]);
    enableModules($event, ['live', 'hub', 'wall', 'play']);

    $otherEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
        'client_id' => $otherClient->id,
        'title' => 'Evento Beta',
    ]);
    enableModules($otherEvent, ['live', 'hub', 'wall', 'play']);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'created_at' => now()->subDay(),
    ]);
    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'created_at' => now()->subDay(),
        'published_at' => now()->subDay(),
    ]);

    EventMedia::factory()->published()->count(3)->create([
        'event_id' => $otherEvent->id,
        'created_at' => now()->subDay(),
        'published_at' => now()->subDay(),
    ]);

    trackAnalyticsEvent($event, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'gallery.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'wall.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'upload.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'upload.completed', now()->subDay());

    trackAnalyticsEvent($otherEvent, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($otherEvent, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($otherEvent, 'hub.page_view', now()->subDay());

    $gameType = PlayGameType::factory()->create();
    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
    ]);

    PlayGameSession::factory()->finished()->create([
        'event_game_id' => $game->id,
        'player_identifier' => 'player-alpha',
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addMinute(),
    ]);

    $response = $this->apiGet('/analytics/platform?period=30d');

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.filters.organization_id', $organization->id)
        ->assertJsonPath('data.summary.uploads_received', 2)
        ->assertJsonPath('data.summary.uploads_approved', 2)
        ->assertJsonPath('data.summary.uploads_published', 1)
        ->assertJsonPath('data.summary.hub_views', 2)
        ->assertJsonPath('data.summary.gallery_views', 1)
        ->assertJsonPath('data.summary.wall_views', 1)
        ->assertJsonPath('data.summary.upload_views', 1)
        ->assertJsonPath('data.summary.play_sessions', 1)
        ->assertJsonPath('data.summary.unique_players', 1)
        ->assertJsonPath('data.rankings.top_events.0.title', 'Evento Alpha');
});

it('allows super admins to filter platform analytics by organization', function () {
    [, $adminOrganization] = $this->actingAsSuperAdmin();
    $targetOrganization = $this->createOrganization();

    $adminClient = Client::factory()->create(['organization_id' => $adminOrganization->id]);
    $targetClient = Client::factory()->create(['organization_id' => $targetOrganization->id]);

    $adminEvent = Event::factory()->active()->create([
        'organization_id' => $adminOrganization->id,
        'client_id' => $adminClient->id,
        'title' => 'Evento Admin',
    ]);
    enableModules($adminEvent, ['live', 'hub']);

    $targetEvent = Event::factory()->active()->create([
        'organization_id' => $targetOrganization->id,
        'client_id' => $targetClient->id,
        'title' => 'Evento Target',
    ]);
    enableModules($targetEvent, ['live', 'hub']);

    EventMedia::factory()->published()->create([
        'event_id' => $adminEvent->id,
        'created_at' => now()->subDay(),
        'published_at' => now()->subDay(),
    ]);
    EventMedia::factory()->approved()->count(2)->create([
        'event_id' => $targetEvent->id,
        'created_at' => now()->subDay(),
    ]);

    trackAnalyticsEvent($adminEvent, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($targetEvent, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($targetEvent, 'gallery.page_view', now()->subDay());

    $response = $this->apiGet("/analytics/platform?period=30d&organization_id={$targetOrganization->id}");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.filters.organization_id', $targetOrganization->id)
        ->assertJsonPath('data.summary.uploads_received', 2)
        ->assertJsonPath('data.summary.uploads_published', 0)
        ->assertJsonPath('data.summary.hub_views', 1)
        ->assertJsonPath('data.summary.gallery_views', 1)
        ->assertJsonPath('data.rankings.top_events.0.title', 'Evento Target');
});

it('returns event analytics with funnel deltas and play drill-down data', function () {
    [, $organization] = $this->actingAsOwner();

    $client = Client::factory()->create(['organization_id' => $organization->id]);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'client_id' => $client->id,
        'title' => 'Evento Drill Down',
    ]);
    enableModules($event, ['live', 'hub', 'play']);

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'created_at' => now()->subDays(2),
    ]);
    EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'created_at' => now()->subDay(),
        'published_at' => now()->subDay(),
    ]);
    EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'created_at' => now()->subDays(8),
    ]);

    trackAnalyticsEvent($event, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'hub.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'play.page_view', now()->subDay());
    trackAnalyticsEvent($event, 'play.page_view', now()->subDays(8));

    $gameType = PlayGameType::factory()->create();
    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'title' => 'Memory Show',
        'slug' => 'memory-show',
    ]);

    PlayGameSession::factory()->finished()->create([
        'event_game_id' => $game->id,
        'player_identifier' => 'player-current',
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay()->addMinute(),
    ]);
    PlayGameSession::factory()->finished()->create([
        'event_game_id' => $game->id,
        'player_identifier' => 'player-previous',
        'started_at' => now()->subDays(8),
        'finished_at' => now()->subDays(8)->addMinute(),
    ]);

    $response = $this->apiGet("/analytics/events/{$event->id}?period=7d&module=play");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.event.title', 'Evento Drill Down')
        ->assertJsonPath('data.summary.uploads_received', 2)
        ->assertJsonPath('data.summary.uploads_published', 1)
        ->assertJsonPath('data.deltas.uploads_received.value', 100)
        ->assertJsonPath('data.funnel.0.count', 2)
        ->assertJsonPath('data.funnel.1.percentage', 100)
        ->assertJsonPath('data.funnel.2.percentage', 50)
        ->assertJsonPath('data.breakdowns.surfaces.0.key', 'play')
        ->assertJsonPath('data.play.enabled', true)
        ->assertJsonPath('data.play.games.0.title', 'Memory Show')
        ->assertJsonPath('data.play.games.0.sessions', 1);
});

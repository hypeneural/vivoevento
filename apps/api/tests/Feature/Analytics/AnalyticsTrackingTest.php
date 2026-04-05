<?php

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameType;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

function enablePublicModule(Event $event, string $module): void
{
    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => $module,
        'is_enabled' => true,
    ]);
}

it('tracks public hub gallery and wall page views into analytics events', function () {
    Storage::fake('public');

    $hubEvent = Event::factory()->active()->create();
    enablePublicModule($hubEvent, 'hub');

    $galleryEvent = Event::factory()->active()->create();
    enablePublicModule($galleryEvent, 'live');

    $wallEvent = Event::factory()->active()->create();
    enablePublicModule($wallEvent, 'wall');
    $wallSettings = EventWallSetting::factory()->live()->create([
        'event_id' => $wallEvent->id,
    ]);

    $this->apiGet("/public/events/{$hubEvent->slug}/hub")->assertStatus(200);
    $this->apiGet("/public/events/{$galleryEvent->slug}/gallery")->assertStatus(200);
    $this->apiGet("/public/wall/{$wallSettings->wall_code}/boot")->assertStatus(200);

    expect(AnalyticsEvent::query()->where('event_id', $hubEvent->id)->where('event_name', 'hub.page_view')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $galleryEvent->id)->where('event_name', 'gallery.page_view')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $wallEvent->id)->where('event_name', 'wall.page_view')->count())->toBe(1);
});

it('tracks public upload views and completed uploads into analytics events', function () {
    Storage::fake('public');
    Bus::fake([GenerateMediaVariantsJob::class]);

    $event = Event::factory()->active()->create();
    enablePublicModule($event, 'live');

    $this->apiGet("/public/events/{$event->upload_slug}/upload")->assertStatus(200);

    $response = $this->postJson("/api/v1/public/events/{$event->upload_slug}/upload", [
        'file' => UploadedFile::fake()->image('photo.jpg'),
        'sender_name' => 'Convidado Teste',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);

    expect(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'upload.page_view')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'upload.completed')->count())->toBe(1);
});

it('tracks public play page views and game views into analytics events', function () {
    $event = Event::factory()->active()->create();
    enablePublicModule($event, 'play');

    EventPlaySetting::query()->create([
        'event_id' => $event->id,
        'is_enabled' => true,
        'ranking_enabled' => true,
    ]);

    $gameType = PlayGameType::factory()->create();
    $game = PlayEventGame::factory()->create([
        'event_id' => $event->id,
        'game_type_id' => $gameType->id,
        'slug' => 'memory-public',
    ]);

    $this->apiGet("/public/events/{$event->slug}/play")->assertStatus(200);
    $this->apiGet("/public/events/{$event->slug}/play/{$game->slug}")->assertStatus(200);

    expect(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'play.page_view')->count())->toBe(1)
        ->and(AnalyticsEvent::query()->where('event_id', $event->id)->where('event_name', 'play.game_view')->count())->toBe(1);
});

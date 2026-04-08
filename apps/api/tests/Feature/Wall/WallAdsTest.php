<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Models\EventWallAd;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    [$this->user, $this->organization] = $this->actingAsManager();

    $this->event = Event::factory()->active()->create([
        'organization_id' => $this->organization->id,
        'title' => 'Evento Ads',
    ]);

    EventModule::query()->create([
        'event_id' => $this->event->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $this->settings = EventWallSetting::factory()->live()->create([
        'event_id' => $this->event->id,
        'ad_mode' => 'by_photos',
        'ad_frequency' => 5,
        'ad_interval_minutes' => 3,
    ]);
});

// ─── WallLayout enum ──────────────────────────────────────────

it('WallLayout enum has 11 cases (auto + 10 layouts)', function () {
    $cases = WallLayout::cases();
    expect(count($cases))->toBe(11);
});

it('WallLayout enum includes carousel mosaic and grid', function () {
    expect(WallLayout::Carousel->value)->toBe('carousel');
    expect(WallLayout::Mosaic->value)->toBe('mosaic');
    expect(WallLayout::Grid->value)->toBe('grid');
});

// ─── Ad CRUD ──────────────────────────────────────────────────

it('lists ads for a wall via GET', function () {
    EventWallAd::factory()->count(3)->create([
        'event_wall_setting_id' => $this->settings->id,
    ]);

    $response = $this->apiGet("/events/{$this->event->id}/wall/ads");

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toHaveCount(3);
});

it('uploads an image ad via POST', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 1920, 1080)->size(500);

    $response = $this->postJson("/api/v1/events/{$this->event->id}/wall/ads", [
        'file' => $file,
        'duration_seconds' => 15,
    ]);

    $response->assertCreated();
    expect($response->json('data.media_type'))->toBe('image');
    expect($response->json('data.duration_seconds'))->toBe(15);

    $this->assertDatabaseHas('event_wall_ads', [
        'event_wall_setting_id' => $this->settings->id,
        'media_type' => 'image',
    ]);
});

it('uploads a video ad via POST', function () {
    // Create a minimal valid MP4 file (ftyp box) so finfo detects video/mp4
    $mp4Header = "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D\x00\x00\x02\x00\x69\x73\x6F\x6D\x69\x73\x6F\x32";
    $tmpPath = tempnam(sys_get_temp_dir(), 'mp4_');
    file_put_contents($tmpPath, $mp4Header . str_repeat("\x00", 1024));

    $file = new UploadedFile($tmpPath, 'promo.mp4', 'video/mp4', null, true);

    $response = $this->postJson("/api/v1/events/{$this->event->id}/wall/ads", [
        'file' => $file,
    ]);

    $response->assertCreated();
    expect($response->json('data.media_type'))->toBe('video');
});


it('rejects upload with invalid mime type', function () {
    $file = UploadedFile::fake()->create('malware.exe', 500, 'application/x-msdownload');

    $response = $this->postJson("/api/v1/events/{$this->event->id}/wall/ads", [
        'file' => $file,
    ]);

    $response->assertUnprocessable();
});

it('rejects upload exceeding 20MB', function () {
    $file = UploadedFile::fake()->image('huge.jpg')->size(21000); // 21MB

    $response = $this->postJson("/api/v1/events/{$this->event->id}/wall/ads", [
        'file' => $file,
    ]);

    $response->assertUnprocessable();
});

it('deletes an ad and removes file from storage', function () {
    $ad = EventWallAd::factory()->create([
        'event_wall_setting_id' => $this->settings->id,
        'file_path' => 'wall/events/' . $this->event->id . '/ads/test.jpg',
    ]);

    // Create a fake file at the path
    Storage::disk('public')->put($ad->file_path, 'fake-content');
    expect(Storage::disk('public')->exists($ad->file_path))->toBeTrue();

    $response = $this->apiDelete("/events/{$this->event->id}/wall/ads/{$ad->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('event_wall_ads', ['id' => $ad->id]);
    expect(Storage::disk('public')->exists($ad->file_path))->toBeFalse();
});

it('reorders ads correctly', function () {
    $ad1 = EventWallAd::factory()->create([
        'event_wall_setting_id' => $this->settings->id,
        'position' => 0,
    ]);
    $ad2 = EventWallAd::factory()->create([
        'event_wall_setting_id' => $this->settings->id,
        'position' => 1,
    ]);
    $ad3 = EventWallAd::factory()->create([
        'event_wall_setting_id' => $this->settings->id,
        'position' => 2,
    ]);

    // Reverse order: 3, 1, 2
    $response = $this->apiPatch("/events/{$this->event->id}/wall/ads/reorder", [
        'order' => [$ad3->id, $ad1->id, $ad2->id],
    ]);

    $response->assertOk();

    expect($ad3->fresh()->position)->toBe(0);
    expect($ad1->fresh()->position)->toBe(1);
    expect($ad2->fresh()->position)->toBe(2);
});

// ─── Boot Payload ─────────────────────────────────────────────

it('includes ads in boot payload when ads exist', function () {
    EventWallAd::factory()->image()->count(2)->create([
        'event_wall_setting_id' => $this->settings->id,
        'is_active' => true,
    ]);

    $response = $this->getJson("/api/v1/public/wall/{$this->settings->wall_code}/boot");

    $response->assertOk();
    expect($response->json('data.ads'))->toHaveCount(2);
    expect($response->json('data.settings.ad_mode'))->toBe('by_photos');
    expect($response->json('data.settings.ad_frequency'))->toBe(5);
});

it('returns empty ads array when no ads exist', function () {
    $response = $this->getJson("/api/v1/public/wall/{$this->settings->wall_code}/boot");

    $response->assertOk();
    expect($response->json('data.ads'))->toBeArray();
    expect($response->json('data.ads'))->toHaveCount(0);
});

it('excludes inactive ads from boot payload', function () {
    EventWallAd::factory()->image()->create([
        'event_wall_setting_id' => $this->settings->id,
        'is_active' => true,
    ]);
    EventWallAd::factory()->image()->inactive()->create([
        'event_wall_setting_id' => $this->settings->id,
    ]);

    $response = $this->getJson("/api/v1/public/wall/{$this->settings->wall_code}/boot");

    $response->assertOk();
    expect($response->json('data.ads'))->toHaveCount(1);
});

// ─── Settings Update ──────────────────────────────────────────

it('updates ad settings via PATCH', function () {
    $response = $this->apiPatch("/events/{$this->event->id}/wall/settings", [
        'ad_mode' => 'by_minutes',
        'ad_frequency' => 10,
        'ad_interval_minutes' => 5,
        'layout' => 'carousel',
    ]);

    $this->assertApiSuccess($response);

    $this->settings->refresh();
    expect($this->settings->ad_mode)->toBe('by_minutes');
    expect($this->settings->ad_frequency)->toBe(10);
    expect($this->settings->ad_interval_minutes)->toBe(5);
    expect($this->settings->layout->value)->toBe('carousel');
});

it('rejects invalid ad_mode value', function () {
    $response = $this->apiPatch("/events/{$this->event->id}/wall/settings", [
        'ad_mode' => 'invalid_mode',
    ]);

    $response->assertUnprocessable();
});

// ─── Authorization ────────────────────────────────────────────

it('forbids ad access for users from another organization', function () {
    $otherOrg = $this->createOrganization();
    $otherEvent = Event::factory()->active()->create([
        'organization_id' => $otherOrg->id,
    ]);
    EventModule::query()->create([
        'event_id' => $otherEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);
    EventWallSetting::factory()->live()->create([
        'event_id' => $otherEvent->id,
    ]);

    $response = $this->apiGet("/events/{$otherEvent->id}/wall/ads");

    $this->assertApiForbidden($response);
});

// ─── Factory ──────────────────────────────────────────────────

it('creates EventWallAd via factory with valid data', function () {
    $ad = EventWallAd::factory()->create([
        'event_wall_setting_id' => $this->settings->id,
    ]);

    expect($ad->exists)->toBeTrue();
    expect($ad->event_wall_setting_id)->toBe($this->settings->id);
    expect($ad->file_path)->toBeString();
    expect($ad->media_type)->toBeIn(['image', 'video']);
    expect($ad->is_active)->toBeTrue();
});

it('EventWallSetting has ads relation', function () {
    EventWallAd::factory()->count(3)->create([
        'event_wall_setting_id' => $this->settings->id,
    ]);

    $ads = $this->settings->ads()->get();
    expect($ads)->toHaveCount(3);
});

it('EventWallSetting activeAds filters inactive', function () {
    EventWallAd::factory()->image()->create([
        'event_wall_setting_id' => $this->settings->id,
        'is_active' => true,
    ]);
    EventWallAd::factory()->image()->inactive()->create([
        'event_wall_setting_id' => $this->settings->id,
    ]);

    $activeAds = $this->settings->activeAds()->get();
    expect($activeAds)->toHaveCount(1);
});

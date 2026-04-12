<?php

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a gallery hero image and persists the asset path inside page schema', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria com hero customizado',
    ]);

    $response = $this->postJson("/api/v1/events/{$event->id}/gallery/hero-image", [
        'file' => UploadedFile::fake()->image('hero.jpg', 1800, 1200),
    ]);

    $this->assertApiSuccess($response);

    $path = $response->json('data.asset.path');

    expect($path)->toStartWith("events/gallery/{$event->id}/hero/");

    Storage::disk('public')->assertExists($path);

    $settings = EventGallerySetting::query()->where('event_id', $event->id)->firstOrFail();

    expect(data_get($settings->page_schema_json, 'blocks.hero.image_path'))->toBe($path);

    $response->assertJsonPath('data.asset.kind', 'hero')
        ->assertJsonPath('data.settings.page_schema.blocks.hero.image_path', $path)
        ->assertJsonPath('data.settings.page_schema.blocks.hero.image_url', fn ($value) => filled($value))
        ->assertJsonPath('data.settings.updated_by', $user->id);
});

it('uploads a gallery banner image and exposes the resolved url in the public preview payload', function () {
    Storage::fake('public');

    [, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Galeria com banner customizado',
    ]);

    $upload = $this->postJson("/api/v1/events/{$event->id}/gallery/banner-image", [
        'file' => UploadedFile::fake()->image('banner.jpg', 2000, 900),
    ]);

    $this->assertApiSuccess($upload);

    $path = $upload->json('data.asset.path');
    $url = $upload->json('data.asset.url');

    expect($path)->toStartWith("events/gallery/{$event->id}/banner/");

    Storage::disk('public')->assertExists($path);

    $previewLink = $this->apiPost("/events/{$event->id}/gallery/preview-link");
    $token = $previewLink->json('data.token');

    $preview = $this->getJson("/api/v1/public/gallery-previews/{$token}");

    $preview->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('experience.page_schema.blocks.banner_strip.enabled', true)
        ->assertJsonPath('experience.page_schema.blocks.banner_strip.image_path', $path)
        ->assertJsonPath('experience.page_schema.blocks.banner_strip.image_url', $url);
});

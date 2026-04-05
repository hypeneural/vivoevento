<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads the hub hero image directly and persists the path', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com hero',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => true]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'wall', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'play', 'is_enabled' => false]);
    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    $response = $this->postJson("/api/v1/events/{$event->id}/hub/hero-image", [
        'file' => UploadedFile::fake()->image('hero.jpg', 1800, 1200),
    ]);

    $this->assertApiSuccess($response);

    $path = $response->json('data.path');

    expect($path)->toStartWith("events/hub/{$event->id}/hero/");
    Storage::disk('public')->assertExists($path);

    $this->assertDatabaseHas('event_hub_settings', [
        'event_id' => $event->id,
        'hero_image_path' => $path,
    ]);
});

it('uploads a sponsor logo for the hub editor and returns a public asset path', function () {
    Storage::fake('public');

    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'title' => 'Evento com patrocinador',
    ]);

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'hub', 'is_enabled' => true]);

    $response = $this->postJson("/api/v1/events/{$event->id}/hub/sponsor-logo", [
        'file' => UploadedFile::fake()->image('logo.png', 900, 900),
    ]);

    $this->assertApiSuccess($response);

    $path = $response->json('data.path');

    expect($path)->toStartWith("events/hub/{$event->id}/sponsors/");
    Storage::disk('public')->assertExists($path);
});

<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('still accepts public upload even when the commercial entitlement disables the public upload channel', function () {
    Storage::fake('public');
    Queue::fake();

    $event = Event::factory()->active()->create([
        'current_entitlements_json' => [
            'channels' => [
                'public_upload' => [
                    'enabled' => false,
                ],
            ],
        ],
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'file' => UploadedFile::fake()->image('channel-disabled.jpg'),
        ]
    );

    $this->assertApiSuccess($response, 201);

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('still reports the public upload page as available even when the event has no active public upload channel configured', function () {
    $event = Event::factory()->active()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.upload.enabled', true)
        ->assertJsonPath('data.upload.status', 'available');
});

it('still accepts public upload even when the resolved commercial photo limit has already been reached', function () {
    Storage::fake('public');
    Queue::fake();

    $event = Event::factory()->active()->create([
        'current_entitlements_json' => [
            'limits' => [
                'max_photos' => 1,
            ],
            'channels' => [
                'public_upload' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'source_type' => 'public_upload',
        'source_label' => 'Convidado',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'file' => UploadedFile::fake()->image('limit-reached.jpg'),
        ]
    );

    $this->assertApiSuccess($response, 201);

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(2);
});

it('still accepts public upload even when the event has no active public upload channel configured', function () {
    Storage::fake('public');
    Queue::fake();

    $event = Event::factory()->active()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'file' => UploadedFile::fake()->image('no-channel-config.jpg'),
        ]
    );

    $this->assertApiSuccess($response, 201);

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(1);
});

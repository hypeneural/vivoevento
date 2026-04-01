<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('returns the public upload payload for an active live event', function () {
    $event = Event::factory()->active()->create([
        'title' => 'Festival da Familia',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.event.title', 'Festival da Familia');
    $response->assertJsonPath('data.upload.enabled', true);
    $response->assertJsonPath('data.upload.status', 'available');
    expect($response->json('data.links.upload_url'))->toContain('/upload/' . $event->upload_slug);
});

it('accepts multiple public image uploads for an active live event', function () {
    Storage::fake('public');
    Queue::fake();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'sender_name' => 'Marina',
            'caption' => 'Memorias da festa',
            'files' => [
                UploadedFile::fake()->image('entrada.jpg'),
                UploadedFile::fake()->image('brinde.png'),
            ],
        ]
    );

    $this->assertApiSuccess($response, 201);

    $response->assertJsonPath('data.uploaded_count', 2);
    $response->assertJsonPath('data.moderation', 'pending');

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(2);

    $storedPaths = EventMedia::query()
        ->where('event_id', $event->id)
        ->pluck('original_filename');

    foreach ($storedPaths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    Queue::assertPushed(GenerateMediaVariantsJob::class, 2);
});

it('shows a closed state and rejects uploads when the event is not available', function () {
    $event = Event::factory()->draft()->create();

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $payloadResponse = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($payloadResponse);
    $payloadResponse->assertJsonPath('data.upload.enabled', false);
    $payloadResponse->assertJsonPath('data.upload.status', 'inactive');

    $uploadResponse = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'file' => UploadedFile::fake()->image('fechado.jpg'),
        ]
    );

    $this->assertApiError($uploadResponse, 422);
    $uploadResponse->assertJsonPath('message', 'O envio de imagens esta temporariamente indisponivel.');
});

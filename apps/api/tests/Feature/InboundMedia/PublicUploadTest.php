<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function fakeVideoToolingReady(): array
{
    $ffmpegPath = tempnam(sys_get_temp_dir(), 'ffmpeg_');
    $ffprobePath = tempnam(sys_get_temp_dir(), 'ffprobe_');

    config()->set('media_processing.ffmpeg_binary', $ffmpegPath);
    config()->set('media_processing.ffprobe_binary', $ffprobePath);

    return [$ffmpegPath, $ffprobePath];
}

function cleanupVideoTooling(array $paths): void
{
    foreach ($paths as $path) {
        @unlink($path);
    }
}

function enableWallVideoForPublicUpload(Event $event, array $overrides = []): void
{
    EventWallSetting::factory()->live()->create(array_merge([
        'event_id' => $event->id,
        'video_enabled' => true,
        'public_upload_video_enabled' => true,
        'video_max_seconds' => 30,
        'video_preferred_variant' => 'wall_video_720p',
    ], $overrides));
}

it('returns the public upload payload for an active live event', function () {
    $tooling = fakeVideoToolingReady();

    $event = Event::factory()->active()->create([
        'title' => 'Festival da Familia',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event);

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.event.title', 'Festival da Familia');
    $response->assertJsonPath('data.upload.enabled', true);
    $response->assertJsonPath('data.upload.status', 'available');
    $response->assertJsonPath('data.upload.accepts_video', true);
    $response->assertJsonPath('data.upload.video_single_only', true);
    $response->assertJsonPath('data.upload.video_max_duration_seconds', 30);
    $response->assertJsonPath('data.upload.accept_hint', 'image/*,video/mp4,video/quicktime');
    expect($response->json('data.links.upload_url'))->toContain('/upload/' . $event->upload_slug);

    cleanupVideoTooling($tooling);
});

it('returns moderation guidance for each supported event mode in the upload payload', function (string $mode, string $expectedInstruction) {
    $tooling = fakeVideoToolingReady();

    $event = Event::factory()->active()->create([
        'title' => 'Festival da Familia',
        'moderation_mode' => $mode,
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event);

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.upload.moderation_mode', $mode);
    $response->assertJsonPath('data.upload.instructions', $expectedInstruction);

    cleanupVideoTooling($tooling);
})->with([
    ['none', 'As fotos e os videos curtos de ate 30s entram no ar automaticamente apos o processamento base.'],
    ['manual', 'As fotos e os videos curtos de ate 30s passam por moderacao manual antes de aparecer no evento.'],
    ['ai', 'As fotos e os videos curtos de ate 30s passam por moderacao por IA antes de aparecer no evento.'],
]);

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
        ->pluck('original_path');

    foreach ($storedPaths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    $storedMedia = EventMedia::query()
        ->where('event_id', $event->id)
        ->orderBy('id')
        ->get();

    expect($storedMedia->every(fn (EventMedia $media) => $media->original_disk === 'public'))->toBeTrue()
        ->and($storedMedia->every(fn (EventMedia $media) => $media->client_filename !== null))->toBeTrue()
        ->and($storedMedia->every(fn (EventMedia $media) => $media->moderation_status->value === 'pending'))->toBeTrue()
        ->and($storedMedia->every(fn (EventMedia $media) => $media->face_index_status === 'skipped'))->toBeTrue();

    Queue::assertPushed(GenerateMediaVariantsJob::class, 2);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->queue === 'media-variants');
});

it('accepts a single public video upload for an active live event', function () {
    Storage::fake('public');
    Queue::fake();
    $tooling = fakeVideoToolingReady();
    Process::fake([
        '*' => Process::result(json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'width' => 1280,
                    'height' => 720,
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                ],
            ],
            'format' => [
                'duration' => '17.2',
                'bit_rate' => '850000',
                'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
            ],
        ], JSON_THROW_ON_ERROR), '', 0),
    ]);

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event);

    $mp4Header = "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D\x00\x00\x02\x00\x69\x73\x6F\x6D\x69\x73\x6F\x32";
    $tmpPath = tempnam(sys_get_temp_dir(), 'public_mp4_');
    file_put_contents($tmpPath, $mp4Header . str_repeat("\x00", 1024));

    $file = new UploadedFile($tmpPath, 'entrada.mp4', 'video/mp4', null, true);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'sender_name' => 'Marina',
            'caption' => 'Video da pista',
            'file' => $file,
        ]
    );

    $this->assertApiSuccess($response, 201);
    $response->assertJsonPath('data.uploaded_count', 1);
    $response->assertJsonPath('data.message', 'Video recebido com sucesso!');
    $response->assertJsonPath('data.moderation', 'pending');

    $media = EventMedia::query()->where('event_id', $event->id)->latest('id')->first();

    expect($media)->not->toBeNull()
        ->and($media?->media_type)->toBe('video')
        ->and($media?->mime_type)->toBe('video/mp4')
        ->and($media?->original_disk)->toBe('public')
        ->and($media?->client_filename)->toBe('entrada.mp4')
        ->and($media?->duration_seconds)->toBe(18)
        ->and($media?->width)->toBe(1280)
        ->and($media?->height)->toBe(720)
        ->and($media?->has_audio)->toBeTrue()
        ->and($media?->video_codec)->toBe('h264')
        ->and($media?->audio_codec)->toBe('aac')
        ->and($media?->bitrate)->toBe(850000)
        ->and($media?->container)->toBe('mp4');

    Storage::disk('public')->assertExists($media->original_path);

    Queue::assertPushed(GenerateMediaVariantsJob::class, 1);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->queue === 'media-variants');
    Process::assertRanTimes(
        fn ($process, $result) => $process->command[0] === config('media_processing.ffprobe_binary') && $result->successful(),
        times: 1,
    );

    @unlink($tmpPath);
    cleanupVideoTooling($tooling);
});

it('rejects public video uploads that exceed the configured duration limit', function () {
    Storage::fake('public');
    Queue::fake();
    $tooling = fakeVideoToolingReady();
    Process::fake([
        '*' => Process::result(json_encode([
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'width' => 1280,
                    'height' => 720,
                ],
            ],
            'format' => [
                'duration' => '45.2',
                'bit_rate' => '950000',
                'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
            ],
        ], JSON_THROW_ON_ERROR), '', 0),
    ]);

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event);

    $tmpPath = tempnam(sys_get_temp_dir(), 'public_long_mp4_');
    file_put_contents($tmpPath, "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D" . str_repeat("\x00", 1024));

    $file = new UploadedFile($tmpPath, 'longo.mp4', 'video/mp4', null, true);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'sender_name' => 'Marina',
            'file' => $file,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonPath('errors.file.0', 'Envie um video curto de ate 30 segundos.');

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(0);
    Queue::assertNothingPushed();

    @unlink($tmpPath);
    cleanupVideoTooling($tooling);
});

it('rejects public video uploads when video support is disabled by policy', function () {
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
    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
        'video_enabled' => false,
    ]);

    $tmpPath = tempnam(sys_get_temp_dir(), 'public_disabled_mp4_');
    file_put_contents($tmpPath, "\x00\x00\x00\x18\x66\x74\x79\x70\x69\x73\x6F\x6D" . str_repeat("\x00", 1024));

    $file = new UploadedFile($tmpPath, 'bloqueado.mp4', 'video/mp4', null, true);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post(
        "/api/v1/public/events/{$event->upload_slug}/upload",
        [
            'sender_name' => 'Marina',
            'file' => $file,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonPath('errors.file.0', 'O envio de video ainda nao esta habilitado para este evento.');

    expect(EventMedia::query()->where('event_id', $event->id)->count())->toBe(0);
    Queue::assertNothingPushed();

    @unlink($tmpPath);
});

it('hides public video support when rollout publico is disabled for this wall', function () {
    $tooling = fakeVideoToolingReady();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event, [
        'public_upload_video_enabled' => false,
    ]);

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);
    $response
        ->assertJsonPath('data.upload.accepts_video', false)
        ->assertJsonPath('data.upload.accept_hint', 'image/*')
        ->assertJsonPath('data.upload.instructions', 'As fotos enviadas passam por moderacao manual antes de aparecer no evento.');

    cleanupVideoTooling($tooling);
});

it('hides public video support when the wall policy allows video but ffmpeg tooling is unavailable', function () {
    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    EventModule::create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);
    enableWallVideoForPublicUpload($event);

    config()->set('media_processing.ffmpeg_binary', 'C:\\missing\\ffmpeg.exe');
    config()->set('media_processing.ffprobe_binary', 'C:\\missing\\ffprobe.exe');

    $response = $this->apiGet("/public/events/{$event->upload_slug}/upload");

    $this->assertApiSuccess($response);
    $response
        ->assertJsonPath('data.upload.accepts_video', false)
        ->assertJsonPath('data.upload.accept_hint', 'image/*')
        ->assertJsonPath('data.upload.instructions', 'As fotos enviadas passam por moderacao manual antes de aparecer no evento.');
});

it('marks uploaded media with queued face indexing when face search is enabled', function () {
    Storage::fake('public');
    Queue::fake();

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
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
            'files' => [
                UploadedFile::fake()->image('entrada.jpg'),
            ],
        ]
    );

    $this->assertApiSuccess($response, 201);

    $media = EventMedia::query()->where('event_id', $event->id)->latest('id')->first();

    expect($media)->not->toBeNull()
        ->and($media?->face_index_status)->toBe('queued');

    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->queue === 'media-variants');
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

    $uploadResponse->assertStatus(422);
    $uploadResponse->assertJsonPath('message', 'O envio de imagens esta temporariamente indisponivel.');
});

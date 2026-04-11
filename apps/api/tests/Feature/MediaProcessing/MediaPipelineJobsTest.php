<?php

use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use App\Modules\MediaProcessing\Services\MediaVariantGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('publish media job publishes approved media and emits media published', function () {
    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $domainEvent->id,
        'publication_status' => PublicationStatus::Draft->value,
        'published_at' => null,
    ]);

    EventFacade::fake([MediaPublished::class]);

    app(PublishMediaJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->publication_status)->toBe(PublicationStatus::Published)
        ->and($media->published_at)->not->toBeNull();

    EventFacade::assertDispatched(MediaPublished::class, fn (MediaPublished $event) => $event->eventMediaId === $media->id);
});

it('generate variants job marks media processed emits variants event and queues safety analysis', function () {
    Queue::fake();
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    $image = UploadedFile::fake()->image('entrada.jpg', 1800, 1200);
    $imageBinary = file_get_contents($image->getPathname()) ?: '';
    $path = "events/{$domainEvent->id}/originals/entrada.jpg";
    Storage::disk('public')->put($path, $imageBinary);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'original_filename' => 'entrada.jpg',
        'client_filename' => 'entrada.jpg',
        'original_disk' => 'public',
        'original_path' => $path,
        'processing_status' => MediaProcessingStatus::Downloaded->value,
    ]);

    app(GenerateMediaVariantsJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->processing_status)->toBe(MediaProcessingStatus::Processed);
    expect($media->perceptual_hash)->not->toBeNull()
        ->and(strlen((string) $media->perceptual_hash))->toBe(16)
        ->and($media->duplicate_group_key)->toBeNull();
    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->pluck('variant_key')
            ->sort()
            ->values()
            ->all()
    )->toBe(['fast_preview', 'gallery', 'moderation_preview', 'moderation_thumb', 'thumb', 'wall']);

    $variantsRun = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'variants')
        ->latest('id')
        ->first();

    expect($variantsRun)->not->toBeNull()
        ->and($variantsRun?->status)->toBe('completed')
        ->and($variantsRun?->decision_key)->toBe('generated')
        ->and($variantsRun?->queue_name)->toBe('media-variants')
        ->and($variantsRun?->worker_ref)->not->toBe('')
        ->and($variantsRun?->result_json['generated_count'] ?? null)->toBe(6)
        ->and($variantsRun?->result_json['perceptual_hash'] ?? null)->not->toBeNull()
        ->and($variantsRun?->result_json['fingerprint_status'] ?? null)->toBe('indexed');

    Queue::assertPushed(AnalyzeContentSafetyJob::class, fn (AnalyzeContentSafetyJob $job) => $job->eventMediaId === $media->id);
});

it('groups visually identical images under the same duplicate key', function () {
    Queue::fake();

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    $firstPath = "events/{$domainEvent->id}/originals/duplicada.jpg";
    $generator = \Mockery::mock(MediaVariantGeneratorService::class);
    $generator->shouldReceive('generate')
        ->twice()
        ->andReturn([
            'generated_count' => 6,
            'variant_keys' => ['fast_preview', 'thumb', 'moderation_thumb', 'moderation_preview', 'gallery', 'wall'],
            'source_width' => 1800,
            'source_height' => 1200,
            'perceptual_hash' => '0f0f0f0f0f0f0f0f',
        ]);
    app()->instance(MediaVariantGeneratorService::class, $generator);

    $firstMedia = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'original_filename' => 'duplicada.jpg',
        'client_filename' => 'duplicada.jpg',
        'original_disk' => 'public',
        'original_path' => $firstPath,
        'processing_status' => MediaProcessingStatus::Downloaded->value,
    ]);

    $secondMedia = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'original_filename' => 'duplicada-copia.jpg',
        'client_filename' => 'duplicada-copia.jpg',
        'original_disk' => 'public',
        'original_path' => "events/{$domainEvent->id}/originals/duplicada-copia.jpg",
        'processing_status' => MediaProcessingStatus::Downloaded->value,
    ]);

    app(GenerateMediaVariantsJob::class, ['eventMediaId' => $firstMedia->id])->handle();
    app(GenerateMediaVariantsJob::class, ['eventMediaId' => $secondMedia->id])->handle();

    $firstMedia->refresh();
    $secondMedia->refresh();

    expect($firstMedia->perceptual_hash)->not->toBeNull()
        ->and($secondMedia->perceptual_hash)->toBe($firstMedia->perceptual_hash)
        ->and($firstMedia->duplicate_group_key)->not->toBeNull()
        ->and($secondMedia->duplicate_group_key)->toBe($firstMedia->duplicate_group_key);

    $variantsRun = MediaProcessingRun::query()
        ->where('event_media_id', $secondMedia->id)
        ->where('stage_key', 'variants')
        ->latest('id')
        ->first();

    expect($variantsRun?->result_json['fingerprint_status'] ?? null)->toBe('grouped')
        ->and($variantsRun?->result_json['matched_media_id'] ?? null)->toBe($firstMedia->id)
        ->and($variantsRun?->result_json['match_type'] ?? null)->toBe('exact');
});

it('generate variants job transcodes wall video variants and poster for video media', function () {
    Queue::fake();
    Storage::fake('public');
    $ffmpegBinary = (string) config('media_processing.ffmpeg_binary', 'ffmpeg');
    $ffprobeBinary = (string) config('media_processing.ffprobe_binary', 'ffprobe');

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    $path = "events/{$domainEvent->id}/originals/video-pista.mp4";
    $originalAbsolutePath = Storage::disk('public')->path($path);
    if (! is_dir(dirname($originalAbsolutePath))) {
        mkdir(dirname($originalAbsolutePath), 0777, true);
    }
    file_put_contents($originalAbsolutePath, 'fake-video-binary');

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'video-pista.mp4',
        'client_filename' => 'video-pista.mp4',
        'original_disk' => 'public',
        'original_path' => $path,
        'processing_status' => MediaProcessingStatus::Downloaded->value,
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 18,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 850000,
        'container' => 'mp4',
    ]);

    $poster = UploadedFile::fake()->image('poster.jpg', 1280, 720);
    $posterBinary = file_get_contents($poster->getPathname()) ?: '';
    $videoVariantPath = Storage::disk('public')->path("events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4");
    $posterVariantPath = Storage::disk('public')->path("events/{$domainEvent->id}/variants/{$media->id}/wall_video_poster.jpg");
    if (! is_dir(dirname($videoVariantPath))) {
        mkdir(dirname($videoVariantPath), 0777, true);
    }
    file_put_contents($videoVariantPath, 'fake-wall-video-binary');
    file_put_contents($posterVariantPath, $posterBinary);

    Process::fake(function ($process) use ($ffmpegBinary, $ffprobeBinary) {
        $command = $process->command;
        $binary = $command[0] ?? null;

        if ($binary === $ffmpegBinary) {
            return Process::result('', '', 0);
        }

        if ($binary === $ffprobeBinary) {
            return Process::result(json_encode([
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
                    'duration' => '18',
                    'bit_rate' => '850000',
                    'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
                ],
            ], JSON_THROW_ON_ERROR), '', 0);
        }

        return Process::result('', 'unexpected process', 1);
    });

    app(GenerateMediaVariantsJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->processing_status)->toBe(MediaProcessingStatus::Processed);
    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->pluck('variant_key')
            ->sort()
            ->values()
            ->all()
    )->toBe(['wall_video_720p', 'wall_video_poster']);

    Storage::disk('public')->assertExists("events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4");
    Storage::disk('public')->assertExists("events/{$domainEvent->id}/variants/{$media->id}/wall_video_poster.jpg");

    $variantsRun = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'variants')
        ->latest('id')
        ->first();

    expect($variantsRun)->not->toBeNull()
        ->and($variantsRun?->provider_key)->toBe('ffmpeg')
        ->and($variantsRun?->model_key)->toBe('ffmpeg-h264-aac-faststart')
        ->and($variantsRun?->status)->toBe('completed')
        ->and($variantsRun?->decision_key)->toBe('generated')
        ->and($variantsRun?->result_json['generated_count'] ?? null)->toBe(2)
        ->and($variantsRun?->result_json['variant_keys'] ?? null)->toBe(['wall_video_720p', 'wall_video_poster'])
        ->and($variantsRun?->result_json['perceptual_hash'] ?? null)->toBeNull()
        ->and($variantsRun?->result_json['fingerprint_status'] ?? null)->toBe('skipped');

    Queue::assertPushed(AnalyzeContentSafetyJob::class, fn (AnalyzeContentSafetyJob $job) => $job->eventMediaId === $media->id);
    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffmpegBinary && $result->successful(), 2);
    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffprobeBinary && $result->successful(), 1);
});

it('run moderation job approves media and queues publication for no moderation events', function () {
    Queue::fake();

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Pending->value,
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($media->safety_status)->toBe('skipped')
        ->and($media->vlm_status)->toBe('skipped')
        ->and($media->face_index_status)->toBe('skipped');

    $run = MediaProcessingRun::query()
        ->where('event_media_id', $media->id)
        ->where('stage_key', 'moderation')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->decision_key)->toBe('approved')
        ->and($run?->queue_name)->toBe('media-audit');

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
});

it('run moderation job keeps face indexing queued when face search is enabled for the event', function () {
    Queue::fake();

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Pending->value,
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($media->face_index_status)->toBe('queued');

    Queue::assertPushed(PublishMediaJob::class, fn (PublishMediaJob $job) => $job->eventMediaId === $media->id);
    Queue::assertPushed(IndexMediaFacesJob::class, fn (IndexMediaFacesJob $job) => $job->eventMediaId === $media->id);
});

it('run moderation job keeps media pending for manual moderation events', function () {
    Queue::fake();

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'manual',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Pending->value,
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($media->safety_status)->toBe('skipped')
        ->and($media->vlm_status)->toBe('skipped');

    Queue::assertNotPushed(PublishMediaJob::class);
});

it('run moderation job keeps media pending for ai moderation events when safety has not passed yet', function () {
    Queue::fake();

    $domainEvent = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $domainEvent->id,
        'moderation_status' => ModerationStatus::Pending->value,
        'safety_status' => 'queued',
        'vlm_status' => 'skipped',
    ]);

    app(RunModerationJob::class, ['eventMediaId' => $media->id])->handle();

    $media->refresh();

    expect($media->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($media->safety_status)->toBe('queued');

    Queue::assertNotPushed(PublishMediaJob::class);
});

it('exposes horizon tags for media pipeline jobs', function () {
    expect((new GenerateMediaVariantsJob(101))->tags())->toBe([
        'queue:media-variants',
        'pipeline:variants',
        'event_media:101',
    ]);

    expect((new RunModerationJob(202))->tags())->toBe([
        'queue:media-audit',
        'pipeline:moderation',
        'event_media:202',
    ]);

    expect((new AnalyzeContentSafetyJob(252))->tags())->toBe([
        'queue:media-safety',
        'pipeline:safety',
        'event_media:252',
    ]);

    expect((new EvaluateMediaPromptJob(282))->tags())->toBe([
        'queue:media-vlm',
        'pipeline:vlm',
        'event_media:282',
    ]);

    expect((new IndexMediaFacesJob(292))->tags())->toBe([
        'queue:face-index',
        'pipeline:face_index',
        'event_media:292',
    ]);

    expect((new PublishMediaJob(303))->tags())->toBe([
        'queue:media-publish',
        'pipeline:publish',
        'event_media:303',
    ]);
});

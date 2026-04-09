<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Queue;

it('queues backfill only for legacy videos that are missing wall variants or metadata', function () {
    Queue::fake();

    $ffmpegPath = tempnam(sys_get_temp_dir(), 'ffmpeg_');
    $ffprobePath = tempnam(sys_get_temp_dir(), 'ffprobe_');
    config()->set('media_processing.ffmpeg_binary', $ffmpegPath);
    config()->set('media_processing.ffprobe_binary', $ffprobePath);

    $event = Event::factory()->active()->create();

    $legacyVideo = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/legacy.mp4",
        'width' => null,
        'height' => null,
        'duration_seconds' => null,
    ]);

    $readyVideo = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/ready.mp4",
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 18,
    ]);
    $readyVideo->variants()->create([
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$readyVideo->id}/wall_video_720p.mp4",
        'mime_type' => 'video/mp4',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 1200000,
    ]);
    $readyVideo->variants()->create([
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$event->id}/variants/{$readyVideo->id}/wall_video_poster.webp",
        'mime_type' => 'image/webp',
        'width' => 720,
        'height' => 1280,
        'size_bytes' => 120000,
    ]);

    $this->artisan('media:backfill-wall-video-variants', [
        '--event_id' => $event->id,
        '--limit' => 10,
    ])->assertExitCode(0);

    Queue::assertPushed(GenerateMediaVariantsJob::class, 1);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->eventMediaId === $legacyVideo->id);

    @unlink($ffmpegPath);
    @unlink($ffprobePath);
});

it('refuses to queue legacy video backfill when ffmpeg or ffprobe are unavailable', function () {
    Queue::fake();

    config()->set('media_processing.ffmpeg_binary', 'C:\\missing\\ffmpeg.exe');
    config()->set('media_processing.ffprobe_binary', 'C:\\missing\\ffprobe.exe');

    $this->artisan('media:backfill-wall-video-variants', [
        '--limit' => 5,
    ])->assertExitCode(1);

    Queue::assertNothingPushed();
});

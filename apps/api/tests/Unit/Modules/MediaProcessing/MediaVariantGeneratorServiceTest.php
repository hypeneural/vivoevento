<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Services\MediaVariantGeneratorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

function fakeVideoPosterBinary(): string
{
    $poster = UploadedFile::fake()->image('poster.jpg', 1280, 720);

    return file_get_contents($poster->getPathname()) ?: '';
}

function fakeVideoProbePayload(int $width, int $height, int $durationSeconds = 18, int $bitrate = 850000): string
{
    return json_encode([
        'streams' => [
            [
                'codec_type' => 'video',
                'codec_name' => 'h264',
                'width' => $width,
                'height' => $height,
            ],
            [
                'codec_type' => 'audio',
                'codec_name' => 'aac',
            ],
        ],
        'format' => [
            'duration' => (string) $durationSeconds,
            'bit_rate' => (string) $bitrate,
            'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
        ],
    ], JSON_THROW_ON_ERROR);
}

function seedPublicFakeDiskFile(string $relativePath, string $contents): void
{
    $absolutePath = Storage::disk('public')->path($relativePath);
    $directory = dirname($absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($absolutePath, $contents);
}

it('generates wall video variants and poster for a large source video', function () {
    Storage::fake('public');
    $ffmpegBinary = (string) config('media_processing.ffmpeg_binary', 'ffmpeg');
    $ffprobeBinary = (string) config('media_processing.ffprobe_binary', 'ffprobe');

    $event = Event::factory()->active()->create();
    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/original-video.mp4",
        'original_filename' => 'original-video.mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 18,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 1_100_000,
        'container' => 'mp4',
    ]);

    seedPublicFakeDiskFile($media->original_path, 'fake-video-binary');
    $posterBinary = fakeVideoPosterBinary();
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_720p.mp4", 'fake-wall-video-binary');
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_1080p.mp4", 'fake-wall-video-binary');
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_poster.jpg", $posterBinary);

    Process::fake(function ($process) use ($ffmpegBinary, $ffprobeBinary) {
        $command = $process->command;
        $binary = $command[0] ?? null;
        $outputPath = $command[array_key_last($command)] ?? null;

        if ($binary === $ffmpegBinary && is_string($outputPath)) {
            return Process::result('', '', 0);
        }

        if ($binary === $ffprobeBinary && is_string($outputPath)) {
            if (str_contains($outputPath, 'wall_video_1080p.mp4')) {
                return Process::result(fakeVideoProbePayload(1920, 1080, 18, 1_000_000), '', 0);
            }

            if (str_contains($outputPath, 'wall_video_720p.mp4')) {
                return Process::result(fakeVideoProbePayload(1280, 720, 18, 820000), '', 0);
            }

            return Process::result(fakeVideoProbePayload(1920, 1080, 18, 1_100_000), '', 0);
        }

        return Process::result('', 'unexpected process', 1);
    });

    $summary = app(MediaVariantGeneratorService::class)->generate($media);

    expect($summary['generated_count'])->toBe(3)
        ->and($summary['variant_keys'])->toBe(['wall_video_720p', 'wall_video_1080p', 'wall_video_poster'])
        ->and($summary['source_width'])->toBe(1920)
        ->and($summary['source_height'])->toBe(1080)
        ->and($summary['perceptual_hash'])->toBeNull();

    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->pluck('variant_key')
            ->sort()
            ->values()
            ->all()
    )->toBe(['wall_video_1080p', 'wall_video_720p', 'wall_video_poster']);

    $posterVariant = EventMediaVariant::query()
        ->where('event_media_id', $media->id)
        ->where('variant_key', 'wall_video_poster')
        ->first();

    expect($posterVariant)->not->toBeNull()
        ->and($posterVariant?->mime_type)->toBe('image/jpeg')
        ->and($posterVariant?->width)->not->toBeNull()
        ->and($posterVariant?->height)->not->toBeNull();

    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffmpegBinary && $result->successful(), 3);
    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffprobeBinary && $result->successful(), 2);
});

it('generates only 720p and poster for smaller source videos', function () {
    Storage::fake('public');
    $ffmpegBinary = (string) config('media_processing.ffmpeg_binary', 'ffmpeg');
    $ffprobeBinary = (string) config('media_processing.ffprobe_binary', 'ffprobe');

    $event = Event::factory()->active()->create();
    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/compact-video.mp4",
        'original_filename' => 'compact-video.mp4',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 14,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 780000,
        'container' => 'mp4',
    ]);

    seedPublicFakeDiskFile($media->original_path, 'fake-video-binary');
    $posterBinary = fakeVideoPosterBinary();
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_720p.mp4", 'fake-wall-video-binary');
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_poster.jpg", $posterBinary);

    Process::fake(function ($process) use ($ffmpegBinary, $ffprobeBinary) {
        $command = $process->command;
        $binary = $command[0] ?? null;

        if ($binary === $ffmpegBinary) {
            return Process::result('', '', 0);
        }

        if ($binary === $ffprobeBinary) {
            return Process::result(fakeVideoProbePayload(1280, 720, 14, 780000), '', 0);
        }

        return Process::result('', 'unexpected process', 1);
    });

    $summary = app(MediaVariantGeneratorService::class)->generate($media);

    expect($summary['generated_count'])->toBe(2)
        ->and($summary['variant_keys'])->toBe(['wall_video_720p', 'wall_video_poster']);

    expect(
        EventMediaVariant::query()
            ->where('event_media_id', $media->id)
            ->pluck('variant_key')
            ->sort()
            ->values()
            ->all()
    )->toBe(['wall_video_720p', 'wall_video_poster']);

    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffmpegBinary && $result->successful(), 2);
    Process::assertRanTimes(fn ($process, $result) => ($process->command[0] ?? null) === $ffprobeBinary && $result->successful(), 1);
});

it('uses the configured ffmpeg binary path when generating wall video variants', function () {
    config()->set('media_processing.ffmpeg_binary', 'C:\\tools\\ffmpeg.exe');

    Storage::fake('public');

    $event = Event::factory()->active()->create();
    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_disk' => 'public',
        'original_path' => "events/{$event->id}/originals/configured-video.mp4",
        'original_filename' => 'configured-video.mp4',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 14,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 780000,
        'container' => 'mp4',
    ]);

    seedPublicFakeDiskFile($media->original_path, 'fake-video-binary');
    $posterBinary = fakeVideoPosterBinary();
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_720p.mp4", 'fake-wall-video-binary');
    seedPublicFakeDiskFile("events/{$event->id}/variants/{$media->id}/wall_video_poster.jpg", $posterBinary);

    Process::fake(function ($process) {
        $binary = $process->command[0] ?? null;

        if ($binary === 'C:\\tools\\ffmpeg.exe') {
            return Process::result('', '', 0);
        }

        if ($binary === 'ffprobe') {
            return Process::result(fakeVideoProbePayload(1280, 720, 14, 780000), '', 0);
        }

        return Process::result('', 'unexpected process', 1);
    });

    app(MediaVariantGeneratorService::class)->generate($media);

    Process::assertRan(function ($process, $result) {
        return ($process->command[0] ?? null) === 'C:\\tools\\ffmpeg.exe'
            && $result->successful();
    });
});

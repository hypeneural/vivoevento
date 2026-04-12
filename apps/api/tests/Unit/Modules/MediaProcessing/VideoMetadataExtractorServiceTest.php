<?php

use App\Modules\MediaProcessing\Services\VideoMetadataExtractorService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

it('uses complete canonical hints without spawning ffprobe', function () {
    Process::fake();

    $metadata = app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
        disk: 'public',
        path: 'events/10/originals/video.mp4',
        mimeType: 'video/mp4',
        hints: [
            'media' => [
                'width' => 1080,
                'height' => 1920,
                'duration' => 27,
                'has_audio' => true,
                'video_codec' => 'h264',
                'audio_codec' => 'aac',
                'bitrate' => 1_200_000,
                'container' => 'mp4',
            ],
        ],
    );

    expect($metadata)->toMatchArray([
        'width' => 1080,
        'height' => 1920,
        'duration_seconds' => 27,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 1_200_000,
        'container' => 'mp4',
    ]);

    Process::assertNothingRan();
});

it('extracts video metadata from ffprobe output when hints are incomplete', function () {
    Storage::fake('public');
    Storage::disk('public')->put('events/10/originals/video.mp4', 'fake-video-binary');

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
                'duration' => '14.4',
                'bit_rate' => '950000',
                'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
            ],
        ], JSON_THROW_ON_ERROR), '', 0),
    ]);

    $metadata = app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
        disk: 'public',
        path: 'events/10/originals/video.mp4',
        mimeType: 'video/mp4',
    );

    expect($metadata)->toMatchArray([
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 15,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 950000,
        'container' => 'mp4',
    ]);

    Process::assertRan(function ($process, $result) {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        return str_contains($command, 'ffprobe')
            && str_contains($command, '-show_streams')
            && str_contains($command, '-show_format')
            && $result->successful();
    });
});

it('falls back to mime-derived container when ffprobe is unavailable', function () {
    Storage::fake('public');
    Storage::disk('public')->put('events/10/originals/video.mov', 'fake-video-binary');

    Process::fake([
        '*' => Process::result('', 'ffprobe not found', 1),
    ]);

    $metadata = app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
        disk: 'public',
        path: 'events/10/originals/video.mov',
        mimeType: 'video/quicktime',
    );

    expect($metadata)->toMatchArray([
        'width' => null,
        'height' => null,
        'duration_seconds' => null,
        'has_audio' => null,
        'video_codec' => null,
        'audio_codec' => null,
        'bitrate' => null,
        'container' => 'mov',
    ]);
});

it('uses the configured ffprobe binary path when probing video metadata', function () {
    config()->set('media_processing.ffprobe_binary', 'C:\\tools\\ffprobe.exe');

    Storage::fake('public');
    Storage::disk('public')->put('events/10/originals/video.mp4', 'fake-video-binary');

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
                'duration' => '14.4',
                'bit_rate' => '950000',
                'format_name' => 'mov,mp4,m4a,3gp,3g2,mj2',
            ],
        ], JSON_THROW_ON_ERROR), '', 0),
    ]);

    app(VideoMetadataExtractorService::class)->extractFromStoredAsset(
        disk: 'public',
        path: 'events/10/originals/video.mp4',
        mimeType: 'video/mp4',
    );

    Process::assertRan(function ($process, $result) {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        return str_contains($command, 'C:\\tools\\ffprobe.exe')
            && $result->successful();
    });
});

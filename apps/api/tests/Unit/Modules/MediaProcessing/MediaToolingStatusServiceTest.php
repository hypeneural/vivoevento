<?php

use App\Modules\MediaProcessing\Services\MediaToolingStatusService;

it('reports ready when ffmpeg and ffprobe are configured as real paths', function () {
    $ffmpegPath = tempnam(sys_get_temp_dir(), 'ffmpeg_');
    $ffprobePath = tempnam(sys_get_temp_dir(), 'ffprobe_');

    config()->set('media_processing.ffmpeg_binary', $ffmpegPath);
    config()->set('media_processing.ffprobe_binary', $ffprobePath);

    $payload = app(MediaToolingStatusService::class)->payload();

    expect($payload['ready'])->toBeTrue()
        ->and($payload['ffmpeg_available'])->toBeTrue()
        ->and($payload['ffprobe_available'])->toBeTrue()
        ->and($payload['ffmpeg_resolved_path'])->toBe($ffmpegPath)
        ->and($payload['ffprobe_resolved_path'])->toBe($ffprobePath);

    @unlink($ffmpegPath);
    @unlink($ffprobePath);
});

it('reports not ready when configured binaries do not exist', function () {
    config()->set('media_processing.ffmpeg_binary', 'C:\\missing\\ffmpeg.exe');
    config()->set('media_processing.ffprobe_binary', 'C:\\missing\\ffprobe.exe');

    $payload = app(MediaToolingStatusService::class)->payload();

    expect($payload['ready'])->toBeFalse()
        ->and($payload['ffmpeg_available'])->toBeFalse()
        ->and($payload['ffprobe_available'])->toBeFalse()
        ->and($payload['ffmpeg_resolved_path'])->toBeNull()
        ->and($payload['ffprobe_resolved_path'])->toBeNull();
});

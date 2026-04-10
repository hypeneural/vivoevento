<?php

it('reports ready tooling through the artisan command', function () {
    $ffmpegPath = tempnam(sys_get_temp_dir(), 'ffmpeg_');
    $ffprobePath = tempnam(sys_get_temp_dir(), 'ffprobe_');

    config()->set('media_processing.ffmpeg_binary', $ffmpegPath);
    config()->set('media_processing.ffprobe_binary', $ffprobePath);

    $this->artisan('media:tooling-status')
        ->expectsOutput("ffmpeg: {$ffmpegPath}")
        ->expectsOutput("ffprobe: {$ffprobePath}")
        ->expectsOutput('Status: ready')
        ->assertExitCode(0);

    @unlink($ffmpegPath);
    @unlink($ffprobePath);
});

it('returns failure when tooling is not ready', function () {
    config()->set('media_processing.ffmpeg_binary', 'C:\\missing\\ffmpeg.exe');
    config()->set('media_processing.ffprobe_binary', 'C:\\missing\\ffprobe.exe');

    $this->artisan('media:tooling-status')
        ->expectsOutput('ffmpeg: C:\\missing\\ffmpeg.exe')
        ->expectsOutput('ffprobe: C:\\missing\\ffprobe.exe')
        ->expectsOutput('Status: not_ready')
        ->assertExitCode(1);
});

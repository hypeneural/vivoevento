<?php

it('provisions ffmpeg on ubuntu hosts and verifies tooling readiness before wall video rollout', function () {
    $repoRoot = dirname(dirname(base_path()));
    $bootstrapHost = file_get_contents($repoRoot.'/scripts/ops/bootstrap-host.sh');
    $verifyHost = file_get_contents($repoRoot.'/scripts/ops/verify-host.sh');

    expect($bootstrapHost)->toContain('ffmpeg')
        ->and($verifyHost)->toContain('FFMPEG_BIN')
        ->and($verifyHost)->toContain('FFPROBE_BIN')
        ->and($verifyHost)->toContain('artisan media:tooling-status');
});

it('ships production env guidance and a homologation script for wall video rollout', function () {
    $repoRoot = dirname(dirname(base_path()));
    $productionEnv = file_get_contents($repoRoot.'/deploy/examples/apps-api.env.production.example');
    $opsReadme = file_get_contents($repoRoot.'/scripts/ops/README.md');
    $deployReadme = file_get_contents($repoRoot.'/scripts/deploy/README.md');
    $healthcheck = file_get_contents($repoRoot.'/scripts/deploy/healthcheck.sh');
    $homologationScript = file_get_contents($repoRoot.'/scripts/ops/homologate-wall-video.sh');

    expect($productionEnv)->toContain('MEDIA_FFMPEG_BIN=/usr/bin/ffmpeg')
        ->and($productionEnv)->toContain('MEDIA_FFPROBE_BIN=/usr/bin/ffprobe')
        ->and($productionEnv)->toContain('PUBLIC_UPLOAD_VIDEO_ENABLED=true')
        ->and($productionEnv)->toContain('PRIVATE_INBOUND_VIDEO_ENABLED=true')
        ->and($opsReadme)->toContain('homologate-wall-video.sh')
        ->and($deployReadme)->toContain('homologate-wall-video.sh')
        ->and($healthcheck)->toContain('REQUIRE_MEDIA_TOOLING')
        ->and($healthcheck)->toContain('artisan media:tooling-status')
        ->and($homologationScript)->toContain('--device-class')
        ->and($homologationScript)->toContain('--network-class')
        ->and($homologationScript)->toContain('artisan media:tooling-status')
        ->and($homologationScript)->toContain('video_start')
        ->and($homologationScript)->toContain('video_first_frame')
        ->and($homologationScript)->toContain('video_complete');
});

<?php

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaProcessingRunService;

it('stores operational metadata when a stage starts and finishes', function () {
    $media = EventMedia::factory()->create();
    $service = app(MediaProcessingRunService::class);

    $run = $service->startStage($media, 'variants', [
        'provider_key' => 'intervention-image',
        'provider_version' => 'v4',
        'model_key' => 'intervention-image-v4',
        'model_snapshot' => 'intervention-image-v4',
        'queue_name' => 'media-fast',
        'idempotency_key' => "variants:{$media->id}",
    ]);

    expect($run->queue_name)->toBe('media-fast')
        ->and($run->provider_version)->toBe('v4')
        ->and($run->model_snapshot)->toBe('intervention-image-v4')
        ->and($run->worker_ref)->not->toBe('');

    $finished = $service->finishStage($run, [
        'decision_key' => 'generated',
        'result_json' => ['variant_keys' => ['fast_preview']],
        'metrics_json' => ['generated_count' => 1],
        'cost_units' => 0.125,
    ]);

    expect($finished->decision_key)->toBe('generated')
        ->and($finished->cost_units)->toBe(0.125)
        ->and($finished->failure_class)->toBeNull()
        ->and($finished->finished_at)->not->toBeNull();
});

it('marks failed stages with a default transient failure class', function () {
    $media = EventMedia::factory()->create();
    $service = app(MediaProcessingRunService::class);

    $run = $service->startStage($media, 'safety', [
        'queue_name' => 'media-safety',
    ]);

    $failed = $service->failStage(
        $run,
        new RuntimeException('provider timeout'),
    );

    expect($failed->status)->toBe('failed')
        ->and($failed->failure_class)->toBe('transient')
        ->and($failed->queue_name)->toBe('media-safety')
        ->and($failed->error_message)->toBe('provider timeout');
});

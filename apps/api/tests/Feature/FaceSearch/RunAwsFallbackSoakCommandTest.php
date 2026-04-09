<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsFallbackSoakAction;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('runs the aws fallback soak command and stores a structured report', function () {
    $reportDir = storage_path('app/testing/face-search-soak/command');
    File::deleteDirectory($reportDir);

    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch soak command',
    ]);

    $action = m::mock(RunAwsFallbackSoakAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->andReturn([
            'status' => 'completed',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'metrics' => [
                'fallback_rate' => 0.0,
                'avg_response_duration_ms' => 740.5,
                'drift_detected_after' => false,
            ],
            'queries' => [],
        ]);

    app()->instance(RunAwsFallbackSoakAction::class, $action);

    $this->artisan('face-search:soak-aws-fallback', [
        'event_ids' => [$event->id],
        '--queries-per-event' => 2,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['summary']['completed'])->toBe(1)
        ->and($report['summary']['failed'])->toBe(0)
        ->and((float) $report['summary']['avg_fallback_rate'])->toBe(0.0)
        ->and($report['summary']['avg_response_duration_ms'])->toBe(740.5)
        ->and($report['events'][0]['status'])->toBe('completed');
});

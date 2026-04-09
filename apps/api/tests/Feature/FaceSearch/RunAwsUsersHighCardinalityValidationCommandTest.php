<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Actions\RunAwsUsersHighCardinalityValidationAction;
use Illuminate\Support\Facades\File;
use Mockery as m;

it('runs the aws users high-cardinality validation command and stores a structured report', function () {
    $reportDir = storage_path('app/testing/face-search-users-high-cardinality/command');
    File::deleteDirectory($reportDir);

    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch users validation command',
    ]);

    $action = m::mock(RunAwsUsersHighCardinalityValidationAction::class);
    $action->shouldReceive('execute')
        ->once()
        ->andReturn([
            'status' => 'completed',
            'event_id' => $event->id,
            'event_title' => $event->title,
            'metrics' => [
                'ready_user_count' => 1804,
                'users_mode_resolution_rate' => 1.0,
                'fallback_rate' => 0.0,
                'top_1_match_rate' => 0.94,
                'top_k_match_rate' => 0.98,
                'p95_response_duration_ms' => 812,
            ],
            'criteria_evaluation' => [
                'passed' => true,
            ],
        ]);

    app()->instance(RunAwsUsersHighCardinalityValidationAction::class, $action);

    $this->artisan('face-search:validate-aws-users-high-cardinality', [
        'event_id' => $event->id,
        '--sample-users' => 40,
        '--min-ready-users' => 500,
        '--target-ready-users' => 2000,
        '--report-dir' => $reportDir,
    ])->assertSuccessful();

    $reports = File::files($reportDir);

    expect($reports)->toHaveCount(1);

    $report = json_decode((string) File::get($reports[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['report']['status'])->toBe('completed')
        ->and($report['report']['metrics']['ready_user_count'])->toBe(1804)
        ->and($report['report']['criteria_evaluation']['passed'])->toBeTrue()
        ->and($report['requested_criteria']['target_ready_users'])->toBe(2000);
});

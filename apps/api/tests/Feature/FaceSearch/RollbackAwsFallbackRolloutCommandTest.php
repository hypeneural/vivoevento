<?php

use App\Modules\Events\Models\Event;
use Illuminate\Support\Facades\File;

it('restores the previous face-search settings from a rollout report', function () {
    $reportDir = storage_path('app/testing/face-search-rollout/rollback');
    File::deleteDirectory($reportDir);
    File::ensureDirectoryExists($reportDir);

    $event = Event::factory()->active()->create([
        'title' => 'FaceSearch rollback target',
    ]);

    \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'fallback_backend_key' => 'local_pgvector',
        'routing_policy' => 'aws_primary_local_fallback',
        'shadow_mode_percentage' => 0,
    ]);

    $reportPath = $reportDir . DIRECTORY_SEPARATOR . 'rollback-report.json';
    File::put($reportPath, json_encode([
        'events' => [[
            'event_id' => $event->id,
            'previous_settings' => array_merge(
                App\Modules\FaceSearch\Models\EventFaceSearchSetting::defaultAttributes(),
                [
                    'enabled' => true,
                    'recognition_enabled' => true,
                    'search_backend_key' => 'aws_rekognition',
                    'fallback_backend_key' => 'local_pgvector',
                    'routing_policy' => 'aws_primary_local_shadow',
                    'shadow_mode_percentage' => 100,
                ],
            ),
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->artisan('face-search:rollback-aws-fallback', [
        '--report' => $reportPath,
    ])->assertSuccessful();

    $this->assertDatabaseHas('event_face_search_settings', [
        'event_id' => $event->id,
        'routing_policy' => 'aws_primary_local_shadow',
        'shadow_mode_percentage' => 100,
        'fallback_backend_key' => 'local_pgvector',
    ]);
});

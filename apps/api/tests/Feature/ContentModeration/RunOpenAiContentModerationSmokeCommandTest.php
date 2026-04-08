<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('runs the openai content moderation smoke command and stores a report', function () {
    $sourceDir = storage_path('app/testing/content-moderation-smoke/assets-' . uniqid());
    $reportDir = storage_path('app/testing/content-moderation-smoke/reports-' . uniqid());

    File::ensureDirectoryExists($sourceDir);
    File::ensureDirectoryExists($reportDir);
    File::put($sourceDir . DIRECTORY_SEPARATOR . 'sample.jpg', 'fake-image-binary');

    $manifestPath = writeOpenAiContentModerationSmokeManifest($sourceDir, [
        'id' => 'smoke-entry-1',
        'relative_path' => 'sample.jpg',
    ]);

    config()->set('content_moderation.providers.openai.base_url', 'https://api.openai.com/v1');
    config()->set('content_moderation.providers.openai.api_key', 'openai-test-key');
    config()->set('content_moderation.providers.openai.model', 'omni-moderation-latest');
    config()->set('content_moderation.providers.openai.provider_version', 'openai-http-v1');

    Http::fake([
        'https://api.openai.com/v1/moderations' => Http::response([
            'id' => 'modr-smoke-1',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => [
                    'sexual' => false,
                    'violence' => false,
                    'self-harm' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.02,
                    'sexual/minors' => 0.0,
                    'violence' => 0.01,
                    'violence/graphic' => 0.0,
                    'self-harm' => 0.0,
                    'self-harm/intent' => 0.0,
                    'self-harm/instructions' => 0.0,
                ],
                'category_applied_input_types' => [
                    'sexual' => ['image'],
                    'violence' => ['image'],
                    'self-harm' => ['image'],
                ],
            ]],
        ]),
    ]);

    $this->artisan('content-moderation:smoke-openai', [
        '--manifest' => $manifestPath,
        '--entry-id' => 'smoke-entry-1',
        '--report-dir' => $reportDir,
    ])
        ->expectsOutputToContain('"provider": "openai"')
        ->assertSuccessful();

    $reportFiles = File::files($reportDir);

    expect($reportFiles)->toHaveCount(1);

    $report = json_decode((string) File::get($reportFiles[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['provider'])->toBe('openai')
        ->and($report['model'])->toBe('omni-moderation-latest')
        ->and($report['entry_id'])->toBe('smoke-entry-1')
        ->and($report['path_used'])->toBe('data_url')
        ->and($report['fallback_triggered'])->toBeTrue()
        ->and($report['request_outcome'])->toBe('success')
        ->and($report['decision'])->toBe('pass')
        ->and($report['blocked'])->toBeFalse()
        ->and($report['review_required'])->toBeFalse()
        ->and($report['provider_flagged'])->toBeFalse()
        ->and($report['provider_category_input_types']['sexual'])->toBe(['image'])
        ->and($report['category_scores']['nudity'])->toBe(0.02);
});

it('fails cleanly when the openai content moderation api key is not configured', function () {
    $reportDir = storage_path('app/testing/content-moderation-smoke/reports-' . uniqid());
    File::ensureDirectoryExists($reportDir);

    config()->set('content_moderation.providers.openai.api_key', null);

    $this->artisan('content-moderation:smoke-openai', [
        '--report-dir' => $reportDir,
    ])
        ->expectsOutputToContain('OPENAI_API_KEY is not configured for content moderation smoke.')
        ->assertFailed();

    $reportFiles = File::files($reportDir);

    expect($reportFiles)->toHaveCount(1);

    $report = json_decode((string) File::get($reportFiles[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['provider'])->toBe('openai')
        ->and($report['request_outcome'])->toBe('failed')
        ->and($report['error_message'])->toBe('OPENAI_API_KEY is not configured for content moderation smoke.');
});

/**
 * @param array<string, mixed> $entry
 */
function writeOpenAiContentModerationSmokeManifest(string $assetRoot, array $entry): string
{
    $manifestPath = storage_path('app/testing/content-moderation-smoke/' . uniqid('manifest-', true) . '.json');
    File::ensureDirectoryExists(dirname($manifestPath));

    File::put($manifestPath, json_encode([
        'version' => 1,
        'created_at' => '2026-04-07',
        'status' => 'test',
        'asset_root_env' => '',
        'fallback_asset_root' => $assetRoot,
        'entries' => [$entry],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $manifestPath;
}

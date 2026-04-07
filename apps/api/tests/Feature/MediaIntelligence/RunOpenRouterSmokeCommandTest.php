<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('runs the openrouter smoke command with a fixed homologated model and stores a report', function () {
    $sourceDir = storage_path('app/testing/media-intelligence-smoke/assets-' . uniqid());
    $reportDir = storage_path('app/testing/media-intelligence-smoke/reports-' . uniqid());

    File::ensureDirectoryExists($sourceDir);
    File::ensureDirectoryExists($reportDir);
    File::put($sourceDir . DIRECTORY_SEPARATOR . 'sample.jpg', 'fake-image-binary');

    $manifestPath = writeOpenRouterSmokeManifest($sourceDir, [
        'id' => 'smoke-entry-1',
        'relative_path' => 'sample.jpg',
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
    config()->set('media_intelligence.providers.openrouter.api_key', 'or-test-key');
    config()->set('media_intelligence.providers.openrouter.model', 'openai/gpt-4.1-mini');
    config()->set('media_intelligence.providers.openrouter.site_url', 'https://eventovivo.test');
    config()->set('media_intelligence.providers.openrouter.app_name', 'Evento Vivo');
    config()->set('media_intelligence.providers.openrouter.allowed_models', [
        'openai/gpt-4.1-mini' => [
            'supports_image' => true,
            'supports_json_schema' => true,
            'routing_mode' => 'fixed',
        ],
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/models/openai/gpt-4.1-mini/endpoints' => Http::response([
            'data' => [
                'id' => 'openai/gpt-4.1-mini',
                'architecture' => [
                    'input_modalities' => ['image', 'text'],
                ],
                'endpoints' => [[
                    'supported_parameters' => ['response_format', 'structured_outputs', 'temperature'],
                ]],
            ],
        ]),
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'id' => 'or-smoke-1',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'decision' => 'approve',
                        'review' => false,
                        'reason' => 'Imagem compativel com o evento.',
                        'short_caption' => 'Legenda curta validada.',
                        'tags' => ['festa', 'casamento'],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
            'usage' => [
                'prompt_tokens' => 101,
                'completion_tokens' => 23,
            ],
        ]),
    ]);

    $this->artisan('media-intelligence:smoke-openrouter', [
        '--manifest' => $manifestPath,
        '--entry-id' => 'smoke-entry-1',
        '--report-dir' => $reportDir,
    ])
        ->expectsOutputToContain('"provider": "openrouter"')
        ->assertSuccessful();

    $reportFiles = File::files($reportDir);

    expect($reportFiles)->toHaveCount(1);

    $report = json_decode((string) File::get($reportFiles[0]->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($report['provider'])->toBe('openrouter')
        ->and($report['model'])->toBe('openai/gpt-4.1-mini')
        ->and($report['entry_id'])->toBe('smoke-entry-1')
        ->and($report['path_used'])->toBe('data_url')
        ->and($report['fallback_triggered'])->toBeTrue()
        ->and($report['request_outcome'])->toBe('success')
        ->and($report['structured_output_valid'])->toBeTrue()
        ->and($report['tokens_input'])->toBe(101)
        ->and($report['tokens_output'])->toBe(23)
        ->and($report['catalog_model_metadata']['supports_json_schema'])->toBeTrue()
        ->and($report['live_model_capabilities']['supports_image'])->toBeTrue()
        ->and($report['live_model_capabilities']['supports_json_schema'])->toBeTrue();
});

it('fails cleanly when the openrouter smoke api key is not configured', function () {
    config()->set('media_intelligence.providers.openrouter.api_key', null);

    $this->artisan('media-intelligence:smoke-openrouter')
        ->expectsOutputToContain('MEDIA_INTELLIGENCE_OPENROUTER_API_KEY is not configured for OpenRouter smoke.')
        ->assertFailed();
});

/**
 * @param array<string, mixed> $entry
 */
function writeOpenRouterSmokeManifest(string $assetRoot, array $entry): string
{
    $manifestPath = storage_path('app/testing/media-intelligence-smoke/' . uniqid('manifest-', true) . '.json');
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

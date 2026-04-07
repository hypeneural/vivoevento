<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use App\Shared\Support\AssetUrlService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenRouterSmokeService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly AssetUrlService $assetUrls,
        private readonly OpenAiCompatibleVisualReasoningPayloadFactory $payloads,
        private readonly OpenRouterModelCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $manifestPath, ?string $entryId = null, ?string $modelOverride = null): array
    {
        $config = (array) config('media_intelligence.providers.openrouter', []);
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new ProviderMisconfiguredException('MEDIA_INTELLIGENCE_OPENROUTER_API_KEY is not configured for OpenRouter smoke.');
        }

        $modelKey = trim((string) ($modelOverride ?: ($config['model'] ?? '')));

        if ($modelKey === '') {
            throw new RuntimeException('OpenRouter smoke requires a fixed model_key.');
        }

        $catalogMetadata = $this->catalog->metadata($modelKey);

        if (! is_array($catalogMetadata)) {
            throw new RuntimeException(sprintf('OpenRouter smoke model [%s] is not homologated in the local catalog.', $modelKey));
        }

        $manifest = $this->loadManifest($manifestPath);
        $assetRoot = $this->resolveAssetRoot($manifest);
        $entry = $this->resolveEntry($manifest, $assetRoot, $entryId);
        $liveCapabilities = $this->fetchLiveCapabilities($config, $apiKey, $modelKey);

        if (! ($liveCapabilities['supports_image'] ?? false)) {
            throw new RuntimeException(sprintf('OpenRouter smoke model [%s] does not expose image support in the live endpoints metadata.', $modelKey));
        }

        if (! ($liveCapabilities['supports_json_schema'] ?? false)) {
            throw new RuntimeException(sprintf('OpenRouter smoke model [%s] does not expose structured output support in the live endpoints metadata.', $modelKey));
        }

        $tempPath = $this->copyToLocalDisk($entry['absolute_path'], $entry['id']);

        try {
            $result = $this->evaluateEntry($tempPath, $entry, $modelKey);
        } finally {
            Storage::disk('local')->delete($tempPath);
        }

        return [
            'provider' => 'openrouter',
            'model' => $modelKey,
            'provider_version' => (string) ($config['provider_version'] ?? 'openrouter-openai-v1'),
            'manifest_path' => (string) $manifest['_resolved_path'],
            'asset_root' => $assetRoot,
            'entry_id' => $entry['id'],
            'entry_relative_path' => $entry['selected_relative_path'],
            'request_outcome' => 'success',
            'path_used' => data_get($result, 'raw.input_path_used'),
            'fallback_triggered' => data_get($result, 'raw.input_path_used') !== 'image_url',
            'input_source_ref' => data_get($result, 'raw.input_source_ref'),
            'input_mime_type' => data_get($result, 'raw.input_mime_type'),
            'latency_ms' => $result['latency_ms'],
            'structured_output_valid' => true,
            'decision' => $result['decision'],
            'reason' => $result['reason'],
            'short_caption' => $result['short_caption'],
            'tags' => $result['tags'],
            'tokens_input' => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'provider_response_id' => data_get($result, 'raw.id'),
            'provider_response_model' => data_get($result, 'raw.model'),
            'catalog_model_metadata' => $catalogMetadata,
            'live_model_capabilities' => $liveCapabilities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        $resolvedPath = File::exists($manifestPath)
            ? $manifestPath
            : base_path(ltrim($manifestPath, '\\/'));

        if (! File::exists($resolvedPath)) {
            throw new RuntimeException(sprintf('OpenRouter smoke manifest [%s] does not exist.', $manifestPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['entries']) || ! is_array($payload['entries'])) {
            throw new RuntimeException('OpenRouter smoke manifest is invalid.');
        }

        $payload['_resolved_path'] = $resolvedPath;

        return $payload;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function resolveAssetRoot(array $manifest): string
    {
        $envKey = (string) ($manifest['asset_root_env'] ?? '');

        if ($envKey !== '') {
            $fromEnv = env($envKey);

            if (is_string($fromEnv) && trim($fromEnv) !== '') {
                return rtrim($fromEnv, "\\/");
            }
        }

        $fallback = (string) ($manifest['fallback_asset_root'] ?? '');
        $userProfile = getenv('USERPROFILE') ?: '';

        return rtrim(str_replace('%USERPROFILE%', (string) $userProfile, $fallback), "\\/");
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function resolveEntry(array $manifest, string $assetRoot, ?string $entryId): array
    {
        if (! is_dir($assetRoot)) {
            throw new RuntimeException(sprintf('OpenRouter smoke asset root [%s] does not exist.', $assetRoot));
        }

        $entries = collect((array) $manifest['entries'])
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(fn (array $entry): array => $entry)
            ->values();

        $selected = $entryId === null || trim($entryId) === ''
            ? $entries->first()
            : $entries->first(fn (array $entry): bool => (string) ($entry['id'] ?? '') === trim($entryId));

        if (! is_array($selected)) {
            throw new RuntimeException('OpenRouter smoke could not resolve a dataset entry.');
        }

        $relativePath = (string) ($selected['smoke_relative_path'] ?? ($selected['relative_path'] ?? ''));
        $absolutePath = $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        if (! File::exists($absolutePath)) {
            throw new RuntimeException(sprintf('OpenRouter smoke asset [%s] does not exist.', $relativePath));
        }

        return [
            'id' => (string) ($selected['id'] ?? ''),
            'selected_relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'mime_type' => $this->resolveImageMimeType($absolutePath),
        ];
    }

    private function copyToLocalDisk(string $absolutePath, string $entryId): string
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg';
        $relativePath = sprintf(
            'testing/media-intelligence-smoke/%s-%s.%s',
            now()->format('Ymd-His'),
            $entryId,
            $extension,
        );

        Storage::disk('local')->put($relativePath, File::get($absolutePath));

        return $relativePath;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function evaluateEntry(string $tempPath, array $entry, string $modelKey): array
    {
        $event = Event::factory()->make([
            'id' => 999901,
            'title' => 'OpenRouter Smoke Validation',
        ]);

        $media = EventMedia::factory()->make([
            'id' => 999902,
            'event_id' => 999901,
            'caption' => null,
            'mime_type' => $entry['mime_type'],
            'original_disk' => 'local',
            'original_path' => $tempPath,
            'original_filename' => basename($tempPath),
        ]);
        $media->setRelation('event', $event);
        $media->setRelation('variants', collect());

        $settings = EventMediaIntelligenceSetting::factory()->make([
            'event_id' => 999901,
            'provider_key' => 'openrouter',
            'model_key' => $modelKey,
            'enabled' => true,
            'mode' => 'enrich_only',
            'prompt_version' => EventMediaIntelligenceSetting::DEFAULT_PROMPT_VERSION,
            'response_schema_version' => EventMediaIntelligenceSetting::DEFAULT_RESPONSE_SCHEMA_VERSION,
            'require_json_output' => true,
        ]);

        $provider = new OpenRouterVisualReasoningProvider(
            $this->http,
            $this->dataUrlOnlyAssetService(),
            $this->payloads,
        );

        $startedAt = microtime(true);
        $result = $provider->evaluate($media, $settings);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'decision' => $result->decision->value,
            'reason' => $result->reason,
            'short_caption' => $result->shortCaption,
            'tags' => $result->tags,
            'tokens_input' => $result->tokensInput,
            'tokens_output' => $result->tokensOutput,
            'latency_ms' => $latencyMs,
            'raw' => $result->rawResponse,
        ];
    }

    private function dataUrlOnlyAssetService(): MediaAssetUrlService
    {
        return new class($this->assetUrls) extends MediaAssetUrlService
        {
            public function __construct(AssetUrlService $assets)
            {
                parent::__construct($assets);
            }

            public function preview(EventMedia $media): ?string
            {
                return null;
            }
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function fetchLiveCapabilities(array $config, string $apiKey, string $modelKey): array
    {
        $response = $this->http
            ->baseUrl(rtrim((string) ($config['base_url'] ?? 'https://openrouter.ai/api/v1'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->get('models/' . str_replace('%2F', '/', rawurlencode($modelKey)) . '/endpoints');

        $response->throw();

        $payload = $response->json('data');

        if (! is_array($payload)) {
            throw new RuntimeException('OpenRouter model endpoints payload is invalid.');
        }

        $inputModalities = array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) ? $item : null,
            Arr::wrap(data_get($payload, 'architecture.input_modalities', [])),
        )));

        $supportedParameters = collect(Arr::wrap($payload['endpoints'] ?? []))
            ->filter(fn (mixed $endpoint): bool => is_array($endpoint))
            ->flatMap(fn (array $endpoint): array => array_values(array_filter(array_map(
                static fn (mixed $item): ?string => is_string($item) ? $item : null,
                Arr::wrap($endpoint['supported_parameters'] ?? []),
            ))))
            ->unique()
            ->values()
            ->all();

        return [
            'model_id' => (string) ($payload['id'] ?? $modelKey),
            'input_modalities' => $inputModalities,
            'supported_parameters' => $supportedParameters,
            'supports_image' => in_array('image', $inputModalities, true),
            'supports_json_schema' => in_array('response_format', $supportedParameters, true)
                || in_array('structured_outputs', $supportedParameters, true),
        ];
    }

    private function resolveImageMimeType(string $absolutePath): string
    {
        $mimeType = File::mimeType($absolutePath);

        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
            return $mimeType;
        }

        return match (strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}

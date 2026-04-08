<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use App\Shared\Support\AssetUrlService;
use App\Shared\Support\ExternalImageUrlPolicy;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiContentModerationSmokeService
{
    public function __construct(
        private readonly OpenAiContentModerationProvider $provider,
        private readonly AssetUrlService $assetUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $manifestPath, ?string $entryId = null): array
    {
        $config = (array) config('content_moderation.providers.openai', []);
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new ProviderMisconfiguredException('OPENAI_API_KEY is not configured for content moderation smoke.');
        }

        $manifest = $this->loadManifest($manifestPath);
        $assetRoot = $this->resolveAssetRoot($manifest);
        $entry = $this->resolveEntry($manifest, $assetRoot, $entryId);
        $tempPath = $this->copyToLocalDisk($entry['absolute_path'], $entry['id']);

        try {
            $result = $this->evaluateEntry($tempPath, $entry);
        } finally {
            Storage::disk('local')->delete($tempPath);
        }

        return [
            'provider' => 'openai',
            'model' => (string) ($config['model'] ?? 'omni-moderation-latest'),
            'provider_version' => (string) ($config['provider_version'] ?? 'openai-http-v1'),
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
            'decision' => $result['decision'],
            'blocked' => $result['blocked'],
            'review_required' => $result['review_required'],
            'provider_flagged' => $result['provider_flagged'],
            'category_scores' => $result['category_scores'],
            'provider_categories' => $result['provider_categories'],
            'provider_category_scores' => $result['provider_category_scores'],
            'provider_category_input_types' => $result['provider_category_input_types'],
            'provider_response_id' => data_get($result, 'raw.id'),
            'provider_response_model' => data_get($result, 'raw.model'),
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
            throw new RuntimeException(sprintf('Content moderation smoke manifest [%s] does not exist.', $manifestPath));
        }

        $payload = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['entries']) || ! is_array($payload['entries'])) {
            throw new RuntimeException('Content moderation smoke manifest is invalid.');
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
            throw new RuntimeException(sprintf('Content moderation smoke asset root [%s] does not exist.', $assetRoot));
        }

        $entries = collect((array) $manifest['entries'])
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(fn (array $entry): array => $entry)
            ->values();

        $selected = $entryId === null || trim($entryId) === ''
            ? $entries->first()
            : $entries->first(fn (array $entry): bool => (string) ($entry['id'] ?? '') === trim($entryId));

        if (! is_array($selected)) {
            throw new RuntimeException('Content moderation smoke could not resolve a dataset entry.');
        }

        $relativePath = (string) ($selected['smoke_relative_path'] ?? ($selected['relative_path'] ?? ''));
        $absolutePath = $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

        if (! File::exists($absolutePath)) {
            throw new RuntimeException(sprintf('Content moderation smoke asset [%s] does not exist.', $relativePath));
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
            'testing/content-moderation-smoke/%s-%s.%s',
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
    private function evaluateEntry(string $tempPath, array $entry): array
    {
        $event = Event::factory()->make([
            'id' => 999903,
            'title' => 'OpenAI Moderation Smoke Validation',
        ]);

        $media = EventMedia::factory()->make([
            'id' => 999904,
            'event_id' => 999903,
            'caption' => null,
            'mime_type' => $entry['mime_type'],
            'original_disk' => 'local',
            'original_path' => $tempPath,
            'original_filename' => basename($tempPath),
        ]);
        $media->setRelation('event', $event);
        $media->setRelation('variants', collect());

        $settings = EventContentModerationSetting::factory()->make([
            'event_id' => 999903,
            'provider_key' => 'openai',
            'enabled' => true,
        ]);

        $provider = new OpenAiContentModerationProvider(
            app(\Illuminate\Http\Client\Factory::class),
            $this->dataUrlOnlyAssetService(),
            app(\App\Modules\ContentModeration\Support\ContentSafetyThresholdEvaluator::class),
            app(ExternalImageUrlPolicy::class),
        );

        $startedAt = microtime(true);
        $result = $provider->evaluate($media, $settings);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'decision' => $result->decision->value,
            'blocked' => $result->blocked,
            'review_required' => $result->reviewRequired,
            'provider_flagged' => (bool) data_get($result->normalizedProvider, 'flagged', false),
            'category_scores' => $result->categoryScores,
            'provider_categories' => $result->providerCategories,
            'provider_category_scores' => $result->providerCategoryScores,
            'provider_category_input_types' => $result->providerCategoryInputTypes,
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

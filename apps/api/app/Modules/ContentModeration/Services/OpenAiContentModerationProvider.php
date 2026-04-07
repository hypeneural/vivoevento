<?php

namespace App\Modules\ContentModeration\Services;

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Enums\ContentSafetyDecision;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\ContentModeration\Support\ContentSafetyThresholdEvaluator;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiContentModerationProvider implements ContentModerationProviderInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MediaAssetUrlService $assetUrls,
        private readonly ContentSafetyThresholdEvaluator $thresholds,
    ) {}

    public function evaluate(
        EventMedia $media,
        EventContentModerationSetting $settings,
    ): ContentSafetyEvaluationResult {
        $config = (array) config('content_moderation.providers.openai', []);
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new ProviderMisconfiguredException('OPENAI_API_KEY is not configured for content moderation.');
        }

        $imageInput = $this->buildImageInput($media);

        $input = [
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageInput['url'],
                ],
            ],
        ];

        $textContext = trim(implode("\n", array_filter([
            $media->caption,
            $media->inboundMessage?->body_text,
        ])));

        if ($textContext !== '') {
            $input[] = [
                'type' => 'text',
                'text' => $textContext,
            ];
        }

        $request = $this->http
            ->baseUrl(rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));

        if ($organization = $config['organization'] ?? null) {
            $request = $request->withHeaders([
                'OpenAI-Organization' => (string) $organization,
            ]);
        }

        if ($project = $config['project'] ?? null) {
            $request = $request->withHeaders([
                'OpenAI-Project' => (string) $project,
            ]);
        }

        $response = $request->post('/moderations', [
            'model' => (string) ($config['model'] ?? 'omni-moderation-latest'),
            'input' => $input,
        ]);

        $response->throw();

        $payload = $response->json();
        $result = Arr::first(Arr::wrap($payload['results'] ?? []));

        if (! is_array($result)) {
            throw new RuntimeException('OpenAI moderation response did not contain a valid result payload.');
        }

        $providerCategories = $this->normalizeProviderCategories((array) ($result['categories'] ?? []));
        $providerCategoryScores = $this->normalizeProviderCategoryScores((array) ($result['category_scores'] ?? []));
        $providerCategoryInputTypes = $this->normalizeProviderCategoryInputTypes((array) ($result['category_applied_input_types'] ?? []));
        $normalizedProvider = [
            'flagged' => (bool) ($result['flagged'] ?? false),
            'categories' => $providerCategories,
            'category_scores' => $providerCategoryScores,
            'category_applied_input_types' => $providerCategoryInputTypes,
            'input_path_used' => $imageInput['path_used'],
            'input_source_ref' => $imageInput['source_ref'] ?? null,
            'input_mime_type' => $imageInput['mime_type'] ?? null,
        ];

        $categoryScores = $this->mapCategoryScores($providerCategoryScores);
        $thresholdDecision = $this->thresholds->evaluate(
            $categoryScores,
            (array) ($settings->hard_block_thresholds_json ?? []),
            (array) ($settings->review_thresholds_json ?? []),
        );

        $decision = $thresholdDecision['decision'];
        $reasonCodes = $thresholdDecision['reason_codes'];

        if (($result['flagged'] ?? false) === true && $decision === ContentSafetyDecision::Pass) {
            $decision = ContentSafetyDecision::Review;
            $reasonCodes[] = 'provider.flagged';
        }

        $common = [
            'categoryScores' => $categoryScores,
            'providerCategories' => $providerCategories,
            'providerCategoryScores' => $providerCategoryScores,
            'providerCategoryInputTypes' => $providerCategoryInputTypes,
            'normalizedProvider' => $normalizedProvider,
            'reasonCodes' => array_values(array_unique($reasonCodes)),
            'rawResponse' => [
                'id' => $payload['id'] ?? null,
                'model' => $payload['model'] ?? ($config['model'] ?? 'omni-moderation-latest'),
                'results' => $payload['results'] ?? [],
                'input_path_used' => $imageInput['path_used'],
                'input_source_ref' => $imageInput['source_ref'] ?? null,
                'input_mime_type' => $imageInput['mime_type'] ?? null,
            ],
            'providerKey' => 'openai',
            'providerVersion' => (string) ($config['provider_version'] ?? 'openai-http-v1'),
            'modelKey' => (string) ($config['model'] ?? 'omni-moderation-latest'),
            'modelSnapshot' => (string) ($config['model_snapshot'] ?? ($payload['model'] ?? ($config['model'] ?? 'omni-moderation-latest'))),
            'thresholdVersion' => $settings->threshold_version,
        ];

        return match ($decision) {
            ContentSafetyDecision::Block => ContentSafetyEvaluationResult::block(...$common),
            ContentSafetyDecision::Review => ContentSafetyEvaluationResult::review(...$common),
            default => ContentSafetyEvaluationResult::pass(...$common),
        };
    }

    /**
     * @param array<string, mixed> $providerScores
     * @return array<string, float>
     */
    private function mapCategoryScores(array $providerScores): array
    {
        $sexual = $this->score($providerScores, 'sexual');
        $sexualMinors = $this->score($providerScores, 'sexual/minors');
        $violence = $this->score($providerScores, 'violence');
        $violenceGraphic = $this->score($providerScores, 'violence/graphic');
        $selfHarm = $this->score($providerScores, 'self-harm');
        $selfHarmIntent = $this->score($providerScores, 'self-harm/intent');
        $selfHarmInstructions = $this->score($providerScores, 'self-harm/instructions');

        return [
            'nudity' => max($sexual, $sexualMinors),
            'violence' => max($violence, $violenceGraphic),
            'self_harm' => max($selfHarm, $selfHarmIntent, $selfHarmInstructions),
        ];
    }

    /**
     * @param array<string, mixed> $providerScores
     */
    private function score(array $providerScores, string $key): float
    {
        $value = $providerScores[$key] ?? 0.0;

        return is_numeric($value)
            ? round((float) $value, 6)
            : 0.0;
    }

    /**
     * @return array{url:string,path_used:string,source_ref:string,mime_type:string|null}
     */
    private function buildImageInput(EventMedia $media): array
    {
        $imageUrl = $this->assetUrls->preview($media);

        if (is_string($imageUrl) && $imageUrl !== '') {
            return [
                'url' => $imageUrl,
                'path_used' => 'image_url',
                'source_ref' => $imageUrl,
                'mime_type' => null,
            ];
        }

        [$disk, $path, $binary, $mimeType] = $this->loadFallbackBinary($media);

        return [
            'url' => $this->toDataUrl($binary, $mimeType),
            'path_used' => 'data_url',
            'source_ref' => "{$disk}:{$path}",
            'mime_type' => $mimeType,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function loadFallbackBinary(EventMedia $media): array
    {
        $media->loadMissing('variants');

        foreach (['fast_preview', 'gallery', 'wall', 'thumb'] as $variantKey) {
            $variant = $media->variants->firstWhere('variant_key', $variantKey);
            $disk = $variant?->disk ?: 'public';
            $path = $variant?->path;

            if ($path && Storage::disk($disk)->exists($path)) {
                return [
                    $disk,
                    $path,
                    Storage::disk($disk)->get($path),
                    $this->resolveImageMimeType($variant?->mime_type, $media->mime_type),
                ];
            }
        }

        $disk = $media->originalStorageDisk();
        $path = $media->originalStoragePath();

        if ($path && Storage::disk($disk)->exists($path)) {
            return [
                $disk,
                $path,
                Storage::disk($disk)->get($path),
                $this->resolveImageMimeType($media->mime_type),
            ];
        }

        throw new ProviderMisconfiguredException("No public preview URL or local fallback asset available for media {$media->id}.");
    }

    private function toDataUrl(string $binary, string $mimeType): string
    {
        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($binary));
    }

    private function resolveImageMimeType(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, 'image/')) {
                return $candidate;
            }
        }

        return 'image/jpeg';
    }

    /**
     * @param array<string, mixed> $providerCategories
     * @return array<string, bool>
     */
    private function normalizeProviderCategories(array $providerCategories): array
    {
        $normalized = [];

        foreach ($providerCategories as $key => $value) {
            $normalized[(string) $key] = (bool) $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $providerScores
     * @return array<string, float>
     */
    private function normalizeProviderCategoryScores(array $providerScores): array
    {
        $normalized = [];

        foreach ($providerScores as $key => $value) {
            $normalized[(string) $key] = is_numeric($value)
                ? round((float) $value, 6)
                : 0.0;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $providerInputTypes
     * @return array<string, array<int, string>>
     */
    private function normalizeProviderCategoryInputTypes(array $providerInputTypes): array
    {
        $normalized = [];

        foreach ($providerInputTypes as $key => $value) {
            $types = array_values(array_filter(array_map(
                static fn (mixed $item): ?string => is_string($item) ? $item : null,
                Arr::wrap($value),
            )));

            $normalized[(string) $key] = $types;
        }

        return $normalized;
    }
}

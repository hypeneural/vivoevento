<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

abstract class AbstractOpenAiCompatibleVisualReasoningProvider implements VisualReasoningProviderInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MediaAssetUrlService $assetUrls,
        private readonly OpenAiCompatibleVisualReasoningPayloadFactory $payloads,
    ) {}

    public function evaluate(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult {
        $config = (array) config('media_intelligence.providers.' . $this->providerKey(), []);
        $imageInput = $this->buildImageInput($media);

        $response = $this->request($settings, $config)
            ->post('chat/completions', $this->payloads->build($media, $settings, $imageInput['url'], $config));

        $response->throw();

        $payload = $response->json();
        $content = $this->extractContent(Arr::get($payload, 'choices.0.message.content'));

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                '%s returned an invalid JSON payload for media intelligence.',
                $this->providerLabel(),
            ), previous: $exception);
        }

        $decision = $this->normalizeDecision($decoded['decision'] ?? null, (bool) ($decoded['review'] ?? false));
        $reason = $this->normalizeNullableString($decoded['reason'] ?? null);
        $shortCaption = $this->normalizeNullableString($decoded['short_caption'] ?? null);
        $tags = $this->normalizeTags($decoded['tags'] ?? []);

        $common = [
            'reason' => $reason,
            'shortCaption' => $shortCaption,
            'tags' => $tags,
            'rawResponse' => [
                'id' => $payload['id'] ?? null,
                'model' => $payload['model'] ?? ($settings->model_key ?: ($config['model'] ?? null)),
                'usage' => $payload['usage'] ?? null,
                'message' => $decoded,
                'input_path_used' => $imageInput['path_used'],
                'input_source_ref' => $imageInput['source_ref'] ?? null,
                'input_mime_type' => $imageInput['mime_type'] ?? null,
            ],
            'providerKey' => $this->providerKey(),
            'providerVersion' => (string) ($config['provider_version'] ?? ($this->providerKey() . '-openai-v1')),
            'modelKey' => (string) ($settings->model_key ?: ($config['model'] ?? '')),
            'modelSnapshot' => (string) ($config['model_snapshot'] ?? ($settings->model_key ?: ($config['model'] ?? ''))),
            'promptVersion' => $settings->prompt_version,
            'responseSchemaVersion' => $settings->response_schema_version,
            'modeApplied' => $settings->mode,
            'tokensInput' => is_numeric(Arr::get($payload, 'usage.prompt_tokens')) ? (int) Arr::get($payload, 'usage.prompt_tokens') : null,
            'tokensOutput' => is_numeric(Arr::get($payload, 'usage.completion_tokens')) ? (int) Arr::get($payload, 'usage.completion_tokens') : null,
        ];

        return match ($decision) {
            'reject' => VisualReasoningEvaluationResult::reject(...$common),
            'review' => VisualReasoningEvaluationResult::review(...$common),
            default => VisualReasoningEvaluationResult::approve(...$common),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function request(EventMediaIntelligenceSetting $settings, array $config): PendingRequest
    {
        $request = $this->http
            ->baseUrl(rtrim((string) ($config['base_url'] ?? ''), '/'))
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($settings->timeout_ms > 0 ? ceil($settings->timeout_ms / 1000) : ($config['timeout'] ?? 20)))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));

        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        return $this->extendRequest($request, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function extendRequest(PendingRequest $request, array $config): PendingRequest
    {
        return $request;
    }

    abstract protected function providerKey(): string;

    abstract protected function providerLabel(): string;

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

    private function extractContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $segments = [];

            foreach ($content as $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'text' && isset($item['text']) && is_string($item['text'])) {
                    $segments[] = $item['text'];
                }
            }

            if ($segments !== []) {
                return implode("\n", $segments);
            }
        }

        throw new RuntimeException(sprintf('%s response did not include textual content.', $this->providerLabel()));
    }

    private function normalizeDecision(mixed $decision, bool $reviewFlag): string
    {
        if ($reviewFlag) {
            return 'review';
        }

        $normalized = is_string($decision)
            ? strtolower(trim($decision))
            : '';

        return match ($normalized) {
            'approve', 'approved', 'pass' => 'approve',
            'reject', 'rejected', 'block' => 'reject',
            default => 'review',
        };
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (mixed $tag): ?string {
            if (! is_string($tag)) {
                return null;
            }

            $trimmed = trim($tag);

            return $trimmed === '' ? null : $trimmed;
        }, $tags))));
    }
}

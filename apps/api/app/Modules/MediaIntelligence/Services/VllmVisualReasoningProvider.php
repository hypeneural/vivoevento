<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

class VllmVisualReasoningProvider implements VisualReasoningProviderInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MediaAssetUrlService $assetUrls,
        private readonly VisualReasoningResponseSchemaFactory $schemas,
    ) {}

    public function evaluate(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): VisualReasoningEvaluationResult {
        $config = (array) config('media_intelligence.providers.vllm', []);
        $imageUrl = $this->assetUrls->preview($media);

        if (! $imageUrl) {
            throw new ProviderMisconfiguredException("No public preview URL available for media {$media->id}.");
        }

        $request = $this->http
            ->baseUrl(rtrim((string) ($config['base_url'] ?? 'http://localhost:8000/v1'), '/'))
            ->acceptJson()
            ->contentType('application/json')
            ->timeout((int) ($settings->timeout_ms > 0 ? ceil($settings->timeout_ms / 1000) : ($config['timeout'] ?? 20)))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));

        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->post('chat/completions', $this->payload($media, $settings, $imageUrl, $config));
        $response->throw();

        $payload = $response->json();
        $content = $this->extractContent(Arr::get($payload, 'choices.0.message.content'));

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('vLLM returned an invalid JSON payload for media intelligence.', previous: $exception);
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
            ],
            'providerKey' => 'vllm',
            'providerVersion' => (string) ($config['provider_version'] ?? 'vllm-openai-v1'),
            'modelKey' => (string) ($settings->model_key ?: ($config['model'] ?? 'Qwen/Qwen2.5-VL-3B-Instruct')),
            'modelSnapshot' => (string) ($config['model_snapshot'] ?? ($settings->model_key ?: ($config['model'] ?? 'Qwen/Qwen2.5-VL-3B-Instruct'))),
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
     * @return array<string, mixed>
     */
    private function payload(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
        string $imageUrl,
        array $config,
    ): array {
        $userInstructions = implode("\n\n", array_filter([
            trim((string) $settings->approval_prompt),
            $media->event?->title ? 'Contexto adicional do evento: ' . $media->event->title : null,
            $media->caption ? 'Legenda original enviada: ' . $media->caption : null,
            $media->inboundMessage?->body_text ? 'Texto associado ao envio: ' . $media->inboundMessage->body_text : null,
            trim((string) $settings->caption_style_prompt),
        ]));

        $payload = [
            'model' => (string) ($settings->model_key ?: ($config['model'] ?? 'Qwen/Qwen2.5-VL-3B-Instruct')),
            'temperature' => (float) ($config['temperature'] ?? 0.1),
            'max_completion_tokens' => (int) ($config['max_completion_tokens'] ?? 300),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Voce responde apenas em JSON valido, seguindo exatamente o schema solicitado.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $userInstructions,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                            ],
                            'uuid' => sprintf('media-%s', $media->id),
                        ],
                    ],
                ],
            ],
        ];

        if ($settings->require_json_output) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'eventovivo_media_intelligence',
                    'strict' => true,
                    'schema' => $this->schemas->schema($settings->response_schema_version ?: 'foundation-v1'),
                ],
            ];
        }

        return $payload;
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

        throw new RuntimeException('vLLM response did not include textual content.');
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

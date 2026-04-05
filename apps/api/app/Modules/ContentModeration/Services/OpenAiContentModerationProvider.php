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

        $imageUrl = $this->assetUrls->preview($media);

        if (! $imageUrl) {
            throw new ProviderMisconfiguredException("No public preview URL available for media {$media->id}.");
        }

        $input = [
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageUrl,
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

        $categoryScores = $this->mapCategoryScores((array) ($result['category_scores'] ?? []));
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
            'reasonCodes' => array_values(array_unique($reasonCodes)),
            'rawResponse' => [
                'id' => $payload['id'] ?? null,
                'model' => $payload['model'] ?? ($config['model'] ?? 'omni-moderation-latest'),
                'results' => $payload['results'] ?? [],
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
}

<?php

namespace App\Modules\ContentModeration\DTOs;

use App\Modules\ContentModeration\Enums\ContentSafetyDecision;

final class ContentSafetyEvaluationResult
{
    /**
     * @param array<string, float|int|string|bool|null> $categoryScores
     * @param array<int, string> $reasonCodes
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly ContentSafetyDecision $decision,
        public readonly bool $blocked = false,
        public readonly bool $reviewRequired = false,
        public readonly array $categoryScores = [],
        public readonly array $providerCategories = [],
        public readonly array $providerCategoryScores = [],
        public readonly array $providerCategoryInputTypes = [],
        public readonly array $normalizedProvider = [],
        public readonly array $reasonCodes = [],
        public readonly array $rawResponse = [],
        public readonly ?string $providerKey = null,
        public readonly ?string $providerVersion = null,
        public readonly ?string $modelKey = null,
        public readonly ?string $modelSnapshot = null,
        public readonly ?string $thresholdVersion = null,
    ) {}

    /**
     * @param array<string, float|int|string|bool|null> $categoryScores
     * @param array<int, string> $reasonCodes
     * @param array<string, mixed> $rawResponse
     */
    public static function pass(
        array $categoryScores = [],
        array $providerCategories = [],
        array $providerCategoryScores = [],
        array $providerCategoryInputTypes = [],
        array $normalizedProvider = [],
        array $reasonCodes = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $thresholdVersion = null,
    ): self {
        return new self(
            decision: ContentSafetyDecision::Pass,
            blocked: false,
            reviewRequired: false,
            categoryScores: $categoryScores,
            providerCategories: $providerCategories,
            providerCategoryScores: $providerCategoryScores,
            providerCategoryInputTypes: $providerCategoryInputTypes,
            normalizedProvider: $normalizedProvider,
            reasonCodes: $reasonCodes,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            thresholdVersion: $thresholdVersion,
        );
    }

    /**
     * @param array<string, float|int|string|bool|null> $categoryScores
     * @param array<int, string> $reasonCodes
     * @param array<string, mixed> $rawResponse
     */
    public static function review(
        array $categoryScores = [],
        array $providerCategories = [],
        array $providerCategoryScores = [],
        array $providerCategoryInputTypes = [],
        array $normalizedProvider = [],
        array $reasonCodes = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $thresholdVersion = null,
    ): self {
        return new self(
            decision: ContentSafetyDecision::Review,
            blocked: false,
            reviewRequired: true,
            categoryScores: $categoryScores,
            providerCategories: $providerCategories,
            providerCategoryScores: $providerCategoryScores,
            providerCategoryInputTypes: $providerCategoryInputTypes,
            normalizedProvider: $normalizedProvider,
            reasonCodes: $reasonCodes,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            thresholdVersion: $thresholdVersion,
        );
    }

    /**
     * @param array<string, float|int|string|bool|null> $categoryScores
     * @param array<int, string> $reasonCodes
     * @param array<string, mixed> $rawResponse
     */
    public static function block(
        array $categoryScores = [],
        array $providerCategories = [],
        array $providerCategoryScores = [],
        array $providerCategoryInputTypes = [],
        array $normalizedProvider = [],
        array $reasonCodes = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $thresholdVersion = null,
    ): self {
        return new self(
            decision: ContentSafetyDecision::Block,
            blocked: true,
            reviewRequired: false,
            categoryScores: $categoryScores,
            providerCategories: $providerCategories,
            providerCategoryScores: $providerCategoryScores,
            providerCategoryInputTypes: $providerCategoryInputTypes,
            normalizedProvider: $normalizedProvider,
            reasonCodes: $reasonCodes,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            thresholdVersion: $thresholdVersion,
        );
    }

    public static function skipped(
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $thresholdVersion = null,
    ): self {
        return new self(
            decision: ContentSafetyDecision::Skipped,
            blocked: false,
            reviewRequired: false,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            thresholdVersion: $thresholdVersion,
        );
    }

    public function safetyStatus(): string
    {
        return $this->decision->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toEvaluationAttributes(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'provider_version' => $this->providerVersion,
            'model_key' => $this->modelKey,
            'model_snapshot' => $this->modelSnapshot,
            'threshold_version' => $this->thresholdVersion,
            'decision' => $this->decision->value,
            'blocked' => $this->blocked,
            'review_required' => $this->reviewRequired,
            'category_scores_json' => $this->categoryScores,
            'provider_categories_json' => $this->providerCategories,
            'provider_category_scores_json' => $this->providerCategoryScores,
            'provider_category_input_types_json' => $this->providerCategoryInputTypes,
            'normalized_provider_json' => $this->normalizedProvider,
            'reason_codes_json' => $this->reasonCodes,
            'raw_response_json' => $this->rawResponse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toRunResult(): array
    {
        return [
            'decision' => $this->decision->value,
            'blocked' => $this->blocked,
            'review_required' => $this->reviewRequired,
            'category_scores' => $this->categoryScores,
            'input_path_used' => $this->normalizedProvider['input_path_used'] ?? null,
            'reason_codes' => $this->reasonCodes,
        ];
    }
}

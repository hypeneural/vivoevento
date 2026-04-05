<?php

namespace App\Modules\MediaIntelligence\DTOs;

use App\Modules\MediaIntelligence\Enums\VisualReasoningDecision;

final class VisualReasoningEvaluationResult
{
    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly VisualReasoningDecision $decision,
        public readonly bool $reviewRequired = false,
        public readonly ?string $reason = null,
        public readonly ?string $shortCaption = null,
        public readonly array $tags = [],
        public readonly array $rawResponse = [],
        public readonly ?string $providerKey = null,
        public readonly ?string $providerVersion = null,
        public readonly ?string $modelKey = null,
        public readonly ?string $modelSnapshot = null,
        public readonly ?string $promptVersion = null,
        public readonly ?string $responseSchemaVersion = null,
        public readonly ?string $modeApplied = null,
        public readonly ?int $tokensInput = null,
        public readonly ?int $tokensOutput = null,
    ) {}

    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $rawResponse
     */
    public static function approve(
        ?string $reason = null,
        ?string $shortCaption = null,
        array $tags = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $promptVersion = null,
        ?string $responseSchemaVersion = null,
        ?string $modeApplied = null,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
    ): self {
        return new self(
            decision: VisualReasoningDecision::Approve,
            reviewRequired: false,
            reason: $reason,
            shortCaption: $shortCaption,
            tags: $tags,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            promptVersion: $promptVersion,
            responseSchemaVersion: $responseSchemaVersion,
            modeApplied: $modeApplied,
            tokensInput: $tokensInput,
            tokensOutput: $tokensOutput,
        );
    }

    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $rawResponse
     */
    public static function review(
        ?string $reason = null,
        ?string $shortCaption = null,
        array $tags = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $promptVersion = null,
        ?string $responseSchemaVersion = null,
        ?string $modeApplied = null,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
    ): self {
        return new self(
            decision: VisualReasoningDecision::Review,
            reviewRequired: true,
            reason: $reason,
            shortCaption: $shortCaption,
            tags: $tags,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            promptVersion: $promptVersion,
            responseSchemaVersion: $responseSchemaVersion,
            modeApplied: $modeApplied,
            tokensInput: $tokensInput,
            tokensOutput: $tokensOutput,
        );
    }

    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $rawResponse
     */
    public static function reject(
        ?string $reason = null,
        ?string $shortCaption = null,
        array $tags = [],
        array $rawResponse = [],
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $promptVersion = null,
        ?string $responseSchemaVersion = null,
        ?string $modeApplied = null,
        ?int $tokensInput = null,
        ?int $tokensOutput = null,
    ): self {
        return new self(
            decision: VisualReasoningDecision::Reject,
            reviewRequired: false,
            reason: $reason,
            shortCaption: $shortCaption,
            tags: $tags,
            rawResponse: $rawResponse,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            promptVersion: $promptVersion,
            responseSchemaVersion: $responseSchemaVersion,
            modeApplied: $modeApplied,
            tokensInput: $tokensInput,
            tokensOutput: $tokensOutput,
        );
    }

    public static function skipped(
        ?string $providerKey = null,
        ?string $providerVersion = null,
        ?string $modelKey = null,
        ?string $modelSnapshot = null,
        ?string $promptVersion = null,
        ?string $responseSchemaVersion = null,
        ?string $modeApplied = null,
    ): self {
        return new self(
            decision: VisualReasoningDecision::Skipped,
            reviewRequired: false,
            providerKey: $providerKey,
            providerVersion: $providerVersion,
            modelKey: $modelKey,
            modelSnapshot: $modelSnapshot,
            promptVersion: $promptVersion,
            responseSchemaVersion: $responseSchemaVersion,
            modeApplied: $modeApplied,
        );
    }

    public function vlmStatus(): string
    {
        return match ($this->decision) {
            VisualReasoningDecision::Approve => 'completed',
            VisualReasoningDecision::Review => 'review',
            VisualReasoningDecision::Reject => 'rejected',
            VisualReasoningDecision::Skipped => 'skipped',
        };
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
            'prompt_version' => $this->promptVersion,
            'response_schema_version' => $this->responseSchemaVersion,
            'mode_applied' => $this->modeApplied,
            'decision' => $this->decision->value,
            'review_required' => $this->reviewRequired,
            'reason' => $this->reason,
            'short_caption' => $this->shortCaption,
            'tags_json' => $this->tags,
            'raw_response_json' => $this->rawResponse,
            'tokens_input' => $this->tokensInput,
            'tokens_output' => $this->tokensOutput,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toRunResult(): array
    {
        return [
            'decision' => $this->decision->value,
            'review_required' => $this->reviewRequired,
            'reason' => $this->reason,
            'short_caption' => $this->shortCaption,
            'tags' => $this->tags,
            'response_schema_version' => $this->responseSchemaVersion,
            'mode_applied' => $this->modeApplied,
        ];
    }
}

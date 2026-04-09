<?php

namespace App\Modules\MediaIntelligence\DTOs;

use App\Modules\MediaIntelligence\Enums\VisualReasoningDecision;

final class VisualReasoningEvaluationResult
{
    /**
     * @param array<int, string> $tags
     * @param array<string, mixed> $rawResponse
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $promptContext
     * @param array<int, string> $matchedPolicies
     * @param array<int, string> $matchedExceptions
     * @param array<int, string> $inputTypesConsidered
     */
    public function __construct(
        public readonly VisualReasoningDecision $decision,
        public readonly bool $reviewRequired = false,
        public readonly ?string $reason = null,
        public readonly ?string $reasonCode = null,
        public readonly array $matchedPolicies = [],
        public readonly array $matchedExceptions = [],
        public readonly ?string $inputScopeUsed = null,
        public readonly array $inputTypesConsidered = [],
        public readonly ?string $confidenceBand = null,
        public readonly ?string $publishEligibility = null,
        public readonly ?string $shortCaption = null,
        public readonly ?string $replyText = null,
        public readonly array $tags = [],
        public readonly array $rawResponse = [],
        public readonly array $requestPayload = [],
        public readonly ?array $promptContext = null,
        public readonly ?string $normalizedTextContext = null,
        public readonly ?string $normalizedTextContextMode = null,
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
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $promptContext
     * @param array<int, string> $matchedPolicies
     * @param array<int, string> $matchedExceptions
     * @param array<int, string> $inputTypesConsidered
     */
    public static function approve(
        ?string $reason = null,
        ?string $reasonCode = null,
        array $matchedPolicies = [],
        array $matchedExceptions = [],
        ?string $inputScopeUsed = null,
        array $inputTypesConsidered = [],
        ?string $confidenceBand = null,
        ?string $publishEligibility = null,
        ?string $shortCaption = null,
        ?string $replyText = null,
        array $tags = [],
        array $rawResponse = [],
        array $requestPayload = [],
        ?array $promptContext = null,
        ?string $normalizedTextContext = null,
        ?string $normalizedTextContextMode = null,
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
            reasonCode: $reasonCode,
            matchedPolicies: $matchedPolicies,
            matchedExceptions: $matchedExceptions,
            inputScopeUsed: $inputScopeUsed,
            inputTypesConsidered: $inputTypesConsidered,
            confidenceBand: $confidenceBand,
            publishEligibility: $publishEligibility,
            shortCaption: $shortCaption,
            replyText: $replyText,
            tags: $tags,
            rawResponse: $rawResponse,
            requestPayload: $requestPayload,
            promptContext: $promptContext,
            normalizedTextContext: $normalizedTextContext,
            normalizedTextContextMode: $normalizedTextContextMode,
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
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $promptContext
     * @param array<int, string> $matchedPolicies
     * @param array<int, string> $matchedExceptions
     * @param array<int, string> $inputTypesConsidered
     */
    public static function review(
        ?string $reason = null,
        ?string $reasonCode = null,
        array $matchedPolicies = [],
        array $matchedExceptions = [],
        ?string $inputScopeUsed = null,
        array $inputTypesConsidered = [],
        ?string $confidenceBand = null,
        ?string $publishEligibility = null,
        ?string $shortCaption = null,
        ?string $replyText = null,
        array $tags = [],
        array $rawResponse = [],
        array $requestPayload = [],
        ?array $promptContext = null,
        ?string $normalizedTextContext = null,
        ?string $normalizedTextContextMode = null,
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
            reasonCode: $reasonCode,
            matchedPolicies: $matchedPolicies,
            matchedExceptions: $matchedExceptions,
            inputScopeUsed: $inputScopeUsed,
            inputTypesConsidered: $inputTypesConsidered,
            confidenceBand: $confidenceBand,
            publishEligibility: $publishEligibility,
            shortCaption: $shortCaption,
            replyText: $replyText,
            tags: $tags,
            rawResponse: $rawResponse,
            requestPayload: $requestPayload,
            promptContext: $promptContext,
            normalizedTextContext: $normalizedTextContext,
            normalizedTextContextMode: $normalizedTextContextMode,
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
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $promptContext
     * @param array<int, string> $matchedPolicies
     * @param array<int, string> $matchedExceptions
     * @param array<int, string> $inputTypesConsidered
     */
    public static function reject(
        ?string $reason = null,
        ?string $reasonCode = null,
        array $matchedPolicies = [],
        array $matchedExceptions = [],
        ?string $inputScopeUsed = null,
        array $inputTypesConsidered = [],
        ?string $confidenceBand = null,
        ?string $publishEligibility = null,
        ?string $shortCaption = null,
        ?string $replyText = null,
        array $tags = [],
        array $rawResponse = [],
        array $requestPayload = [],
        ?array $promptContext = null,
        ?string $normalizedTextContext = null,
        ?string $normalizedTextContextMode = null,
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
            reasonCode: $reasonCode,
            matchedPolicies: $matchedPolicies,
            matchedExceptions: $matchedExceptions,
            inputScopeUsed: $inputScopeUsed,
            inputTypesConsidered: $inputTypesConsidered,
            confidenceBand: $confidenceBand,
            publishEligibility: $publishEligibility,
            shortCaption: $shortCaption,
            replyText: $replyText,
            tags: $tags,
            rawResponse: $rawResponse,
            requestPayload: $requestPayload,
            promptContext: $promptContext,
            normalizedTextContext: $normalizedTextContext,
            normalizedTextContextMode: $normalizedTextContextMode,
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
            'reason_code' => $this->reasonCode,
            'matched_policies_json' => $this->matchedPolicies,
            'matched_exceptions_json' => $this->matchedExceptions,
            'input_scope_used' => $this->inputScopeUsed,
            'input_types_considered_json' => $this->inputTypesConsidered,
            'confidence_band' => $this->confidenceBand,
            'publish_eligibility' => $this->publishEligibility,
            'short_caption' => $this->shortCaption,
            'reply_text' => $this->replyText,
            'tags_json' => $this->tags,
            'raw_response_json' => $this->rawResponse,
            'request_payload_json' => $this->requestPayload,
            'normalized_text_context' => $this->normalizedTextContext,
            'normalized_text_context_mode' => $this->normalizedTextContextMode,
            'prompt_context_json' => $this->promptContext,
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
            'reason_code' => $this->reasonCode,
            'matched_policies' => $this->matchedPolicies,
            'matched_exceptions' => $this->matchedExceptions,
            'input_scope_used' => $this->inputScopeUsed,
            'input_types_considered' => $this->inputTypesConsidered,
            'confidence_band' => $this->confidenceBand,
            'publish_eligibility' => $this->publishEligibility,
            'short_caption' => $this->shortCaption,
            'reply_text' => $this->replyText,
            'tags' => $this->tags,
            'response_schema_version' => $this->responseSchemaVersion,
            'mode_applied' => $this->modeApplied,
            'normalized_text_context' => $this->normalizedTextContext,
            'normalized_text_context_mode' => $this->normalizedTextContextMode,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withExecutionMetadata(array $metadata): self
    {
        $rawResponse = $this->rawResponse;
        $rawResponse['execution'] = array_merge(
            (array) ($rawResponse['execution'] ?? []),
            $metadata,
        );

        $promptContext = is_array($this->promptContext) ? $this->promptContext : [];
        $promptContext['execution'] = array_merge(
            (array) ($promptContext['execution'] ?? []),
            $metadata,
        );

        return new self(
            decision: $this->decision,
            reviewRequired: $this->reviewRequired,
            reason: $this->reason,
            reasonCode: $this->reasonCode,
            matchedPolicies: $this->matchedPolicies,
            matchedExceptions: $this->matchedExceptions,
            inputScopeUsed: $this->inputScopeUsed,
            inputTypesConsidered: $this->inputTypesConsidered,
            confidenceBand: $this->confidenceBand,
            publishEligibility: $this->publishEligibility,
            shortCaption: $this->shortCaption,
            replyText: $this->replyText,
            tags: $this->tags,
            rawResponse: $rawResponse,
            requestPayload: $this->requestPayload,
            promptContext: $promptContext,
            normalizedTextContext: $this->normalizedTextContext,
            normalizedTextContextMode: $this->normalizedTextContextMode,
            providerKey: $this->providerKey,
            providerVersion: $this->providerVersion,
            modelKey: $this->modelKey,
            modelSnapshot: $this->modelSnapshot,
            promptVersion: $this->promptVersion,
            responseSchemaVersion: $this->responseSchemaVersion,
            modeApplied: $this->modeApplied,
            tokensInput: $this->tokensInput,
            tokensOutput: $this->tokensOutput,
        );
    }
}

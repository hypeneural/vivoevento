<?php

namespace App\Modules\MediaIntelligence\Services;

class MediaReplyPromptTestSummaryService
{
    /**
     * @param array<int, array<string, mixed>> $safetyResults
     * @param array<int, array<string, mixed>> $contextualResults
     * @return array<string, mixed>
     */
    public function build(
        array $safetyResults,
        array $contextualResults,
        bool $safetyIsBlocking,
        bool $contextIsBlocking,
        bool $replySucceeded,
    ): array {
        $safetyCounts = $this->countByDecision($safetyResults, 'decision', [
            'pass',
            'review',
            'block',
            'error',
        ]);
        $contextCounts = $this->countByDecision($contextualResults, 'decision', [
            'approve',
            'review',
            'reject',
            'error',
        ]);

        $hasSafetyBlock = $this->containsDecision($safetyResults, 'decision', ['block']);
        $hasSafetyReview = $this->containsDecision($safetyResults, 'decision', ['review', 'error'])
            || $this->containsTruthy($safetyResults, 'review_required');
        $hasContextReject = $this->containsDecision($contextualResults, 'decision', ['reject'])
            || $this->containsDecision($contextualResults, 'publish_eligibility', ['reject']);
        $hasContextReview = $this->containsDecision($contextualResults, 'decision', ['review', 'error'])
            || $this->containsTruthy($contextualResults, 'review_required')
            || $this->containsDecision($contextualResults, 'publish_eligibility', ['review_only']);

        $blockingLayers = [];

        if ($safetyIsBlocking && $hasSafetyBlock) {
            $blockingLayers[] = 'safety';
        }

        if ($contextIsBlocking && $hasContextReject) {
            $blockingLayers[] = 'context';
        }

        if ($blockingLayers !== []) {
            $finalPublishEligibility = 'reject';
            $finalEffectiveState = 'rejected';
            $humanReason = $this->firstNonEmpty(
                $contextualResults,
                'reason',
                'Safety ou contexto bloquearam a midia no laboratorio.',
            );
        } elseif (
            ($safetyIsBlocking && $hasSafetyReview)
            || ($contextIsBlocking && $hasContextReview)
        ) {
            $finalPublishEligibility = 'review_only';
            $finalEffectiveState = 'pending_moderation';
            $humanReason = 'A homologacao sugere revisao manual antes de publicar.';
        } else {
            $finalPublishEligibility = 'auto_publish';
            $finalEffectiveState = 'approved';
            $humanReason = 'A homologacao sugere publicacao automatica com a politica atual.';
        }

        $reasonCodes = array_values(array_unique(array_filter(array_merge(
            $this->pluckStrings($safetyResults, 'reason_codes'),
            $this->pluckStrings($contextualResults, 'reason_code'),
        ))));

        $evaluationErrorsCount = $safetyCounts['error']
            + $contextCounts['error']
            + ($replySucceeded ? 0 : 1);

        return [
            'images_evaluated' => max(count($safetyResults), count($contextualResults)),
            'reply_status' => $replySucceeded ? 'success' : 'failed',
            'safety_is_blocking' => $safetyIsBlocking,
            'context_is_blocking' => $contextIsBlocking,
            'safety_counts' => $safetyCounts,
            'context_counts' => $contextCounts,
            'blocking_layers' => $blockingLayers,
            'reason_codes' => $reasonCodes,
            'evaluation_errors_count' => $evaluationErrorsCount,
            'final_publish_eligibility' => $finalPublishEligibility,
            'final_effective_state' => $finalEffectiveState,
            'human_reason' => $humanReason,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $expected
     * @return array<string, int>
     */
    private function countByDecision(array $rows, string $field, array $expected): array
    {
        $counts = array_fill_keys($expected, 0);

        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if (is_string($value) && array_key_exists($value, $counts)) {
                $counts[$value]++;
            }
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $expected
     */
    private function containsDecision(array $rows, string $field, array $expected): bool
    {
        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if (is_string($value) && in_array($value, $expected, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function containsTruthy(array $rows, string $field): bool
    {
        foreach ($rows as $row) {
            if (($row[$field] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function firstNonEmpty(array $rows, string $field, string $fallback): string
    {
        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function pluckStrings(array $rows, string $field): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $values[] = trim($item);
                }
            }
        }

        return $values;
    }
}

<?php

namespace App\Modules\ContentModeration\Support;

use App\Modules\ContentModeration\Enums\ContentSafetyDecision;

class ContentSafetyThresholdEvaluator
{
    /**
     * @param array<string, float|int|string|bool|null> $categoryScores
     * @param array<string, mixed> $hardBlockThresholds
     * @param array<string, mixed> $reviewThresholds
     * @return array{decision: ContentSafetyDecision, reason_codes: array<int, string>}
     */
    public function evaluate(
        array $categoryScores,
        array $hardBlockThresholds,
        array $reviewThresholds,
    ): array {
        $blockReasons = [];
        $reviewReasons = [];

        foreach ($categoryScores as $category => $score) {
            $normalizedScore = $this->normalizeScore($score);

            if ($normalizedScore === null) {
                continue;
            }

            $blockThreshold = $this->normalizeScore($hardBlockThresholds[$category] ?? null);

            if ($blockThreshold !== null && $normalizedScore >= $blockThreshold) {
                $blockReasons[] = "{$category}.block";
                continue;
            }

            $reviewThreshold = $this->normalizeScore($reviewThresholds[$category] ?? null);

            if ($reviewThreshold !== null && $normalizedScore >= $reviewThreshold) {
                $reviewReasons[] = "{$category}.review";
            }
        }

        if ($blockReasons !== []) {
            return [
                'decision' => ContentSafetyDecision::Block,
                'reason_codes' => $blockReasons,
            ];
        }

        if ($reviewReasons !== []) {
            return [
                'decision' => ContentSafetyDecision::Review,
                'reason_codes' => $reviewReasons,
            ];
        }

        return [
            'decision' => ContentSafetyDecision::Pass,
            'reason_codes' => [],
        ];
    }

    private function normalizeScore(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if ($float < 0.0 || $float > 1.0) {
            return null;
        }

        return round($float, 6);
    }
}

<?php

use App\Modules\ContentModeration\Enums\ContentSafetyDecision;
use App\Modules\ContentModeration\Support\ContentSafetyThresholdEvaluator;

it('returns pass when no threshold is crossed', function () {
    $result = app(ContentSafetyThresholdEvaluator::class)->evaluate(
        [
            'nudity' => 0.18,
            'violence' => 0.21,
        ],
        [
            'nudity' => 0.90,
            'violence' => 0.90,
        ],
        [
            'nudity' => 0.60,
            'violence' => 0.60,
        ],
    );

    expect($result['decision'])->toBe(ContentSafetyDecision::Pass)
        ->and($result['reason_codes'])->toBe([]);
});

it('returns review when a review threshold is crossed', function () {
    $result = app(ContentSafetyThresholdEvaluator::class)->evaluate(
        [
            'nudity' => 0.66,
            'violence' => 0.10,
        ],
        [
            'nudity' => 0.90,
            'violence' => 0.90,
        ],
        [
            'nudity' => 0.60,
            'violence' => 0.60,
        ],
    );

    expect($result['decision'])->toBe(ContentSafetyDecision::Review)
        ->and($result['reason_codes'])->toBe(['nudity.review']);
});

it('returns block when a hard block threshold is crossed', function () {
    $result = app(ContentSafetyThresholdEvaluator::class)->evaluate(
        [
            'nudity' => 0.95,
            'violence' => 0.10,
        ],
        [
            'nudity' => 0.90,
            'violence' => 0.90,
        ],
        [
            'nudity' => 0.60,
            'violence' => 0.60,
        ],
    );

    expect($result['decision'])->toBe(ContentSafetyDecision::Block)
        ->and($result['reason_codes'])->toBe(['nudity.block']);
});

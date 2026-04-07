<?php

namespace Database\Factories;

use App\Modules\ContentModeration\Enums\ContentSafetyDecision;
use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaSafetyEvaluationFactory extends Factory
{
    protected $model = EventMediaSafetyEvaluation::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_media_id' => EventMediaFactory::new(),
            'provider_key' => 'noop',
            'provider_version' => 'foundation-v1',
            'model_key' => 'noop-safety-v1',
            'model_snapshot' => 'noop-safety-v1',
            'threshold_version' => 'foundation-v1',
            'decision' => ContentSafetyDecision::Pass->value,
            'blocked' => false,
            'review_required' => false,
            'category_scores_json' => [
                'nudity' => 0.0,
                'violence' => 0.0,
            ],
            'provider_categories_json' => null,
            'provider_category_scores_json' => null,
            'provider_category_input_types_json' => null,
            'normalized_provider_json' => null,
            'reason_codes_json' => [],
            'raw_response_json' => [
                'provider' => 'noop',
            ],
            'completed_at' => now(),
        ];
    }
}

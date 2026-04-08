<?php

namespace Database\Factories;

use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventContentModerationSettingFactory extends Factory
{
    protected $model = EventContentModerationSetting::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'provider_key' => 'noop',
            'mode' => 'enforced',
            'threshold_version' => 'foundation-v1',
            'hard_block_thresholds_json' => [
                'nudity' => 0.9,
                'violence' => 0.9,
                'self_harm' => 0.9,
            ],
            'review_thresholds_json' => [
                'nudity' => 0.6,
                'violence' => 0.6,
                'self_harm' => 0.6,
            ],
            'fallback_mode' => 'review',
            'analysis_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'enabled' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaIntelligenceSettingFactory extends Factory
{
    protected $model = EventMediaIntelligenceSetting::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'enabled' => true,
            'mode' => 'enrich_only',
            'prompt_version' => 'foundation-v1',
            'approval_prompt' => EventMediaIntelligenceSetting::defaultApprovalPrompt(),
            'caption_style_prompt' => EventMediaIntelligenceSetting::defaultCaptionStylePrompt(),
            'response_schema_version' => 'foundation-v1',
            'timeout_ms' => 12000,
            'fallback_mode' => 'review',
            'require_json_output' => true,
            'reply_text_enabled' => false,
            'reply_text_mode' => 'disabled',
            'reply_prompt_override' => null,
            'reply_fixed_templates_json' => [],
            'reply_prompt_preset_id' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'enabled' => false,
        ]);
    }

    public function gate(): static
    {
        return $this->state(fn () => [
            'mode' => 'gate',
            'fallback_mode' => 'review',
        ]);
    }
}

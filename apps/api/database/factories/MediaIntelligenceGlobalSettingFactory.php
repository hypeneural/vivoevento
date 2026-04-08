<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaIntelligenceGlobalSettingFactory extends Factory
{
    protected $model = MediaIntelligenceGlobalSetting::class;

    public function definition(): array
    {
        return [
            'reply_text_prompt' => MediaIntelligenceGlobalSetting::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [],
            'reply_prompt_preset_id' => null,
            'reply_ai_rate_limit_enabled' => false,
            'reply_ai_rate_limit_max_messages' => 10,
            'reply_ai_rate_limit_window_minutes' => 10,
        ];
    }
}

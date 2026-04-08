<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaIntelligenceGlobalSettingFactory extends Factory
{
    protected $model = MediaIntelligenceGlobalSetting::class;

    public function definition(): array
    {
        return MediaIntelligenceGlobalSetting::defaultAttributes();
    }
}

<?php

namespace Database\Factories;

use App\Modules\ContentModeration\Models\ContentModerationGlobalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentModerationGlobalSettingFactory extends Factory
{
    protected $model = ContentModerationGlobalSetting::class;

    public function definition(): array
    {
        return ContentModerationGlobalSetting::defaultAttributes();
    }
}

<?php

namespace App\Modules\ContentModeration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentModerationGlobalSetting extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return EventContentModerationSetting::defaultAttributes();
    }

    protected $fillable = [
        'provider_key',
        'mode',
        'threshold_version',
        'hard_block_thresholds_json',
        'review_thresholds_json',
        'fallback_mode',
        'analysis_scope',
        'normalized_text_context_mode',
        'enabled',
    ];

    protected $casts = [
        'hard_block_thresholds_json' => 'array',
        'review_thresholds_json' => 'array',
        'enabled' => 'boolean',
    ];

    protected static function newFactory(): \Database\Factories\ContentModerationGlobalSettingFactory
    {
        return \Database\Factories\ContentModerationGlobalSettingFactory::new();
    }
}

<?php

namespace App\Modules\ContentModeration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventContentModerationSetting extends Model
{
    use HasFactory;

    public const DEFAULT_THRESHOLD_VERSION = 'foundation-v1';

    /**
     * @return array<string, float>
     */
    public static function defaultHardBlockThresholds(): array
    {
        return [
            'nudity' => 0.90,
            'violence' => 0.90,
            'self_harm' => 0.90,
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function defaultReviewThresholds(): array
    {
        return [
            'nudity' => 0.60,
            'violence' => 0.60,
            'self_harm' => 0.60,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'provider_key' => (string) config('content_moderation.default_provider', 'openai'),
            'mode' => 'enforced',
            'threshold_version' => self::DEFAULT_THRESHOLD_VERSION,
            'hard_block_thresholds_json' => self::defaultHardBlockThresholds(),
            'review_thresholds_json' => self::defaultReviewThresholds(),
            'fallback_mode' => 'review',
            'analysis_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'enabled' => false,
        ];
    }

    protected $fillable = [
        'event_id',
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

    protected static function newFactory(): \Database\Factories\EventContentModerationSettingFactory
    {
        return \Database\Factories\EventContentModerationSettingFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}

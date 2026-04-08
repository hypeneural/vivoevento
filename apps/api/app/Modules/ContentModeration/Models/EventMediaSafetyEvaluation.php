<?php

namespace App\Modules\ContentModeration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaSafetyEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_media_id',
        'provider_key',
        'provider_version',
        'model_key',
        'model_snapshot',
        'threshold_version',
        'decision',
        'blocked',
        'review_required',
        'category_scores_json',
        'provider_categories_json',
        'provider_category_scores_json',
        'provider_category_input_types_json',
        'normalized_provider_json',
        'reason_codes_json',
        'raw_response_json',
        'request_payload_json',
        'normalized_text_context',
        'normalized_text_context_mode',
        'policy_snapshot_json',
        'policy_sources_json',
        'completed_at',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'review_required' => 'boolean',
        'category_scores_json' => 'array',
        'provider_categories_json' => 'array',
        'provider_category_scores_json' => 'array',
        'provider_category_input_types_json' => 'array',
        'normalized_provider_json' => 'array',
        'reason_codes_json' => 'array',
        'raw_response_json' => 'array',
        'request_payload_json' => 'array',
        'policy_snapshot_json' => 'array',
        'policy_sources_json' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventMediaSafetyEvaluationFactory
    {
        return \Database\Factories\EventMediaSafetyEvaluationFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function eventMedia(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class);
    }
}

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
        'reason_codes_json',
        'raw_response_json',
        'completed_at',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'review_required' => 'boolean',
        'category_scores_json' => 'array',
        'reason_codes_json' => 'array',
        'raw_response_json' => 'array',
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

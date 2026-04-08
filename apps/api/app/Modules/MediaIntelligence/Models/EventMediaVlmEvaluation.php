<?php

namespace App\Modules\MediaIntelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaVlmEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_media_id',
        'provider_key',
        'provider_version',
        'model_key',
        'model_snapshot',
        'prompt_version',
        'response_schema_version',
        'mode_applied',
        'decision',
        'review_required',
        'reason',
        'short_caption',
        'reply_text',
        'tags_json',
        'raw_response_json',
        'request_payload_json',
        'normalized_text_context',
        'normalized_text_context_mode',
        'prompt_context_json',
        'policy_snapshot_json',
        'policy_sources_json',
        'tokens_input',
        'tokens_output',
        'completed_at',
    ];

    protected $casts = [
        'review_required' => 'boolean',
        'tags_json' => 'array',
        'raw_response_json' => 'array',
        'request_payload_json' => 'array',
        'prompt_context_json' => 'array',
        'policy_snapshot_json' => 'array',
        'policy_sources_json' => 'array',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'completed_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventMediaVlmEvaluationFactory
    {
        return \Database\Factories\EventMediaVlmEvaluationFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }
}

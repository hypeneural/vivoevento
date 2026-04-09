<?php

namespace App\Modules\MediaIntelligence\Models;

use App\Models\User;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaReplyTestRun extends Model
{
    use HasFactory;

    protected $table = 'ai_media_reply_test_runs';

    protected $fillable = [
        'trace_id',
        'user_id',
        'event_id',
        'preset_id',
        'provider_key',
        'model_key',
        'status',
        'prompt_template',
        'prompt_resolved',
        'prompt_variables_json',
        'images_json',
        'safety_results_json',
        'contextual_results_json',
        'final_summary_json',
        'policy_snapshot_json',
        'policy_sources_json',
        'request_payload_json',
        'response_payload_json',
        'response_text',
        'latency_ms',
        'error_message',
    ];

    protected $casts = [
        'prompt_variables_json' => 'array',
        'images_json' => 'array',
        'safety_results_json' => 'array',
        'contextual_results_json' => 'array',
        'final_summary_json' => 'array',
        'policy_snapshot_json' => 'array',
        'policy_sources_json' => 'array',
        'request_payload_json' => 'array',
        'response_payload_json' => 'array',
        'latency_ms' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(MediaReplyPromptPreset::class, 'preset_id');
    }

    protected static function newFactory(): \Database\Factories\MediaReplyTestRunFactory
    {
        return \Database\Factories\MediaReplyTestRunFactory::new();
    }
}

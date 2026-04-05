<?php

namespace App\Modules\MediaProcessing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaProcessingRun extends Model
{
    protected $fillable = [
        'event_media_id', 'run_type', 'stage_key', 'provider_key', 'provider_version',
        'model_key', 'model_snapshot', 'input_ref', 'decision_key', 'queue_name',
        'worker_ref', 'result_json', 'metrics_json', 'cost_units', 'idempotency_key',
        'status', 'attempts', 'error_message', 'failure_class', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'result_json' => 'array',
        'metrics_json' => 'array',
        'cost_units' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(EventMedia::class, 'event_media_id');
    }
}

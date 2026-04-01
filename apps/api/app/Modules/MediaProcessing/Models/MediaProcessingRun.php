<?php

namespace App\Modules\MediaProcessing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaProcessingRun extends Model
{
    protected $fillable = [
        'event_media_id', 'run_type', 'status', 'attempts',
        'error_message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(EventMedia::class, 'event_media_id');
    }
}

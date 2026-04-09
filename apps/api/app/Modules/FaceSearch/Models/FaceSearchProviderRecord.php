<?php

namespace App\Modules\FaceSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaceSearchProviderRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'face_search_provider_records';

    protected $guarded = [];

    protected $casts = [
        'bbox_json' => 'array',
        'landmarks_json' => 'array',
        'pose_json' => 'array',
        'quality_json' => 'array',
        'unindexed_reasons_json' => 'array',
        'provider_payload_json' => 'array',
        'searchable' => 'boolean',
        'indexed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\FaceSearchProviderRecordFactory
    {
        return \Database\Factories\FaceSearchProviderRecordFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function eventMedia(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }
}

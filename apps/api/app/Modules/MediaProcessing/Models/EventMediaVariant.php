<?php

namespace App\Modules\MediaProcessing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventMediaVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_media_id', 'variant_key', 'disk', 'path',
        'width', 'height', 'size_bytes', 'mime_type',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(EventMedia::class, 'event_media_id');
    }
}

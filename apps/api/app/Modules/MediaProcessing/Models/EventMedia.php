<?php

namespace App\Modules\MediaProcessing\Models;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventMedia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'event_media';

    protected static function newFactory(): \Database\Factories\EventMediaFactory
    {
        return \Database\Factories\EventMediaFactory::new();
    }

    protected $fillable = [
        'event_id', 'inbound_message_id', 'uploaded_by_user_id',
        'media_type', 'source_type', 'source_label', 'title', 'caption',
        'original_filename', 'mime_type', 'size_bytes', 'width', 'height',
        'duration_seconds', 'checksum', 'processing_status', 'moderation_status',
        'publication_status', 'is_featured', 'sort_order', 'published_at',
    ];

    protected $casts = [
        'processing_status' => MediaProcessingStatus::class,
        'moderation_status' => ModerationStatus::class,
        'publication_status' => PublicationStatus::class,
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'published_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\InboundMedia\Models\InboundMessage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EventMediaVariant::class);
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(MediaProcessingRun::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('moderation_status', ModerationStatus::Approved);
    }

    public function scopePublished($query)
    {
        return $query->where('publication_status', PublicationStatus::Published);
    }
}

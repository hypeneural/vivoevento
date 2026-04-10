<?php

namespace App\Modules\MediaProcessing\Models;

use App\Modules\MediaProcessing\Enums\MediaDecisionSource;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Services\ModerationSearchDocumentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

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
        'original_filename', 'original_disk', 'original_path', 'client_filename',
        'perceptual_hash', 'duplicate_group_key',
        'mime_type', 'size_bytes', 'width', 'height',
        'duration_seconds', 'has_audio', 'video_codec', 'audio_codec', 'bitrate', 'container',
        'checksum', 'processing_status', 'moderation_status',
        'publication_status', 'safety_status', 'face_index_status', 'vlm_status',
        'decision_source', 'decision_overridden_at', 'decision_overridden_by_user_id', 'decision_override_reason',
        'moderation_search_document',
        'pipeline_version', 'last_pipeline_error_code', 'last_pipeline_error_message',
        'is_featured', 'sort_order', 'published_at',
    ];

    protected $casts = [
        'processing_status' => MediaProcessingStatus::class,
        'moderation_status' => ModerationStatus::class,
        'publication_status' => PublicationStatus::class,
        'decision_source' => MediaDecisionSource::class,
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'has_audio' => 'boolean',
        'bitrate' => 'integer',
        'decision_overridden_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (EventMedia $media) {
            if (! Schema::hasColumn('event_media', 'moderation_search_document')) {
                return;
            }

            if (! $media->shouldRefreshModerationSearchDocument()) {
                return;
            }

            $media->moderation_search_document = app(ModerationSearchDocumentBuilder::class)
                ->buildForMedia($media);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\InboundMedia\Models\InboundMessage::class);
    }

    public function decisionOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'decision_overridden_by_user_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EventMediaVariant::class);
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(MediaProcessingRun::class);
    }

    public function safetyEvaluations(): HasMany
    {
        return $this->hasMany(\App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation::class);
    }

    public function latestSafetyEvaluation(): HasOne
    {
        return $this->hasOne(\App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation::class)
            ->latestOfMany();
    }

    public function vlmEvaluations(): HasMany
    {
        return $this->hasMany(\App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation::class);
    }

    public function latestVlmEvaluation(): HasOne
    {
        return $this->hasOne(\App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation::class)
            ->latestOfMany();
    }

    public function latestVlmRun(): HasOne
    {
        return $this->hasOne(MediaProcessingRun::class)
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('stage_key', 'vlm');
            });
    }

    public function faces(): HasMany
    {
        return $this->hasMany(\App\Modules\FaceSearch\Models\EventMediaFace::class, 'event_media_id');
    }

    public function originalStorageDisk(): string
    {
        return $this->original_disk ?: 'public';
    }

    public function originalStoragePath(): ?string
    {
        if ($this->original_path) {
            return $this->original_path;
        }

        if (! $this->original_filename) {
            return null;
        }

        return str_contains($this->original_filename, '/')
            ? $this->original_filename
            : "events/{$this->event_id}/originals/{$this->original_filename}";
    }

    public function displayFilename(): ?string
    {
        return $this->client_filename ?: $this->original_filename;
    }

    private function shouldRefreshModerationSearchDocument(): bool
    {
        if (! $this->exists || ! $this->moderation_search_document) {
            return true;
        }

        return $this->isDirty([
            'event_id',
            'inbound_message_id',
            'caption',
            'title',
            'source_label',
            'original_filename',
            'client_filename',
        ]);
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

<?php

namespace App\Modules\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGallerySetting extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventGallerySettingFactory
    {
        return \Database\Factories\EventGallerySettingFactory::new();
    }

    protected $fillable = [
        'event_id',
        'is_enabled',
        'event_type_family',
        'style_skin',
        'behavior_profile',
        'theme_key',
        'layout_key',
        'theme_tokens_json',
        'page_schema_json',
        'media_behavior_json',
        'current_preset_origin_json',
        'current_draft_revision_id',
        'current_published_revision_id',
        'preview_revision_id',
        'draft_version',
        'published_version',
        'preview_share_token',
        'preview_share_expires_at',
        'last_autosaved_at',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'theme_tokens_json' => 'array',
        'page_schema_json' => 'array',
        'media_behavior_json' => 'array',
        'current_preset_origin_json' => 'array',
        'draft_version' => 'integer',
        'published_version' => 'integer',
        'preview_share_expires_at' => 'datetime',
        'last_autosaved_at' => 'datetime',
        'updated_by' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(EventGalleryRevision::class, 'event_id', 'event_id');
    }

    public function currentDraftRevision(): BelongsTo
    {
        return $this->belongsTo(EventGalleryRevision::class, 'current_draft_revision_id');
    }

    public function currentPublishedRevision(): BelongsTo
    {
        return $this->belongsTo(EventGalleryRevision::class, 'current_published_revision_id');
    }

    public function previewRevision(): BelongsTo
    {
        return $this->belongsTo(EventGalleryRevision::class, 'preview_revision_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'updated_by');
    }

    /**
     * @return array<string, mixed>
     */
    public function toBuilderPayload(): array
    {
        return [
            'event_type_family' => $this->event_type_family,
            'style_skin' => $this->style_skin,
            'behavior_profile' => $this->behavior_profile,
            'theme_key' => $this->theme_key,
            'layout_key' => $this->layout_key,
            'theme_tokens' => is_array($this->theme_tokens_json) ? $this->theme_tokens_json : [],
            'page_schema' => is_array($this->page_schema_json) ? $this->page_schema_json : [],
            'media_behavior' => is_array($this->media_behavior_json) ? $this->media_behavior_json : [],
        ];
    }
}

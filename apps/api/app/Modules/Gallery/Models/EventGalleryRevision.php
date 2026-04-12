<?php

namespace App\Modules\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGalleryRevision extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\EventGalleryRevisionFactory
    {
        return \Database\Factories\EventGalleryRevisionFactory::new();
    }

    protected $fillable = [
        'event_id',
        'version_number',
        'kind',
        'event_type_family',
        'style_skin',
        'behavior_profile',
        'theme_key',
        'layout_key',
        'theme_tokens_json',
        'page_schema_json',
        'media_behavior_json',
        'change_summary_json',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'theme_tokens_json' => 'array',
        'page_schema_json' => 'array',
        'media_behavior_json' => 'array',
        'change_summary_json' => 'array',
        'created_by' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
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

<?php

namespace App\Modules\Gallery\Models;

use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryPreset extends Model
{
    use HasFactory;
    use HasOrganization;

    protected static function newFactory(): \Database\Factories\GalleryPresetFactory
    {
        return \Database\Factories\GalleryPresetFactory::new();
    }

    protected $fillable = [
        'organization_id',
        'created_by',
        'source_event_id',
        'name',
        'slug',
        'description',
        'event_type_family',
        'style_skin',
        'behavior_profile',
        'theme_key',
        'layout_key',
        'theme_tokens_json',
        'page_schema_json',
        'media_behavior_json',
        'derived_preset_key',
    ];

    protected $casts = [
        'theme_tokens_json' => 'array',
        'page_schema_json' => 'array',
        'media_behavior_json' => 'array',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class, 'source_event_id');
    }
}

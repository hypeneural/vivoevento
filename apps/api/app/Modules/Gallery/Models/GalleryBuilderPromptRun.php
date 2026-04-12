<?php

namespace App\Modules\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryBuilderPromptRun extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\GalleryBuilderPromptRunFactory
    {
        return \Database\Factories\GalleryBuilderPromptRunFactory::new();
    }

    protected $fillable = [
        'event_id',
        'organization_id',
        'user_id',
        'prompt_text',
        'persona_key',
        'event_type_key',
        'target_layer',
        'base_preset_key',
        'request_payload_json',
        'response_payload_json',
        'selected_variation_id',
        'response_schema_version',
        'status',
        'provider_key',
        'model_key',
    ];

    protected $casts = [
        'request_payload_json' => 'array',
        'response_payload_json' => 'array',
        'response_schema_version' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organizations\Models\Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class);
    }
}

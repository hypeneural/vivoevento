<?php

namespace App\Modules\Hub\Models;

use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubPreset extends Model
{
    use HasFactory;
    use HasOrganization;

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'source_event_id',
        'name',
        'description',
        'theme_key',
        'layout_key',
        'preset_payload_json',
    ];

    protected $casts = [
        'preset_payload_json' => 'array',
    ];

    protected static function newFactory(): \Database\Factories\HubPresetFactory
    {
        return \Database\Factories\HubPresetFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by_user_id');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class, 'source_event_id');
    }
}

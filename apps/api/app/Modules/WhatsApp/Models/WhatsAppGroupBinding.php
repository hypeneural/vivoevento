<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppGroupBinding extends Model
{
    use HasFactory, HasOrganization;

    protected $fillable = [
        'organization_id',
        'event_id',
        'instance_id',
        'group_external_id',
        'group_name',
        'binding_type',
        'is_active',
        'metadata_json',
    ];

    protected $casts = [
        'binding_type' => GroupBindingType::class,
        'is_active' => 'boolean',
        'metadata_json' => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForGroup($query, string $groupExternalId)
    {
        return $query->where('group_external_id', $groupExternalId);
    }

    public function scopeOfType($query, GroupBindingType $type)
    {
        return $query->where('binding_type', $type);
    }
}

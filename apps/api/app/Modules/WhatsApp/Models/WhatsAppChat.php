<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\ChatType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'external_chat_id',
        'type',
        'phone',
        'group_id',
        'display_name',
        'is_group',
        'last_message_at',
        'metadata_json',
    ];

    protected $casts = [
        'type' => ChatType::class,
        'is_group' => 'boolean',
        'last_message_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'chat_id');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_group', false);
    }
}

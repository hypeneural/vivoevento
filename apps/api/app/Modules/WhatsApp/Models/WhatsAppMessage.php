<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'chat_id',
        'direction',
        'provider_message_id',
        'provider_zaap_id',
        'reply_to_provider_message_id',
        'type',
        'text_body',
        'media_url',
        'mime_type',
        'status',
        'sender_phone',
        'recipient_phone',
        'payload_json',
        'normalized_payload_json',
        'sent_at',
        'received_at',
        'failed_at',
    ];

    protected $casts = [
        'direction' => MessageDirection::class,
        'type' => MessageType::class,
        'status' => MessageStatus::class,
        'payload_json' => 'array',
        'normalized_payload_json' => 'array',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChat::class, 'chat_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(WhatsAppDispatchLog::class, 'message_id');
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function isOutbound(): bool
    {
        return $this->direction === MessageDirection::Outbound;
    }

    public function hasMedia(): bool
    {
        return $this->type->hasMedia();
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeInbound($query)
    {
        return $query->where('direction', MessageDirection::Inbound);
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', MessageDirection::Outbound);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', MessageStatus::Failed);
    }
}

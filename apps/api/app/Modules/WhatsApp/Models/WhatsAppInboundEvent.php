<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\InboundEventStatus;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInboundEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'provider_key',
        'external_event_id',
        'provider_message_id',
        'event_type',
        'payload_json',
        'normalized_json',
        'processing_status',
        'received_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'provider_key' => WhatsAppProviderKey::class,
        'processing_status' => InboundEventStatus::class,
        'payload_json' => 'array',
        'normalized_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    // ─── Helpers ───────────────────────────────────────────

    public function markProcessed(): void
    {
        $this->update([
            'processing_status' => InboundEventStatus::Processed,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'processing_status' => InboundEventStatus::Failed,
            'error_message' => $error,
        ]);
    }

    public function markIgnored(): void
    {
        $this->update([
            'processing_status' => InboundEventStatus::Ignored,
            'processed_at' => now(),
        ]);
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('processing_status', InboundEventStatus::Pending);
    }
}

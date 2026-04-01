<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDispatchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'message_id',
        'provider_key',
        'endpoint_used',
        'request_json',
        'response_json',
        'http_status',
        'success',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'provider_key' => WhatsAppProviderKey::class,
        'request_json' => 'array',
        'response_json' => 'array',
        'http_status' => 'integer',
        'success' => 'boolean',
        'duration_ms' => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'message_id');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }
}

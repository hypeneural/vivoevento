<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Shared\Concerns\HasOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WhatsAppInstance extends Model
{
    use HasFactory, SoftDeletes, HasOrganization;

    protected $fillable = [
        'uuid',
        'organization_id',
        'provider_id',
        'provider_key',
        'name',
        'external_instance_id',
        'provider_token',
        'provider_client_token',
        'phone_number',
        'status',
        'connected_at',
        'disconnected_at',
        'last_status_sync_at',
        'webhook_secret',
        'settings_json',
        'created_by',
    ];

    protected $casts = [
        'provider_key' => WhatsAppProviderKey::class,
        'status' => InstanceStatus::class,
        'provider_token' => 'encrypted',
        'provider_client_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'settings_json' => 'array',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_status_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_token',
        'provider_client_token',
        'webhook_secret',
    ];

    // ─── Boot ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (WhatsAppInstance $instance) {
            if (empty($instance->uuid)) {
                $instance->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(WhatsAppChat::class, 'instance_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'instance_id');
    }

    public function inboundEvents(): HasMany
    {
        return $this->hasMany(WhatsAppInboundEvent::class, 'instance_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(WhatsAppDispatchLog::class, 'instance_id');
    }

    public function groupBindings(): HasMany
    {
        return $this->hasMany(WhatsAppGroupBinding::class, 'instance_id');
    }

    // ─── Helpers ───────────────────────────────────────────

    public function isConnected(): bool
    {
        return $this->status === InstanceStatus::Connected;
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeConnected($query)
    {
        return $query->where('status', InstanceStatus::Connected);
    }

    public function scopeForProvider($query, WhatsAppProviderKey|string $providerKey)
    {
        $key = $providerKey instanceof WhatsAppProviderKey ? $providerKey->value : $providerKey;

        return $query->where('provider_key', $key);
    }
}

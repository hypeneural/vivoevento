<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Shared\Concerns\HasOrganization;
use App\Shared\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WhatsAppInstance extends Model
{
    use HasFactory, SoftDeletes, HasOrganization;

    protected static function newFactory(): \Database\Factories\WhatsAppInstanceFactory
    {
        return \Database\Factories\WhatsAppInstanceFactory::new();
    }

    protected $table = 'whatsapp_instances';

    protected $fillable = [
        'uuid',
        'organization_id',
        'provider_id',
        'provider_key',
        'name',
        'instance_name',
        'external_instance_id',
        'provider_token',
        'provider_client_token',
        'provider_config_json',
        'provider_meta_json',
        'phone_number',
        'is_active',
        'is_default',
        'status',
        'connected_at',
        'disconnected_at',
        'last_status_sync_at',
        'last_health_check_at',
        'last_health_status',
        'last_error',
        'webhook_secret',
        'settings_json',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'provider_key' => WhatsAppProviderKey::class,
        'status' => InstanceStatus::class,
        'provider_token' => 'encrypted',
        'provider_client_token' => 'encrypted',
        'provider_config_json' => 'encrypted:array',
        'provider_meta_json' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'webhook_secret' => 'encrypted',
        'settings_json' => 'array',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'last_status_sync_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    protected $hidden = [
        'provider_token',
        'provider_client_token',
        'provider_config_json',
        'webhook_secret',
    ];

    protected static function booted(): void
    {
        static::creating(function (WhatsAppInstance $instance) {
            if (empty($instance->uuid)) {
                $instance->uuid = (string) Str::uuid();
            }

            if (empty($instance->instance_name)) {
                $instance->instance_name = $instance->external_instance_id
                    ?: Str::slug((string) $instance->name, '_');
            }
        });

        static::saving(function (WhatsAppInstance $instance) {
            if (empty($instance->instance_name)) {
                $instance->instance_name = $instance->external_instance_id
                    ?: Str::slug((string) $instance->name, '_');
            }

            $instance->syncLegacyProviderFields();
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(WhatsAppProvider::class, 'provider_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Users\Models\User::class, 'updated_by');
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

    public function isConnected(): bool
    {
        return $this->normalizedStatus() === InstanceStatus::Connected;
    }

    public function isOperational(): bool
    {
        return $this->normalizedStatus()->isOperational();
    }

    public function normalizedStatus(): InstanceStatus
    {
        return $this->status?->normalized() ?? InstanceStatus::Draft;
    }

    public function providerKeyValue(): string
    {
        return $this->provider_key instanceof WhatsAppProviderKey
            ? $this->provider_key->value
            : (string) $this->provider_key;
    }

    public function providerLabel(): string
    {
        return $this->provider_key instanceof WhatsAppProviderKey
            ? $this->provider_key->label()
            : ucfirst($this->providerKeyValue());
    }

    public function providerInstanceKey(): string
    {
        $config = $this->providerConfig();

        return match ($this->providerKeyValue()) {
            'evolution' => (string) ($config['external_instance_name'] ?? $this->instance_name ?? $this->external_instance_id),
            default => (string) ($config['instance_id'] ?? $this->external_instance_id),
        };
    }

    public function providerConfig(): array
    {
        $config = $this->provider_config_json ?? [];

        if (is_array($config) && $config !== []) {
            return $config;
        }

        return match ($this->providerKeyValue()) {
            'evolution' => array_filter([
                'server_url' => Arr::get($this->settings_json, 'server_url'),
                'auth_type' => Arr::get($this->settings_json, 'auth_type'),
                'api_key' => $this->provider_token,
                'integration' => Arr::get($this->settings_json, 'integration'),
                'external_instance_name' => $this->instance_name ?: $this->external_instance_id,
                'instance_token' => Arr::get($this->settings_json, 'instance_token') ?: $this->provider_client_token,
                'phone_e164' => $this->phone_number,
            ], fn ($value) => $value !== null && $value !== ''),
            default => array_filter([
                'instance_id' => $this->external_instance_id,
                'instance_token' => $this->provider_token,
                'client_token' => $this->provider_client_token,
                'base_url' => Arr::get($this->settings_json, 'base_url'),
            ], fn ($value) => $value !== null && $value !== ''),
        };
    }

    public function maskedProviderConfig(): array
    {
        $config = $this->providerConfig();

        return match ($this->providerKeyValue()) {
            'evolution' => [
                'server_url' => $config['server_url'] ?? null,
                'auth_type' => $config['auth_type'] ?? null,
                'integration' => $config['integration'] ?? null,
                'external_instance_name' => $config['external_instance_name'] ?? $this->instance_name,
                'phone_e164' => $config['phone_e164'] ?? $this->phone_number,
                'api_key_configured' => filled($config['api_key'] ?? $this->provider_token),
                'api_key_masked' => $this->maskSecret($config['api_key'] ?? $this->provider_token),
                'instance_token_configured' => filled($config['instance_token'] ?? $this->provider_client_token),
                'instance_token_masked' => $this->maskSecret($config['instance_token'] ?? $this->provider_client_token),
            ],
            default => [
                'instance_id' => $config['instance_id'] ?? $this->external_instance_id,
                'base_url' => $config['base_url'] ?? config('whatsapp.providers.zapi.base_url'),
                'instance_token_configured' => filled($config['instance_token'] ?? $this->provider_token),
                'instance_token_masked' => $this->maskSecret($config['instance_token'] ?? $this->provider_token),
                'client_token_configured' => filled($config['client_token'] ?? $this->provider_client_token),
                'client_token_masked' => $this->maskSecret($config['client_token'] ?? $this->provider_client_token),
            ],
        };
    }

    public function formattedPhone(): ?string
    {
        $digits = PhoneNumber::digits($this->phone_number);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4);

            return strlen($local) === 9
                ? sprintf('(%s) %s-%s', $ddd, substr($local, 0, 5), substr($local, 5))
                : sprintf('(%s) %s-%s', $ddd, substr($local, 0, 4), substr($local, 4));
        }

        return '+' . $digits;
    }

    public function syncLegacyProviderFields(?array $config = null): void
    {
        $config ??= $this->providerConfig();

        if ($this->providerKeyValue() === 'evolution') {
            $externalInstanceName = trim((string) ($config['external_instance_name'] ?? $this->instance_name ?? $this->external_instance_id));

            $this->external_instance_id = $externalInstanceName !== ''
                ? $externalInstanceName
                : (string) Str::slug((string) $this->name, '_');
            $this->provider_token = (string) ($config['api_key'] ?? $this->provider_token ?? '');
            $this->provider_client_token = (string) ($config['instance_token'] ?? $this->provider_client_token ?? '');

            if (! empty($config['phone_e164'])) {
                $this->phone_number = (string) $config['phone_e164'];
            }

            return;
        }

        $this->external_instance_id = (string) ($config['instance_id'] ?? $this->external_instance_id ?? '');
        $this->provider_token = (string) ($config['instance_token'] ?? $this->provider_token ?? '');
        $this->provider_client_token = (string) ($config['client_token'] ?? $this->provider_client_token ?? '');
    }

    private function maskSecret(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return '****' . substr((string) $value, -4);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', InstanceStatus::Connected->value);
    }

    public function scopeForProvider($query, WhatsAppProviderKey|string $providerKey)
    {
        $key = $providerKey instanceof WhatsAppProviderKey ? $providerKey->value : $providerKey;

        return $query->where('provider_key', $key);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

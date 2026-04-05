<?php

namespace App\Modules\WhatsApp\Actions;

use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWhatsAppInstanceAction
{
    public function execute(WhatsAppInstance $instance, array $attributes, User $user): WhatsAppInstance
    {
        $providerKey = (string) ($attributes['provider_key'] ?? $instance->providerKeyValue());
        $providerConfig = array_key_exists('provider_config', $attributes)
            ? $this->mergeProviderConfig($providerKey, $instance->providerConfig(), $attributes['provider_config'] ?? [], $attributes['instance_name'] ?? $instance->instance_name)
            : $instance->providerConfig();

        if (($attributes['is_default'] ?? false) === true && ! $this->canBeDefault($instance)) {
            throw ValidationException::withMessages([
                'is_default' => ['Teste a conexao primeiro e defina a instancia como padrao em seguida.'],
            ]);
        }

        $providerChanged = $providerKey !== $instance->providerKeyValue();
        $configChanged = array_key_exists('provider_config', $attributes);
        $identityChanged = array_key_exists('instance_name', $attributes);

        $instance = DB::transaction(function () use ($attributes, $instance, $providerKey, $providerConfig, $providerChanged, $configChanged, $identityChanged, $user) {
            if (($attributes['is_active'] ?? $instance->is_active) === false) {
                $attributes['is_default'] = false;
            }

            if (($attributes['is_default'] ?? false) === true) {
                WhatsAppInstance::query()
                    ->where('organization_id', $instance->organization_id)
                    ->where('id', '!=', $instance->id)
                    ->update(['is_default' => false]);
            }

            if ($providerChanged) {
                $provider = $this->resolveProvider($providerKey);
                $attributes['provider_id'] = $provider->id;
                $attributes['provider_key'] = $providerKey;
            }

            $payload = [
                'name' => $attributes['name'] ?? $instance->name,
                'instance_name' => $attributes['instance_name'] ?? $instance->instance_name,
                'provider_config_json' => $providerConfig,
                'phone_number' => array_key_exists('phone_number', $attributes)
                    ? ($attributes['phone_number'] ?? ($providerConfig['phone_e164'] ?? null))
                    : ($instance->phone_number ?? ($providerConfig['phone_e164'] ?? null)),
                'is_active' => $attributes['is_active'] ?? $instance->is_active,
                'is_default' => $attributes['is_default'] ?? $instance->is_default,
                'settings_json' => array_key_exists('settings', $attributes)
                    ? ($attributes['settings'] ?? [])
                    : ($instance->settings_json ?? []),
                'notes' => $attributes['notes'] ?? $instance->notes,
                'updated_by' => $user->id,
            ];

            if ($providerChanged || $configChanged || $identityChanged) {
                $payload['status'] = InstanceStatus::Configured;
                $payload['last_health_check_at'] = null;
                $payload['last_health_status'] = null;
                $payload['last_error'] = null;

                if ($payload['is_default']) {
                    $payload['is_default'] = false;
                }
            }

            if (array_key_exists('webhook_secret', $attributes)) {
                $payload['webhook_secret'] = $attributes['webhook_secret'];
            }

            $instance->update($payload);

            return $instance;
        });

        return $instance->fresh(['provider']);
    }

    private function mergeProviderConfig(string $providerKey, array $currentConfig, array $incomingConfig, string $instanceName): array
    {
        $incomingConfig = array_filter($incomingConfig, fn ($value) => $value !== null && $value !== '');
        $merged = array_replace($currentConfig, $incomingConfig);

        if ($providerKey === 'evolution') {
            $merged['external_instance_name'] = $merged['external_instance_name'] ?? $instanceName;

            if (isset($merged['server_url'])) {
                $merged['server_url'] = rtrim((string) $merged['server_url'], '/');
            }

            return $merged;
        }

        if (isset($merged['base_url'])) {
            $merged['base_url'] = rtrim((string) $merged['base_url'], '/');
        }

        return $merged;
    }

    private function resolveProvider(string $providerKey): WhatsAppProvider
    {
        return WhatsAppProvider::query()->firstOrCreate(
            ['key' => $providerKey],
            [
                'name' => $providerKey === 'evolution' ? 'Evolution API' : 'Z-API',
                'is_active' => true,
            ],
        );
    }

    private function canBeDefault(WhatsAppInstance $instance): bool
    {
        return $instance->is_active
            && $instance->last_health_check_at !== null
            && in_array($instance->last_health_status, ['connected', 'disconnected'], true);
    }
}

<?php

namespace App\Modules\WhatsApp\Actions;

use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateWhatsAppInstanceAction
{
    public function execute(array $attributes, User $user): WhatsAppInstance
    {
        $organizationId = $this->resolveOrganizationId($user, $attributes['organization_id'] ?? null);

        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => ['Selecione uma organizacao valida para cadastrar a instancia.'],
            ]);
        }

        if (($attributes['is_default'] ?? false) === true) {
            throw ValidationException::withMessages([
                'is_default' => ['Teste a conexao primeiro e defina a instancia como padrao depois do cadastro.'],
            ]);
        }

        $providerKey = (string) $attributes['provider_key'];
        $provider = $this->resolveProvider($providerKey);
        $providerConfig = $this->normalizedProviderConfig(
            $providerKey,
            $attributes['provider_config'] ?? [],
            (string) $attributes['instance_name'],
        );

        $instance = DB::transaction(function () use ($attributes, $organizationId, $provider, $providerKey, $providerConfig, $user) {
            $instance = new WhatsAppInstance([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'provider_id' => $provider->id,
                'provider_key' => $providerKey,
                'name' => $attributes['name'],
                'instance_name' => $attributes['instance_name'],
                'provider_config_json' => $providerConfig,
                'provider_meta_json' => null,
                'phone_number' => $attributes['phone_number'] ?? ($providerConfig['phone_e164'] ?? null),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_default' => false,
                'status' => InstanceStatus::Configured,
                'webhook_secret' => $attributes['webhook_secret'] ?? null,
                'settings_json' => $attributes['settings'] ?? [],
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $instance->save();

            return $instance;
        });

        return $instance->fresh(['provider']);
    }

    private function normalizedProviderConfig(string $providerKey, array $providerConfig, string $instanceName): array
    {
        $providerConfig = array_filter($providerConfig, fn ($value) => $value !== null && $value !== '');

        if ($providerKey === 'evolution') {
            $providerConfig['external_instance_name'] = $providerConfig['external_instance_name'] ?? $instanceName;
            $providerConfig['server_url'] = rtrim((string) ($providerConfig['server_url'] ?? ''), '/');

            return $providerConfig;
        }

        if (isset($providerConfig['base_url'])) {
            $providerConfig['base_url'] = rtrim((string) $providerConfig['base_url'], '/');
        }

        return $providerConfig;
    }

    private function resolveProvider(string $providerKey): WhatsAppProvider
    {
        return WhatsAppProvider::query()->firstOrCreate(
            ['key' => $providerKey],
            [
                'name' => match ($providerKey) {
                    'evolution' => 'Evolution API',
                    default => 'Z-API',
                },
                'is_active' => true,
            ],
        );
    }

    private function resolveOrganizationId(User $user, mixed $organizationId): ?int
    {
        if ($user->hasAnyRole(['super-admin', 'platform-admin']) && $organizationId) {
            return (int) $organizationId;
        }

        return $user->currentOrganization()?->id ?? $user->current_organization_id;
    }
}

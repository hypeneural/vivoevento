<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channels.manage') ?? false;
    }

    public function rules(): array
    {
        $providerKey = (string) $this->input('provider_key');

        return array_merge([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'provider_key' => ['required', Rule::enum(WhatsAppProviderKey::class)],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'instance_name' => [
                'required',
                'string',
                'min:3',
                'max:120',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('whatsapp_instances', 'instance_name')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $this->resolvedOrganizationId())
                        ->whereNull('deleted_at')),
            ],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'settings' => ['nullable', 'array'],
            'settings.timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'settings.webhook_url' => ['nullable', 'url', 'max:255'],
            'settings.tags' => ['nullable', 'array'],
            'settings.tags.*' => ['string', 'max:40'],
            'provider_config' => ['required', 'array'],
        ], $this->providerRules($providerKey));
    }

    protected function prepareForValidation(): void
    {
        $instanceName = (string) $this->input('instance_name', '');
        $providerKey = (string) $this->input('provider_key', '');
        $providerConfig = $this->input('provider_config', []);

        if (is_array($providerConfig)) {
            $providerConfig = array_map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            }, $providerConfig);
        }

        if ($providerKey === 'evolution' && empty($providerConfig['external_instance_name']) && $instanceName !== '') {
            $providerConfig['external_instance_name'] = Str::lower($instanceName);
        }

        $this->merge([
            'instance_name' => Str::lower(str_replace('-', '_', trim($instanceName))),
            'phone_number' => $this->normalizedPhone($this->input('phone_number')),
            'provider_config' => $providerConfig,
        ]);
    }

    private function providerRules(string $providerKey): array
    {
        return match ($providerKey) {
            'evolution' => [
                'provider_config.server_url' => ['required', 'string', 'url', 'starts_with:https://', 'max:255'],
                'provider_config.auth_type' => ['required', Rule::in(['global_apikey', 'instance_apikey'])],
                'provider_config.api_key' => ['required', 'string', 'max:255'],
                'provider_config.integration' => ['required', Rule::in(['WHATSAPP-BAILEYS', 'WHATSAPP-BUSINESS'])],
                'provider_config.external_instance_name' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
                'provider_config.instance_token' => ['nullable', 'string', 'max:255'],
                'provider_config.phone_e164' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            ],
            default => [
                'provider_config.instance_id' => ['required', 'string', 'max:180'],
                'provider_config.instance_token' => ['required', 'string', 'max:255'],
                'provider_config.client_token' => ['nullable', 'string', 'max:255'],
                'provider_config.base_url' => ['nullable', 'string', 'url', 'max:255'],
            ],
        };
    }

    private function normalizedPhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function resolvedOrganizationId(): int
    {
        $user = $this->user();
        $requested = $this->integer('organization_id');

        if ($user && $user->hasAnyRole(['super-admin', 'platform-admin']) && $requested > 0) {
            return $requested;
        }

        return (int) ($user?->currentOrganization()?->id ?? $user?->current_organization_id ?? 0);
    }
}

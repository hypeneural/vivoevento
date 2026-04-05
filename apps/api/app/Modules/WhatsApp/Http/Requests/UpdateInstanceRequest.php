<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('channels.manage') ?? false;
    }

    public function rules(): array
    {
        /** @var WhatsAppInstance|null $instance */
        $instance = $this->route('instance');
        $providerKey = (string) ($this->input('provider_key') ?: $instance?->providerKeyValue() ?: 'zapi');

        return array_merge([
            'provider_key' => ['sometimes', Rule::enum(WhatsAppProviderKey::class)],
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'instance_name' => [
                'sometimes',
                'string',
                'min:3',
                'max:120',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('whatsapp_instances', 'instance_name')
                    ->ignore($instance?->id)
                    ->where(fn ($query) => $query
                        ->where('organization_id', $instance?->organization_id)
                        ->whereNull('deleted_at')),
            ],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'settings' => ['nullable', 'array'],
            'settings.timeout_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'settings.webhook_url' => ['nullable', 'url', 'max:255'],
            'settings.tags' => ['nullable', 'array'],
            'settings.tags.*' => ['string', 'max:40'],
            'provider_config' => ['sometimes', 'array'],
        ], $this->providerRules($providerKey));
    }

    protected function prepareForValidation(): void
    {
        $instanceName = $this->input('instance_name');
        $providerKey = (string) ($this->input('provider_key') ?: optional($this->route('instance'))->providerKeyValue());
        $providerConfig = $this->input('provider_config', []);

        if (is_array($providerConfig)) {
            foreach ($providerConfig as $key => $value) {
                $providerConfig[$key] = is_string($value) ? trim($value) : $value;
            }

            foreach (['instance_token', 'client_token', 'api_key'] as $sensitiveKey) {
                if (array_key_exists($sensitiveKey, $providerConfig) && $providerConfig[$sensitiveKey] === '') {
                    unset($providerConfig[$sensitiveKey]);
                }
            }
        }

        if ($providerKey === 'evolution' && empty($providerConfig['external_instance_name']) && is_string($instanceName) && trim($instanceName) !== '') {
            $providerConfig['external_instance_name'] = Str::lower(trim($instanceName));
        }

        $payload = [
            'provider_config' => $providerConfig,
            'phone_number' => $this->normalizedPhone($this->input('phone_number')),
        ];

        if (is_string($instanceName)) {
            $payload['instance_name'] = Str::lower(str_replace('-', '_', trim($instanceName)));
        }

        $this->merge($payload);
    }

    private function providerRules(string $providerKey): array
    {
        return match ($providerKey) {
            'evolution' => [
                'provider_config.server_url' => ['sometimes', 'string', 'url', 'starts_with:https://', 'max:255'],
                'provider_config.auth_type' => ['sometimes', Rule::in(['global_apikey', 'instance_apikey'])],
                'provider_config.api_key' => ['sometimes', 'string', 'max:255'],
                'provider_config.integration' => ['sometimes', Rule::in(['WHATSAPP-BAILEYS', 'WHATSAPP-BUSINESS'])],
                'provider_config.external_instance_name' => ['sometimes', 'string', 'min:3', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
                'provider_config.instance_token' => ['sometimes', 'string', 'max:255'],
                'provider_config.phone_e164' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            ],
            default => [
                'provider_config.instance_id' => ['sometimes', 'string', 'max:180'],
                'provider_config.instance_token' => ['sometimes', 'string', 'max:255'],
                'provider_config.client_token' => ['sometimes', 'nullable', 'string', 'max:255'],
                'provider_config.base_url' => ['sometimes', 'string', 'url', 'max:255'],
            ],
        };
    }

    private function normalizedPhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }
}

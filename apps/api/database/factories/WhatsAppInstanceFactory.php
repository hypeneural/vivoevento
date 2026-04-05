<?php

namespace Database\Factories;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WhatsAppInstanceFactory extends Factory
{
    protected $model = WhatsAppInstance::class;

    public function definition(): array
    {
        $provider = WhatsAppProvider::query()->firstOrCreate(
            ['key' => 'zapi'],
            ['name' => 'Z-API', 'is_active' => true],
        );

        return [
            'uuid' => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'provider_id' => $provider->id,
            'provider_key' => 'zapi',
            'name' => 'WhatsApp ' . fake()->word(),
            'instance_name' => fake()->unique()->slug(2, '_'),
            'external_instance_id' => strtoupper(Str::random(16)),
            'provider_token' => Str::random(24),
            'provider_client_token' => Str::random(24),
            'provider_config_json' => [
                'instance_id' => strtoupper(Str::random(16)),
                'instance_token' => Str::random(24),
                'client_token' => Str::random(24),
            ],
            'phone_number' => '5511999999999',
            'is_active' => true,
            'is_default' => false,
            'status' => 'configured',
            'settings_json' => [],
            'created_by' => User::factory(),
        ];
    }

    public function connected(): static
    {
        return $this->state(fn () => [
            'status' => 'connected',
            'connected_at' => now(),
            'last_health_check_at' => now(),
            'last_health_status' => 'connected',
        ]);
    }

    public function evolution(): static
    {
        return $this->state(function () {
            $provider = WhatsAppProvider::query()->firstOrCreate(
                ['key' => 'evolution'],
                ['name' => 'Evolution API', 'is_active' => true],
            );

            $instanceName = fake()->unique()->slug(2, '_');

            return [
                'provider_id' => $provider->id,
                'provider_key' => 'evolution',
                'instance_name' => $instanceName,
                'external_instance_id' => $instanceName,
                'provider_token' => 'evo_api_key_' . Str::random(16),
                'provider_client_token' => '',
                'provider_config_json' => [
                    'server_url' => 'https://evolution.example.com',
                    'auth_type' => 'global_apikey',
                    'api_key' => 'evo_api_key_' . Str::random(16),
                    'integration' => 'WHATSAPP-BAILEYS',
                    'external_instance_name' => $instanceName,
                    'phone_e164' => '5511999999999',
                ],
            ];
        });
    }
}

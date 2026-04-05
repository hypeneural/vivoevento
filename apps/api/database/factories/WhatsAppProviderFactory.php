<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppProviderFactory extends Factory
{
    protected $model = WhatsAppProvider::class;

    public function definition(): array
    {
        return [
            'key' => 'zapi',
            'name' => 'Z-API',
            'is_active' => true,
            'config_json' => null,
        ];
    }

    public function evolution(): static
    {
        return $this->state(fn () => [
            'key' => 'evolution',
            'name' => 'Evolution API',
        ]);
    }
}

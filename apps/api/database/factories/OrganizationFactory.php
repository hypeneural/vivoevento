<?php

namespace Database\Factories;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'trade_name' => $name,
            'legal_name' => $name . ' LTDA',
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'type' => fake()->randomElement(OrganizationType::cases())->value,
            'status' => 'active',
            'email' => fake()->companyEmail(),
            'billing_email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'America/Sao_Paulo',
        ];
    }
}

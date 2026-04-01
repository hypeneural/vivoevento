<?php

namespace Database\Factories;

use App\Modules\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'type' => fake()->randomElement(['pessoa_fisica', 'empresa']),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function empresa(): static
    {
        return $this->state(fn () => [
            'type' => 'empresa',
            'name' => fake()->company(),
            'document_number' => fake()->numerify('##.###.###/####-##'),
        ]);
    }
}

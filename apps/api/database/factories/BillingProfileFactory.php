<?php

namespace Database\Factories;

use App\Modules\Billing\Models\BillingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingProfileFactory extends Factory
{
    protected $model = BillingProfile::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'gateway_provider' => 'pagarme',
            'gateway_customer_id' => 'cus_'.fake()->bothify('????????????????'),
            'gateway_default_card_id' => 'card_'.fake()->bothify('????????????????'),
            'payer_name' => fake()->name(),
            'payer_email' => fake()->safeEmail(),
            'payer_document' => fake()->numerify('###########'),
            'payer_phone' => fake()->numerify('55119########'),
            'billing_address_json' => [
                'street' => fake()->streetName(),
                'number' => (string) fake()->numberBetween(1, 999),
                'district' => fake()->citySuffix(),
                'zip_code' => fake()->numerify('########'),
                'city' => fake()->city(),
                'state' => 'SP',
                'country' => 'BR',
            ],
            'metadata_json' => [],
        ];
    }
}

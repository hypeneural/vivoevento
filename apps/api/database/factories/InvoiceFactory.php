<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issuedAt = now();

        return [
            'organization_id' => OrganizationFactory::new(),
            'billing_order_id' => BillingOrderFactory::new(),
            'invoice_number' => sprintf('EVV-%s-%06d', $issuedAt->format('Ymd'), fake()->numberBetween(1, 999999)),
            'status' => InvoiceStatus::Paid->value,
            'amount_cents' => fake()->numberBetween(9900, 39900),
            'currency' => 'BRL',
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt,
            'paid_at' => $issuedAt,
            'snapshot_json' => [],
        ];
    }
}

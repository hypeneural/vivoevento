<?php

namespace Database\Factories;

use App\Modules\Billing\Enums\BillingOrderNotificationType;
use App\Modules\Billing\Models\BillingOrderNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingOrderNotificationFactory extends Factory
{
    protected $model = BillingOrderNotification::class;

    public function definition(): array
    {
        return [
            'billing_order_id' => BillingOrderFactory::new(),
            'notification_type' => BillingOrderNotificationType::PaymentPaid->value,
            'channel' => 'whatsapp',
            'status' => 'queued',
            'recipient_phone' => '5548999999999',
            'context_json' => [],
            'dispatched_at' => now(),
        ];
    }
}

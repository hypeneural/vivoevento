<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;

interface BillingSubscriptionGatewayInterface
{
    public function providerKey(): string;

    public function ensurePlan(Plan $plan, PlanPrice $planPrice, array $context = []): array;

    public function createSubscription(BillingOrder $order, Plan $plan, PlanPrice $planPrice, array $context = []): array;
}

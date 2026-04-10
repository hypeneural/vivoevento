<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Plans\Models\Plan;
use App\Modules\Plans\Models\PlanPrice;

interface BillingSubscriptionGatewayInterface
{
    public function providerKey(): string;

    public function ensurePlan(Plan $plan, PlanPrice $planPrice, array $context = []): array;

    public function createSubscription(BillingOrder $order, Plan $plan, PlanPrice $planPrice, array $context = []): array;

    public function cancelSubscription(Subscription $subscription, array $context = []): array;

    public function fetchSubscription(Subscription $subscription, array $context = []): array;

    public function listCycles(Subscription $subscription, array $query = []): array;

    public function listInvoices(Subscription $subscription, array $query = []): array;

    public function listCharges(Subscription $subscription, array $query = []): array;

    public function getCharge(Subscription $subscription, string $chargeId, array $context = []): array;

    public function listCustomerCards(Subscription $subscription, array $context = []): array;

    public function updateSubscriptionCard(Subscription $subscription, array $context = []): array;
}

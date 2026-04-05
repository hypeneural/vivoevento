<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\BillingOrder;

interface BillingGatewayInterface
{
    public function providerKey(): string;

    public function createSubscriptionCheckout(BillingOrder $order, array $context = []): array;

    public function createEventPackageCheckout(BillingOrder $order, array $context = []): array;

    public function parseWebhook(array $payload, array $headers = []): array;

    public function cancelOrder(BillingOrder $order, array $context = []): array;
}

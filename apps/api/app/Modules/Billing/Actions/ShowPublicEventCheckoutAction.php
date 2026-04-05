<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\BillingOrder;
use App\Modules\Billing\Services\PublicEventCheckoutPayloadBuilder;

class ShowPublicEventCheckoutAction
{
    public function __construct(
        private readonly PublicEventCheckoutPayloadBuilder $payloads,
    ) {}

    public function execute(BillingOrder $billingOrder): array
    {
        return $this->payloads->build($billingOrder->fresh(), [
            'message' => null,
        ]);
    }
}

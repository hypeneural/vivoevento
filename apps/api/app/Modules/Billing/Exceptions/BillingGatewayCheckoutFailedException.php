<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

class BillingGatewayCheckoutFailedException extends RuntimeException
{
    public function __construct(
        private readonly array $checkout,
        string $message = 'Gateway checkout failed.',
    ) {
        parent::__construct($message);
    }

    public function checkout(): array
    {
        return $this->checkout;
    }
}

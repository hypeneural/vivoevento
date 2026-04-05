<?php

namespace App\Modules\Billing\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}

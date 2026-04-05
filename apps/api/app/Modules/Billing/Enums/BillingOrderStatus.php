<?php

namespace App\Modules\Billing\Enums;

enum BillingOrderStatus: string
{
    case Draft = 'draft';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function isPendingPayment(): bool
    {
        return $this === self::PendingPayment;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isRefunded(): bool
    {
        return $this === self::Refunded;
    }
}

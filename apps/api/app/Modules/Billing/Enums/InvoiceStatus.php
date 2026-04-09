<?php

namespace App\Modules\Billing\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Void = 'void';
    case Refunded = 'refunded';

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}

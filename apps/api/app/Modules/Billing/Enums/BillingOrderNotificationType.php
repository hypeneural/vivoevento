<?php

namespace App\Modules\Billing\Enums;

enum BillingOrderNotificationType: string
{
    case PixGenerated = 'pix_generated';
    case PaymentPaid = 'payment_paid';
    case PaymentFailed = 'payment_failed';
    case PaymentRefunded = 'payment_refunded';

    public function label(): string
    {
        return match ($this) {
            self::PixGenerated => 'PIX gerado',
            self::PaymentPaid => 'Pagamento confirmado',
            self::PaymentFailed => 'Pagamento reprovado',
            self::PaymentRefunded => 'Pagamento estornado',
        };
    }
}

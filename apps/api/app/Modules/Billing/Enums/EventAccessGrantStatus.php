<?php

namespace App\Modules\Billing\Enums;

enum EventAccessGrantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function isActiveLike(): bool
    {
        return $this === self::Active;
    }
}

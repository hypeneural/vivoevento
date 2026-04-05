<?php

namespace App\Modules\Events\Enums;

enum EventCommercialMode: string
{
    case None = 'none';
    case SubscriptionCovered = 'subscription_covered';
    case Trial = 'trial';
    case SinglePurchase = 'single_purchase';
    case Bonus = 'bonus';
    case ManualOverride = 'manual_override';
}

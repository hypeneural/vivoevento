<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Events\Enums\EventCommercialMode;

enum EventAccessGrantSourceType: string
{
    case Subscription = 'subscription';
    case EventPurchase = 'event_purchase';
    case Trial = 'trial';
    case Bonus = 'bonus';
    case ManualOverride = 'manual_override';

    public function defaultPriority(): int
    {
        return match ($this) {
            self::ManualOverride => 1000,
            self::Bonus => 900,
            self::EventPurchase => 800,
            self::Subscription => 500,
            self::Trial => 100,
        };
    }

    public function commercialMode(): EventCommercialMode
    {
        return match ($this) {
            self::Subscription => EventCommercialMode::SubscriptionCovered,
            self::EventPurchase => EventCommercialMode::SinglePurchase,
            self::Trial => EventCommercialMode::Trial,
            self::Bonus => EventCommercialMode::Bonus,
            self::ManualOverride => EventCommercialMode::ManualOverride,
        };
    }
}

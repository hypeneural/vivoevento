<?php

namespace App\Modules\Billing\Enums;

enum BillingOrderMode: string
{
    case Subscription = 'subscription';
    case EventPackage = 'event_package';
}

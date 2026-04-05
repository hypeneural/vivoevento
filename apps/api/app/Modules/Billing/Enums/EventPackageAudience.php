<?php

namespace App\Modules\Billing\Enums;

enum EventPackageAudience: string
{
    case DirectCustomer = 'direct_customer';
    case Partner = 'partner';
    case Both = 'both';
}

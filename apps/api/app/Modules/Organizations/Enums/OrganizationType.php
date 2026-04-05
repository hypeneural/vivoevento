<?php

namespace App\Modules\Organizations\Enums;

enum OrganizationType: string
{
    case Partner = 'partner';
    case DirectCustomer = 'direct_customer';
    case Host = 'host';
    case Agency = 'agency';
    case Brand = 'brand';
    case Internal = 'internal';
}

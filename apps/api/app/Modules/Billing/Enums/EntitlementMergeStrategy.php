<?php

namespace App\Modules\Billing\Enums;

enum EntitlementMergeStrategy: string
{
    case Expand = 'expand';
    case Replace = 'replace';
    case Restrict = 'restrict';
}

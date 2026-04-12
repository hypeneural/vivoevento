<?php

namespace App\Modules\EventPeople\Enums;

enum EventCoverageState: string
{
    case Missing = 'missing';
    case Weak = 'weak';
    case Ok = 'ok';
    case Strong = 'strong';
}

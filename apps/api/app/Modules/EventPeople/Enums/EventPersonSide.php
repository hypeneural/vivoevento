<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonSide: string
{
    case BrideSide = 'bride_side';
    case GroomSide = 'groom_side';
    case HostSide = 'host_side';
    case CompanySide = 'company_side';
    case Neutral = 'neutral';
}

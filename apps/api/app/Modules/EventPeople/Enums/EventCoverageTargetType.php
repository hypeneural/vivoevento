<?php

namespace App\Modules\EventPeople\Enums;

enum EventCoverageTargetType: string
{
    case Person = 'person';
    case Pair = 'pair';
    case Group = 'group';
}

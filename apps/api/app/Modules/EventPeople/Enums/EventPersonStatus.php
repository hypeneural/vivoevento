<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Hidden = 'hidden';
}

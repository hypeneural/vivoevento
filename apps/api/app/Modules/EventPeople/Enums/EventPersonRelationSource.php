<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonRelationSource: string
{
    case Manual = 'manual';
    case Inferred = 'inferred';
    case Imported = 'imported';
}

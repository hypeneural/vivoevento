<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonReferencePhotoStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Invalid = 'invalid';
}

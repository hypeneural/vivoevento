<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonReferencePhotoPurpose: string
{
    case Avatar = 'avatar';
    case Matching = 'matching';
    case Both = 'both';
}

<?php

namespace App\Modules\EventPeople\Enums;

enum EventRelationalCollectionVisibility: string
{
    case Internal = 'internal';
    case PublicReady = 'public_ready';
}

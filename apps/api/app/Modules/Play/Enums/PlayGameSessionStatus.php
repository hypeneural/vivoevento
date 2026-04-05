<?php

namespace App\Modules\Play\Enums;

enum PlayGameSessionStatus: string
{
    case Started = 'started';
    case Paused = 'paused';
    case Finished = 'finished';
    case Abandoned = 'abandoned';
}

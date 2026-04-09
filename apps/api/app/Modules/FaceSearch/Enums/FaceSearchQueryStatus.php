<?php

namespace App\Modules\FaceSearch\Enums;

enum FaceSearchQueryStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Degraded = 'degraded';
}

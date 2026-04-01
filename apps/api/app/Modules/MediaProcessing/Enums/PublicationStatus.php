<?php

namespace App\Modules\MediaProcessing\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
    case Deleted = 'deleted';
}

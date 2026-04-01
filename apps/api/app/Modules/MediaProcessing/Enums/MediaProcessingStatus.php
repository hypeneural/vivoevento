<?php

namespace App\Modules\MediaProcessing\Enums;

enum MediaProcessingStatus: string
{
    case Received = 'received';
    case Downloaded = 'downloaded';
    case Processed = 'processed';
    case Failed = 'failed';
}

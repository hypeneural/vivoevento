<?php

namespace App\Modules\WhatsApp\Enums;

enum InboundEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';
}

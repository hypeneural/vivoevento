<?php

namespace App\Modules\WhatsApp\Enums;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}

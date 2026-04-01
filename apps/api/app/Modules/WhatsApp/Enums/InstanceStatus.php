<?php

namespace App\Modules\WhatsApp\Enums;

enum InstanceStatus: string
{
    case Pending = 'pending';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';

    public function isOperational(): bool
    {
        return $this === self::Connected;
    }
}

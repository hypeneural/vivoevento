<?php

namespace App\Modules\WhatsApp\Enums;

enum InstanceStatus: string
{
    case Draft = 'draft';
    case Configured = 'configured';
    case Pending = 'pending';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case InvalidCredentials = 'invalid_credentials';
    case Error = 'error';

    public function isOperational(): bool
    {
        return $this === self::Connected;
    }

    public function normalized(): self
    {
        return match ($this) {
            self::Pending => self::Draft,
            self::Connecting => self::Configured,
            default => $this,
        };
    }
}

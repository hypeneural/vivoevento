<?php

namespace App\Modules\WhatsApp\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Received = 'received';
    case Ignored = 'ignored';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Read,
            self::Failed,
            self::Ignored,
        ]);
    }
}

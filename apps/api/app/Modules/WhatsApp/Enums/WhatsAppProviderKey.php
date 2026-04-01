<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppProviderKey: string
{
    case ZApi = 'zapi';
    case Evolution = 'evolution';

    public function label(): string
    {
        return match ($this) {
            self::ZApi => 'Z-API',
            self::Evolution => 'Evolution API',
        };
    }
}

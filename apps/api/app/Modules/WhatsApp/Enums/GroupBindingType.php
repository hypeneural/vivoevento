<?php

namespace App\Modules\WhatsApp\Enums;

enum GroupBindingType: string
{
    case EventGallery = 'event_gallery';
    case Alerts = 'alerts';
    case Support = 'support';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::EventGallery => 'Galeria do Evento',
            self::Alerts => 'Alertas',
            self::Support => 'Suporte',
            self::General => 'Geral',
        };
    }
}

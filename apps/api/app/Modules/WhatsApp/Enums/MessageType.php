<?php

namespace App\Modules\WhatsApp\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Reaction = 'reaction';
    case Sticker = 'sticker';
    case Contact = 'contact';
    case Carousel = 'carousel';
    case Pix = 'pix';
    case System = 'system';

    public function hasMedia(): bool
    {
        return in_array($this, [
            self::Image,
            self::Video,
            self::Audio,
            self::Document,
            self::Sticker,
        ]);
    }
}

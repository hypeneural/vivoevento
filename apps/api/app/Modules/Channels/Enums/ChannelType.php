<?php

namespace App\Modules\Channels\Enums;

enum ChannelType: string
{
    case WhatsAppGroup = 'whatsapp_group';
    case WhatsAppDirect = 'whatsapp_direct';
    case PublicUploadLink = 'public_upload_link';
    case TelegramBot = 'telegram_bot';
    case InternalUpload = 'internal_upload';
}

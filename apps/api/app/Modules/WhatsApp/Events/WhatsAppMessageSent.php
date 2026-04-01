<?php

namespace App\Modules\WhatsApp\Events;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsAppMessage $message,
    ) {}
}

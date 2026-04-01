<?php

namespace App\Modules\WhatsApp\Events;

use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppInstanceStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly WhatsAppInstance $instance,
        public readonly InstanceStatus $previousStatus,
        public readonly InstanceStatus $newStatus,
    ) {}
}

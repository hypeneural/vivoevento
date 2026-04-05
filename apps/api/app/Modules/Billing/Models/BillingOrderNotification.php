<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\BillingOrderNotificationType;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingOrderNotification extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\BillingOrderNotificationFactory
    {
        return \Database\Factories\BillingOrderNotificationFactory::new();
    }

    protected $fillable = [
        'billing_order_id',
        'notification_type',
        'channel',
        'status',
        'recipient_phone',
        'whatsapp_instance_id',
        'whatsapp_message_id',
        'context_json',
        'dispatched_at',
        'failed_at',
    ];

    protected $casts = [
        'notification_type' => BillingOrderNotificationType::class,
        'context_json' => 'array',
        'dispatched_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'whatsapp_instance_id');
    }

    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}

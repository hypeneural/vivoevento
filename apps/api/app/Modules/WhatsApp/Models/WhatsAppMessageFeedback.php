<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageFeedback extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_message_feedbacks';

    protected static function newFactory(): \Database\Factories\WhatsAppMessageFeedbackFactory
    {
        return \Database\Factories\WhatsAppMessageFeedbackFactory::new();
    }

    protected $fillable = [
        'event_id',
        'instance_id',
        'inbound_message_id',
        'event_media_id',
        'outbound_message_id',
        'inbound_provider_message_id',
        'chat_external_id',
        'sender_external_id',
        'feedback_kind',
        'feedback_phase',
        'status',
        'reaction_emoji',
        'reply_text',
        'error_message',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'outbound_message_id');
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\InboundMedia\Models\InboundMessage::class, 'inbound_message_id');
    }

    public function eventMedia(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }
}

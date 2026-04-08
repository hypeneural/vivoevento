<?php

namespace App\Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessageFeedback extends Model
{
    use HasFactory;

    protected $table = 'telegram_message_feedbacks';

    protected static function newFactory(): \Database\Factories\TelegramMessageFeedbackFactory
    {
        return \Database\Factories\TelegramMessageFeedbackFactory::new();
    }

    protected $fillable = [
        'event_id',
        'event_channel_id',
        'trace_id',
        'inbound_message_id',
        'event_media_id',
        'inbound_provider_message_id',
        'chat_external_id',
        'sender_external_id',
        'feedback_kind',
        'feedback_phase',
        'status',
        'reaction_emoji',
        'chat_action',
        'reply_text',
        'resolution_json',
        'error_message',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'resolution_json' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Channels\Models\EventChannel::class, 'event_channel_id');
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

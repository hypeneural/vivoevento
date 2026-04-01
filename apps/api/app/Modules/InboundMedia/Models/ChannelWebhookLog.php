<?php

namespace App\Modules\InboundMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChannelWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_channel_id', 'provider', 'message_id', 'detected_type',
        'routing_status', 'payload_json', 'error_message', 'inbound_message_id',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Channels\Models\EventChannel::class, 'event_channel_id');
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(InboundMessage::class);
    }
}

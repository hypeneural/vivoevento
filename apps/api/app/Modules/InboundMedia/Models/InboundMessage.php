<?php

namespace App\Modules\InboundMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InboundMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'event_channel_id', 'trace_id', 'provider', 'message_id', 'message_type',
        'chat_external_id', 'sender_external_id', 'sender_phone', 'sender_lid',
        'sender_name', 'sender_avatar_url', 'body_text', 'media_url',
        'reference_message_id', 'from_me', 'normalized_payload_json', 'status',
        'received_at', 'processed_at',
    ];

    protected $casts = [
        'from_me' => 'boolean',
        'normalized_payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Channels\Models\EventChannel::class, 'event_channel_id');
    }

    public function eventMedia(): HasOne
    {
        return $this->hasOne(\App\Modules\MediaProcessing\Models\EventMedia::class, 'inbound_message_id');
    }
}

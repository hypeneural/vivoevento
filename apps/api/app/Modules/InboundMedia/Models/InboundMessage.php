<?php

namespace App\Modules\InboundMedia\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InboundMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'event_channel_id', 'provider', 'message_id', 'message_type',
        'sender_phone', 'sender_name', 'body_text', 'media_url',
        'reference_message_id', 'normalized_payload_json', 'status',
        'received_at', 'processed_at',
    ];

    protected $casts = [
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
}

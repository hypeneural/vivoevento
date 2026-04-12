<?php

namespace App\Modules\EventOperations\Models;

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOperationEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'event_media_id',
        'inbound_message_id',
        'station_key',
        'event_key',
        'severity',
        'urgency',
        'title',
        'summary',
        'payload_json',
        'animation_hint',
        'station_load',
        'queue_depth',
        'render_group',
        'dedupe_window_key',
        'correlation_key',
        'event_sequence',
        'occurred_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'station_load' => 'float',
        'queue_depth' => 'integer',
        'event_sequence' => 'integer',
        'occurred_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventOperationEventFactory
    {
        return \Database\Factories\EventOperationEventFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventMedia(): BelongsTo
    {
        return $this->belongsTo(EventMedia::class);
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(InboundMessage::class);
    }
}

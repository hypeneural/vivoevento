<?php

namespace App\Modules\EventOperations\Models;

use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOperationSnapshot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'schema_version',
        'snapshot_version',
        'latest_event_sequence',
        'timeline_cursor',
        'snapshot_json',
        'server_time',
        'updated_at',
    ];

    protected $casts = [
        'schema_version' => 'integer',
        'snapshot_version' => 'integer',
        'latest_event_sequence' => 'integer',
        'snapshot_json' => 'array',
        'server_time' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventOperationSnapshotFactory
    {
        return \Database\Factories\EventOperationSnapshotFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

<?php
namespace App\Modules\Analytics\Models;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'organization_id', 'event_id', 'event_media_id',
        'event_name', 'actor_type', 'actor_id', 'channel',
        'metadata_json', 'occurred_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'occurred_at' => 'datetime',
    ];
}

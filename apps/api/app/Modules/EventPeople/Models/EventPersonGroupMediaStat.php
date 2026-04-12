<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonGroupMediaStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_person_group_id',
        'media_count',
        'published_media_count',
        'projected_at',
    ];

    protected $casts = [
        'media_count' => 'integer',
        'published_media_count' => 'integer',
        'projected_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonGroupMediaStatFactory
    {
        return \Database\Factories\EventPersonGroupMediaStatFactory::new();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventPersonGroup::class, 'event_person_group_id');
    }
}

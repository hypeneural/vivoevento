<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonGroupStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_person_group_id',
        'member_count',
        'people_with_primary_photo_count',
        'people_with_media_count',
        'projected_at',
    ];

    protected $casts = [
        'member_count' => 'integer',
        'people_with_primary_photo_count' => 'integer',
        'people_with_media_count' => 'integer',
        'projected_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonGroupStatFactory
    {
        return \Database\Factories\EventPersonGroupStatFactory::new();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventPersonGroup::class, 'event_person_group_id');
    }
}

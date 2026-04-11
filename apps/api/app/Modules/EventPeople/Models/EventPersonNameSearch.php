<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonNameSearch extends Model
{
    protected $table = 'event_person_name_search';

    protected $fillable = [
        'event_id',
        'event_person_id',
        'normalized_name',
        'alias',
        'normalized_alias',
        'rank',
        'projected_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'projected_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'event_person_id');
    }
}

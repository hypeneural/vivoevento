<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMustHavePair extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'person_a_id',
        'person_b_id',
        'person_pair_key',
        'label',
        'required_media_count',
        'importance_rank',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'required_media_count' => 'integer',
        'importance_rank' => 'integer',
    ];

    protected static function newFactory(): \Database\Factories\EventMustHavePairFactory
    {
        return \Database\Factories\EventMustHavePairFactory::new();
    }

    public function personA(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_a_id');
    }

    public function personB(): BelongsTo
    {
        return $this->belongsTo(EventPerson::class, 'person_b_id');
    }
}

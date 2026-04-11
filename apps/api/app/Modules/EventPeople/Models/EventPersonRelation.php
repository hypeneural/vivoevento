<?php

namespace App\Modules\EventPeople\Models;

use App\Modules\EventPeople\Enums\EventPersonRelationDirectionality;
use App\Modules\EventPeople\Enums\EventPersonRelationSource;
use App\Modules\EventPeople\Enums\EventPersonRelationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPersonRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'person_a_id',
        'person_b_id',
        'person_pair_key',
        'relation_type',
        'directionality',
        'source',
        'confidence',
        'strength',
        'is_primary',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'relation_type' => EventPersonRelationType::class,
        'directionality' => EventPersonRelationDirectionality::class,
        'source' => EventPersonRelationSource::class,
        'confidence' => 'float',
        'strength' => 'float',
        'is_primary' => 'boolean',
    ];

    protected static function newFactory(): \Database\Factories\EventPersonRelationFactory
    {
        return \Database\Factories\EventPersonRelationFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
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

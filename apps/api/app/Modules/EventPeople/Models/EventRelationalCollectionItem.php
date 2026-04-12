<?php

namespace App\Modules\EventPeople\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRelationalCollectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_relational_collection_id',
        'event_media_id',
        'sort_order',
        'match_score',
        'matched_people_count',
        'is_published',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'match_score' => 'float',
        'matched_people_count' => 'integer',
        'is_published' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function newFactory(): \Database\Factories\EventRelationalCollectionItemFactory
    {
        return \Database\Factories\EventRelationalCollectionItemFactory::new();
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(EventRelationalCollection::class, 'event_relational_collection_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\MediaProcessing\Models\EventMedia::class, 'event_media_id');
    }
}

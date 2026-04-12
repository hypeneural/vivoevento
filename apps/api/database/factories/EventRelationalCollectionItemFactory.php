<?php

namespace Database\Factories;

use App\Modules\EventPeople\Models\EventRelationalCollectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventRelationalCollectionItemFactory extends Factory
{
    protected $model = EventRelationalCollectionItem::class;

    public function definition(): array
    {
        $collection = EventRelationalCollectionFactory::new()->create();

        return [
            'event_id' => $collection->event_id,
            'event_relational_collection_id' => $collection->id,
            'event_media_id' => EventMediaFactory::new()->create([
                'event_id' => $collection->event_id,
            ])->id,
            'sort_order' => 0,
            'match_score' => 100,
            'matched_people_count' => 1,
            'is_published' => false,
            'metadata' => null,
        ];
    }
}

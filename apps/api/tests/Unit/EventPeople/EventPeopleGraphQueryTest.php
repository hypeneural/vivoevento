<?php

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonMediaStat;
use App\Modules\EventPeople\Models\EventPersonPairScore;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Queries\BuildEventPeopleGraphQuery;
use App\Modules\Events\Models\Event;

it('builds a stable graph payload with semantic roles, stats and relation strength data', function () {
    $event = Event::factory()->create([
        'event_type' => 'wedding',
    ]);

    $bride = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noiva',
        'type' => 'bride',
        'side' => 'neutral',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    $groom = EventPerson::factory()->create([
        'event_id' => $event->id,
        'display_name' => 'Noivo',
        'type' => 'groom',
        'side' => 'neutral',
        'importance_rank' => 100,
        'status' => 'active',
    ]);

    EventPersonMediaStat::query()->create([
        'event_id' => $event->id,
        'event_person_id' => $bride->id,
        'media_count' => 8,
        'solo_media_count' => 3,
        'with_others_media_count' => 5,
        'published_media_count' => 6,
        'pending_media_count' => 1,
        'projected_at' => now(),
    ]);

    EventPersonRelation::query()->create([
        'event_id' => $event->id,
        'person_a_id' => $bride->id,
        'person_b_id' => $groom->id,
        'person_pair_key' => "{$bride->id}:{$groom->id}",
        'relation_type' => 'spouse_of',
        'directionality' => 'undirected',
        'source' => 'manual',
        'is_primary' => true,
    ]);

    EventPersonPairScore::query()->create([
        'event_id' => $event->id,
        'person_a_id' => $bride->id,
        'person_b_id' => $groom->id,
        'person_pair_key' => "{$bride->id}:{$groom->id}",
        'co_media_count' => 5,
        'weighted_score' => 92.4,
        'projected_at' => now(),
    ]);

    $graph = app(BuildEventPeopleGraphQuery::class)->build($event);

    expect($graph['people'])->toHaveCount(2)
        ->and(collect($graph['people'])->firstWhere('display_name', 'Noiva')['role_label'])->toBe('Noiva')
        ->and(collect($graph['people'])->firstWhere('display_name', 'Noiva')['role_family'])->toBe('principal')
        ->and(collect($graph['people'])->firstWhere('display_name', 'Noiva')['media_count'])->toBe(8)
        ->and($graph['relations'])->toHaveCount(1)
        ->and($graph['relations'][0]['co_photo_count'])->toBe(5)
        ->and($graph['stats']['people_count'])->toBe(2)
        ->and($graph['stats']['relation_count'])->toBe(1)
        ->and($graph['filters']['relation_types'])->toContain('spouse_of')
        ->and(collect($graph['groups'])->pluck('key'))->toContain('couple');
});

<?php

use App\Modules\EventOperations\Models\EventOperationSnapshot;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Carbon;

it('persists event operation snapshots with versioned payload casts', function () {
    $event = Event::factory()->active()->create();

    $snapshot = EventOperationSnapshot::factory()->create([
        'event_id' => $event->id,
        'snapshot_json' => [
            'health' => ['status' => 'attention'],
            'stations' => [
                ['station_key' => 'intake', 'queue_depth' => 4],
            ],
        ],
        'server_time' => '2026-04-12 18:42:15',
        'updated_at' => '2026-04-12 18:42:20',
    ]);

    expect($snapshot->getTable())->toBe('event_operation_snapshots')
        ->and($snapshot->snapshot_json)->toBeArray()
        ->and($snapshot->snapshot_json['health']['status'])->toBe('attention')
        ->and($snapshot->server_time)->toBeInstanceOf(Carbon::class)
        ->and($snapshot->updated_at)->toBeInstanceOf(Carbon::class)
        ->and($snapshot->event)->not->toBeNull()
        ->and($snapshot->event->is($event))->toBeTrue();
});

it('keeps a single materialized snapshot row per event', function () {
    $event = Event::factory()->active()->create();

    EventOperationSnapshot::factory()->create([
        'event_id' => $event->id,
        'snapshot_version' => 4,
    ]);

    expect(fn () => EventOperationSnapshot::factory()->create([
        'event_id' => $event->id,
        'snapshot_version' => 5,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

<?php

namespace App\Modules\EventOperations\Data;

use Spatie\LaravelData\Data;

class EventOperationsRoomData extends Data
{
    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $health
     * @param array<string, mixed> $connection
     * @param array<string, mixed> $counters
     * @param array<int, array<string, mixed>> $stations
     * @param array<int, array<string, mixed>> $alerts
     * @param array<string, mixed> $wall
     * @param array<int, array<string, mixed>> $timeline
     */
    public function __construct(
        public int $schema_version,
        public int $snapshot_version,
        public ?string $timeline_cursor,
        public int $event_sequence,
        public string $server_time,
        public array $event,
        public array $health,
        public array $connection,
        public array $counters,
        public array $stations,
        public array $alerts,
        public array $wall,
        public array $timeline,
    ) {}
}

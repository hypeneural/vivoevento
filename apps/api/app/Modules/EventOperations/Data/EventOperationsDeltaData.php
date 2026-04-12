<?php

namespace App\Modules\EventOperations\Data;

use Spatie\LaravelData\Data;

class EventOperationsDeltaData extends Data
{
    /**
     * @param array<string, mixed>|null $station_delta
     * @param array<string, mixed>|null $timeline_entry
     * @param array<string, mixed>|null $alert
     * @param array<string, mixed>|null $health
     * @param array<string, mixed>|null $snapshot
     */
    public function __construct(
        public int $schema_version,
        public int $snapshot_version,
        public ?string $timeline_cursor,
        public int $event_sequence,
        public string $server_time,
        public string $kind,
        public string $broadcast_priority,
        public ?array $station_delta = null,
        public ?array $timeline_entry = null,
        public ?array $alert = null,
        public ?array $health = null,
        public ?array $snapshot = null,
        public bool $resync_required = false,
    ) {}
}

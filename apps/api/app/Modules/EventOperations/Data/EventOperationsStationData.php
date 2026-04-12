<?php

namespace App\Modules\EventOperations\Data;

use Spatie\LaravelData\Data;

class EventOperationsStationData extends Data
{
    /**
     * @param array<int, array<string, mixed>> $recent_items
     */
    public function __construct(
        public string $station_key,
        public string $label,
        public string $health,
        public int $backlog_count,
        public int $queue_depth,
        public float $station_load,
        public int $throughput_per_minute,
        public array $recent_items,
        public string $animation_hint,
        public string $render_group,
        public ?string $dominant_reason,
        public string $updated_at,
    ) {}
}

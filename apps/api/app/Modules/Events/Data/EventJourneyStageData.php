<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyStageData extends Data
{
    /**
     * @param array<int, EventJourneyNodeData> $nodes
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public int $position,
        public array $nodes,
    ) {}
}

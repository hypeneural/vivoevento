<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyScenarioData extends Data
{
    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $expected_node_ids
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public array $input,
        public array $expected_node_ids,
    ) {}
}

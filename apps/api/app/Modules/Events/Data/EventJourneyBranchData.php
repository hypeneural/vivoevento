<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyBranchData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $target_node_id = null,
        public bool $active = true,
        public string $status = 'active',
        public ?string $summary = null,
        public array $conditions = [],
    ) {}
}

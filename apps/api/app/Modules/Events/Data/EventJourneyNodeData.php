<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyNodeData extends Data
{
    /**
     * @param array<int, EventJourneyBranchData> $branches
     * @param array<string, mixed> $config_preview
     * @param array<int, string> $warnings
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $id,
        public string $stage,
        public string $kind,
        public string $label,
        public string $description,
        public bool $active,
        public bool $editable,
        public string $status,
        public string $summary,
        public array $config_preview = [],
        public array $branches = [],
        public array $warnings = [],
        public array $meta = [],
    ) {}
}

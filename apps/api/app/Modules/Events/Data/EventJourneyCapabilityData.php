<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyCapabilityData extends Data
{
    /**
     * @param array<string, mixed> $config_preview
     */
    public function __construct(
        public string $id,
        public string $label,
        public bool $enabled,
        public bool $available,
        public bool $editable,
        public ?string $reason = null,
        public array $config_preview = [],
    ) {}
}

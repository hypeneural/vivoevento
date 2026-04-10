<?php

namespace App\Modules\Events\Data;

use Spatie\LaravelData\Data;

class EventJourneyProjectionData extends Data
{
    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $intake_defaults
     * @param array<string, mixed> $intake_channels
     * @param array<string, mixed> $settings
     * @param array<string, EventJourneyCapabilityData> $capabilities
     * @param array<int, EventJourneyStageData> $stages
     * @param array<int, string> $warnings
     * @param array<int, EventJourneyScenarioData> $simulation_presets
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public string $version,
        public array $event,
        public array $intake_defaults,
        public array $intake_channels,
        public array $settings,
        public array $capabilities,
        public array $stages,
        public array $warnings,
        public array $simulation_presets,
        public array $summary = [],
    ) {}
}

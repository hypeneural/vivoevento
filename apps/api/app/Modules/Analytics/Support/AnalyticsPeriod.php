<?php

namespace App\Modules\Analytics\Support;

use Carbon\CarbonImmutable;

class AnalyticsPeriod
{
    public function __construct(
        public readonly string $preset,
        public readonly CarbonImmutable $dateFrom,
        public readonly CarbonImmutable $dateTo,
        public readonly CarbonImmutable $previousDateFrom,
        public readonly CarbonImmutable $previousDateTo,
    ) {}

    public function filters(): array
    {
        return [
            'period' => $this->preset,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'comparison' => [
                'date_from' => $this->previousDateFrom->toDateString(),
                'date_to' => $this->previousDateTo->toDateString(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function days(): array
    {
        $days = [];
        $cursor = $this->dateFrom;

        while ($cursor->lessThanOrEqualTo($this->dateTo)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}

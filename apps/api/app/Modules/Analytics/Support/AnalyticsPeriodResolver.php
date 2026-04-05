<?php

namespace App\Modules\Analytics\Support;

use Carbon\CarbonImmutable;

class AnalyticsPeriodResolver
{
    public function resolve(
        string $preset = '30d',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $timezone = null,
    ): AnalyticsPeriod {
        $tz = $timezone ?: (string) config('app.timezone', 'America/Sao_Paulo');
        $today = CarbonImmutable::now($tz);

        [$from, $to] = match ($preset) {
            '7d' => [$today->subDays(6)->startOfDay(), $today->endOfDay()],
            '90d' => [$today->subDays(89)->startOfDay(), $today->endOfDay()],
            'custom' => [
                CarbonImmutable::parse((string) $dateFrom, $tz)->startOfDay(),
                CarbonImmutable::parse((string) $dateTo, $tz)->endOfDay(),
            ],
            default => [$today->subDays(29)->startOfDay(), $today->endOfDay()],
        };

        $days = $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $from->subDays($days)->startOfDay();

        return new AnalyticsPeriod(
            preset: $preset,
            dateFrom: $from,
            dateTo: $to,
            previousDateFrom: $previousFrom,
            previousDateTo: $previousTo,
        );
    }
}

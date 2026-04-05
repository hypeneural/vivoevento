<?php

namespace App\Modules\Analytics\Queries;

use App\Modules\Analytics\Services\AnalyticsMetricsService;
use App\Modules\Analytics\Support\AnalyticsPeriod;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

class BuildPlatformAnalyticsQuery
{
    public function __construct(
        private readonly AnalyticsMetricsService $metrics,
        private readonly AnalyticsPeriod $period,
        private readonly ?int $organizationId = null,
        private readonly ?int $clientId = null,
        private readonly ?string $eventStatus = null,
        private readonly ?string $module = null,
    ) {}

    public function execute(): array
    {
        $eventsQuery = $this->scopedEventsQuery();
        $currentSummary = $this->metrics->summaryForEvents($eventsQuery, $this->period->dateFrom, $this->period->dateTo);
        $previousSummary = $this->metrics->summaryForEvents($eventsQuery, $this->period->previousDateFrom, $this->period->previousDateTo);

        return [
            'filters' => [
                ...$this->period->filters(),
                'organization_id' => $this->organizationId,
                'client_id' => $this->clientId,
                'event_status' => $this->eventStatus,
                'module' => $this->module,
            ],
            'summary' => $currentSummary,
            'deltas' => $this->metrics->deltasFromSummaries($currentSummary, $previousSummary),
            'timelines' => [
                'media' => $this->metrics->mediaTimelineForEvents($eventsQuery, $this->period),
                'traffic' => $this->metrics->trafficTimelineForEvents($eventsQuery, $this->period, $this->module),
                'play' => $this->metrics->playTimelineForEvents($eventsQuery, $this->period, $this->module),
            ],
            'breakdowns' => [
                'modules' => $this->metrics->moduleBreakdownFromSummary($currentSummary),
                'source_types' => $this->metrics->sourceTypeBreakdownForEvents($eventsQuery, $this->period->dateFrom, $this->period->dateTo),
                'event_statuses' => $this->metrics->eventStatusBreakdown($eventsQuery),
            ],
            'rankings' => [
                'top_events' => $this->metrics->topEvents($eventsQuery, $this->period->dateFrom, $this->period->dateTo),
            ],
        ];
    }

    private function scopedEventsQuery(): Builder
    {
        return Event::query()
            ->when($this->organizationId !== null, fn (Builder $query) => $query->where('organization_id', $this->organizationId))
            ->when($this->clientId !== null, fn (Builder $query) => $query->where('client_id', $this->clientId))
            ->when($this->eventStatus !== null, fn (Builder $query) => $query->where('status', $this->eventStatus))
            ->when($this->module !== null, function (Builder $query) {
                $query->whereHas('modules', function (Builder $moduleQuery) {
                    $moduleQuery
                        ->where('module_key', $this->module)
                        ->where('is_enabled', true);
                });
            });
    }
}

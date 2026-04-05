<?php

namespace App\Modules\Analytics\Queries;

use App\Modules\Analytics\Services\AnalyticsMetricsService;
use App\Modules\Analytics\Support\AnalyticsPeriod;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

class BuildEventAnalyticsQuery
{
    public function __construct(
        private readonly AnalyticsMetricsService $metrics,
        private readonly Event $event,
        private readonly AnalyticsPeriod $period,
        private readonly ?string $module = null,
    ) {}

    public function execute(): array
    {
        $eventsQuery = $this->scopedEventsQuery();
        $currentSummary = $this->metrics->summaryForEvents($eventsQuery, $this->period->dateFrom, $this->period->dateTo);
        $previousSummary = $this->metrics->summaryForEvents($eventsQuery, $this->period->previousDateFrom, $this->period->previousDateTo);

        $this->event->loadMissing([
            'organization:id,trade_name,legal_name,slug',
            'client:id,name',
            'modules:event_id,module_key,is_enabled',
            'playSettings:id,event_id,is_enabled,ranking_enabled',
        ]);

        return [
            'event' => (new EventResource($this->event))->toArray(request()),
            'filters' => [
                ...$this->period->filters(),
                'module' => $this->module,
            ],
            'summary' => $currentSummary,
            'deltas' => $this->metrics->deltasFromSummaries($currentSummary, $previousSummary),
            'funnel' => $this->metrics->funnelFromSummary($currentSummary),
            'timelines' => [
                'media' => $this->metrics->mediaTimelineForEvents($eventsQuery, $this->period),
                'traffic' => $this->metrics->trafficTimelineForEvents($eventsQuery, $this->period, $this->module),
                'play' => $this->metrics->playTimelineForEvents($eventsQuery, $this->period, $this->module),
            ],
            'breakdowns' => [
                'source_types' => $this->metrics->sourceTypeBreakdownForEvents($eventsQuery, $this->period->dateFrom, $this->period->dateTo),
                'surfaces' => $this->metrics->surfaceBreakdownFromSummary($currentSummary, $this->module),
            ],
            'play' => $this->metrics->playOverviewForEvent($this->event, $this->period->dateFrom, $this->period->dateTo, $this->module),
        ];
    }

    private function scopedEventsQuery(): Builder
    {
        return Event::query()->whereKey($this->event->id);
    }
}

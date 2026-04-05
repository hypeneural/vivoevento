<?php

namespace App\Modules\Hub\Queries;

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use App\Modules\Hub\Models\EventHubSetting;
use App\Modules\Hub\Support\HubPayloadFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetEventHubInsightsQuery
{
    private const CLICK_EVENT_NAMES = ['hub.button_click', 'hub.social_click', 'hub.sponsor_click'];

    public function __construct(
        private readonly HubPayloadFactory $payloads,
    ) {}

    public function execute(Event $event, EventHubSetting $settings, int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $today = CarbonImmutable::now();
        $from = $today->subDays($days - 1)->startOfDay();
        $to = $today->endOfDay();

        $buttonCatalog = collect($this->payloads->clickTargets($event, $settings))
            ->mapWithKeys(fn (array $button) => [
                (string) $button['id'] => [
                    'button_id' => (string) $button['id'],
                    'label' => (string) $button['label'],
                    'type' => (string) $button['type'],
                    'preset_key' => $button['preset_key'] ?? null,
                    'icon' => (string) $button['icon'],
                    'resolved_url' => $button['resolved_url'] ?? null,
                    'is_visible' => (bool) ($button['is_visible'] ?? true),
                    'clicks' => 0,
                    'last_clicked_at' => null,
                ],
            ]);

        $events = AnalyticsEvent::query()
            ->where('event_id', $event->id)
            ->where('channel', 'hub')
            ->whereIn('event_name', ['hub.page_view', ...self::CLICK_EVENT_NAMES])
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get(['event_name', 'actor_type', 'actor_id', 'metadata_json', 'occurred_at']);

        $pageViews = $events->where('event_name', 'hub.page_view')->count();
        $buttonClicks = $events->filter(
            fn (AnalyticsEvent $analyticsEvent) => in_array($analyticsEvent->event_name, self::CLICK_EVENT_NAMES, true)
        )->count();
        $uniqueVisitors = $events
            ->where('event_name', 'hub.page_view')
            ->map(fn (AnalyticsEvent $analyticsEvent) => "{$analyticsEvent->actor_type}:{$analyticsEvent->actor_id}")
            ->unique()
            ->count();

        $timeline = $this->buildTimeline($events, $from, $days);
        $buttonPerformance = $this->buildButtonPerformance($events, $buttonCatalog);
        $topButtons = $buttonPerformance
            ->filter(fn (array $button) => $button['clicks'] > 0)
            ->values()
            ->take(5)
            ->all();

        return [
            'summary' => [
                'page_views' => $pageViews,
                'unique_visitors' => $uniqueVisitors,
                'button_clicks' => $buttonClicks,
                'ctr' => $pageViews > 0 ? round(($buttonClicks / $pageViews) * 100, 2) : 0.0,
                'active_buttons' => $buttonPerformance->where('clicks', '>', 0)->count(),
                'last_activity_at' => $events->last()?->occurred_at?->toIso8601String(),
            ],
            'buttons' => $buttonPerformance->values()->all(),
            'top_buttons' => $topButtons,
            'timeline' => $timeline,
            'window_days' => $days,
            'generated_at' => $today->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{date: string, page_views: int, button_clicks: int, ctr: float}>
     */
    private function buildTimeline(Collection $events, CarbonImmutable $from, int $days): array
    {
        $timeline = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->addDays($offset)->toDateString();
            $timeline[$date] = [
                'date' => $date,
                'page_views' => 0,
                'button_clicks' => 0,
                'ctr' => 0.0,
            ];
        }

        foreach ($events as $analyticsEvent) {
            $date = $analyticsEvent->occurred_at?->toDateString();

            if (! $date || ! array_key_exists($date, $timeline)) {
                continue;
            }

            if ($analyticsEvent->event_name === 'hub.page_view') {
                $timeline[$date]['page_views']++;
            }

            if (in_array($analyticsEvent->event_name, self::CLICK_EVENT_NAMES, true)) {
                $timeline[$date]['button_clicks']++;
            }
        }

        return collect($timeline)
            ->map(function (array $point) {
                $point['ctr'] = $point['page_views'] > 0
                    ? round(($point['button_clicks'] / $point['page_views']) * 100, 2)
                    : 0.0;

                return $point;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $buttonCatalog
     * @return Collection<int, array<string, mixed>>
     */
    private function buildButtonPerformance(Collection $events, Collection $buttonCatalog): Collection
    {
        $performance = $buttonCatalog->map(fn (array $button) => $button);

        foreach ($events->filter(
            fn (AnalyticsEvent $analyticsEvent) => in_array($analyticsEvent->event_name, self::CLICK_EVENT_NAMES, true)
        ) as $analyticsEvent) {
            $buttonId = (string) data_get($analyticsEvent->metadata_json, 'button_id', '');

            if ($buttonId === '' || ! $performance->has($buttonId)) {
                continue;
            }

            $current = $performance->get($buttonId);
            $current['clicks']++;
            $current['last_clicked_at'] = $analyticsEvent->occurred_at?->toIso8601String();
            $performance->put($buttonId, $current);
        }

        return $performance
            ->sort(function (array $left, array $right) {
                $clickDelta = $right['clicks'] <=> $left['clicks'];

                if ($clickDelta !== 0) {
                    return $clickDelta;
                }

                return strcmp(
                    (string) ($right['last_clicked_at'] ?? ''),
                    (string) ($left['last_clicked_at'] ?? ''),
                );
            })
            ->values();
    }
}

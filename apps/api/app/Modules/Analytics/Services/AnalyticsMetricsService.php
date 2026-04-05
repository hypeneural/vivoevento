<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Analytics\Support\AnalyticsPeriod;
use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Play\Models\PlayGameSession;
use App\Shared\Support\AssetUrlService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsMetricsService
{
    private const ANALYTICS_EVENT_TO_SUMMARY_KEY = [
        'hub.page_view' => 'hub_views',
        'gallery.page_view' => 'gallery_views',
        'wall.page_view' => 'wall_views',
        'upload.page_view' => 'upload_views',
        'upload.completed' => 'upload_completed',
        'play.page_view' => 'play_views',
        'play.game_view' => 'play_game_views',
    ];

    private const MODULE_LABELS = [
        'live' => 'Live',
        'hub' => 'Hub',
        'wall' => 'Wall',
        'play' => 'Play',
    ];

    private const SURFACE_LABELS = [
        'upload' => 'Upload',
        'gallery' => 'Galeria',
        'hub' => 'Hub',
        'wall' => 'Wall',
        'play' => 'Play',
    ];

    private const EVENT_STATUS_LABELS = [
        'draft' => 'Rascunho',
        'scheduled' => 'Agendado',
        'active' => 'Ativo',
        'paused' => 'Pausado',
        'ended' => 'Encerrado',
        'archived' => 'Arquivado',
    ];

    public function __construct(
        private readonly AssetUrlService $assetUrls,
    ) {}

    public function summaryForEvents(Builder $eventsQuery, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $mediaBase = $this->mediaBaseQuery($eventsQuery)->whereBetween('created_at', [$from, $to]);

        $uploadsReceived = (clone $mediaBase)->count();
        $uploadsApproved = (clone $mediaBase)
            ->where('moderation_status', ModerationStatus::Approved->value)
            ->count();
        $uploadsPublished = (clone $mediaBase)
            ->where('publication_status', PublicationStatus::Published->value)
            ->count();

        $analyticsCounts = $this->analyticsCountMapForEvents($eventsQuery, $from, $to);
        $playSummary = $this->playSummaryForEvents($eventsQuery, $from, $to);

        $summary = [
            'uploads_received' => $uploadsReceived,
            'uploads_approved' => $uploadsApproved,
            'uploads_published' => $uploadsPublished,
            'approval_rate' => $uploadsReceived > 0 ? round(($uploadsApproved / $uploadsReceived) * 100, 2) : 0.0,
            'publication_rate' => $uploadsReceived > 0 ? round(($uploadsPublished / $uploadsReceived) * 100, 2) : 0.0,
            'hub_views' => $analyticsCounts['hub_views'],
            'gallery_views' => $analyticsCounts['gallery_views'],
            'wall_views' => $analyticsCounts['wall_views'],
            'upload_views' => $analyticsCounts['upload_views'],
            'upload_completed' => $analyticsCounts['upload_completed'],
            'play_views' => $analyticsCounts['play_views'],
            'play_game_views' => $analyticsCounts['play_game_views'],
            'play_sessions' => $playSummary['play_sessions'],
            'unique_players' => $playSummary['unique_players'],
        ];

        $summary['public_interactions'] = $summary['hub_views']
            + $summary['gallery_views']
            + $summary['wall_views']
            + $summary['upload_views']
            + $summary['upload_completed']
            + $summary['play_views']
            + $summary['play_game_views']
            + $summary['play_sessions'];

        return $summary;
    }

    public function deltasFromSummaries(array $current, array $previous): array
    {
        $deltas = [];

        foreach ($current as $key => $value) {
            $previousValue = $previous[$key] ?? 0;

            if (str_ends_with($key, '_rate')) {
                $difference = round((float) $value - (float) $previousValue, 2);

                $deltas[$key] = [
                    'type' => 'points',
                    'value' => $difference,
                    'difference' => $difference,
                    'previous' => round((float) $previousValue, 2),
                    'direction' => $difference <=> 0,
                ];

                continue;
            }

            $difference = (int) $value - (int) $previousValue;
            $percentage = match (true) {
                (int) $previousValue === 0 && (int) $value === 0 => 0.0,
                (int) $previousValue === 0 => 100.0,
                default => round(($difference / (int) $previousValue) * 100, 2),
            };

            $deltas[$key] = [
                'type' => 'percentage',
                'value' => $percentage,
                'difference' => $difference,
                'previous' => (int) $previousValue,
                'direction' => $difference <=> 0,
            ];
        }

        return $deltas;
    }

    public function mediaTimelineForEvents(Builder $eventsQuery, AnalyticsPeriod $period): array
    {
        $received = $this->groupMediaCountsByDate(
            (clone $this->mediaBaseQuery($eventsQuery))
                ->whereBetween('created_at', [$period->dateFrom, $period->dateTo]),
            'created_at',
        );

        $approved = $this->groupMediaCountsByDate(
            (clone $this->mediaBaseQuery($eventsQuery))
                ->whereBetween('created_at', [$period->dateFrom, $period->dateTo])
                ->where('moderation_status', ModerationStatus::Approved->value),
            'created_at',
        );

        $published = $this->groupPublishedMediaCountsByDate($eventsQuery, $period);

        return collect($period->days())->map(function (string $date) use ($received, $approved, $published) {
            $receivedCount = (int) ($received[$date] ?? 0);
            $approvedCount = (int) ($approved[$date] ?? 0);
            $publishedCount = (int) ($published[$date] ?? 0);

            return [
                'date' => $date,
                'uploads_received' => $receivedCount,
                'uploads_approved' => $approvedCount,
                'uploads_published' => $publishedCount,
                'approval_rate' => $receivedCount > 0 ? round(($approvedCount / $receivedCount) * 100, 2) : 0.0,
                'publication_rate' => $receivedCount > 0 ? round(($publishedCount / $receivedCount) * 100, 2) : 0.0,
            ];
        })->all();
    }

    public function trafficTimelineForEvents(Builder $eventsQuery, AnalyticsPeriod $period, ?string $module = null): array
    {
        $rows = AnalyticsEvent::query()
            ->whereIn('event_id', $this->eventIdsSubquery($eventsQuery))
            ->whereBetween('occurred_at', [$period->dateFrom, $period->dateTo])
            ->whereIn('event_name', array_keys(self::ANALYTICS_EVENT_TO_SUMMARY_KEY))
            ->selectRaw('DATE(occurred_at) as metric_date')
            ->selectRaw("SUM(CASE WHEN event_name = 'hub.page_view' THEN 1 ELSE 0 END) as hub_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'gallery.page_view' THEN 1 ELSE 0 END) as gallery_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'wall.page_view' THEN 1 ELSE 0 END) as wall_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'upload.page_view' THEN 1 ELSE 0 END) as upload_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'upload.completed' THEN 1 ELSE 0 END) as upload_completed")
            ->selectRaw("SUM(CASE WHEN event_name = 'play.page_view' THEN 1 ELSE 0 END) as play_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'play.game_view' THEN 1 ELSE 0 END) as play_game_views")
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->keyBy('metric_date');

        return collect($period->days())->map(function (string $date) use ($rows, $module) {
            $row = $rows->get($date);

            $point = [
                'date' => $date,
                'hub_views' => (int) ($row->hub_views ?? 0),
                'gallery_views' => (int) ($row->gallery_views ?? 0),
                'wall_views' => (int) ($row->wall_views ?? 0),
                'upload_views' => (int) ($row->upload_views ?? 0),
                'upload_completed' => (int) ($row->upload_completed ?? 0),
                'play_views' => (int) ($row->play_views ?? 0),
                'play_game_views' => (int) ($row->play_game_views ?? 0),
            ];

            $point = $this->filterTrafficPointByModule($point, $module);
            $point['public_interactions'] = $point['hub_views']
                + $point['gallery_views']
                + $point['wall_views']
                + $point['upload_views']
                + $point['upload_completed']
                + $point['play_views']
                + $point['play_game_views'];

            return $point;
        })->all();
    }

    public function playTimelineForEvents(Builder $eventsQuery, AnalyticsPeriod $period, ?string $module = null): array
    {
        if ($module !== null && $module !== 'play') {
            return collect($period->days())->map(fn (string $date) => [
                'date' => $date,
                'sessions' => 0,
                'unique_players' => 0,
            ])->all();
        }

        $rows = $this->playSessionsBaseQuery($eventsQuery)
            ->whereBetween('play_game_sessions.started_at', [$period->dateFrom, $period->dateTo])
            ->selectRaw('DATE(play_game_sessions.started_at) as metric_date')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT play_game_sessions.player_identifier) as unique_players')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->keyBy('metric_date');

        return collect($period->days())->map(function (string $date) use ($rows) {
            $row = $rows->get($date);

            return [
                'date' => $date,
                'sessions' => (int) ($row->sessions ?? 0),
                'unique_players' => (int) ($row->unique_players ?? 0),
            ];
        })->all();
    }

    public function sourceTypeBreakdownForEvents(Builder $eventsQuery, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->mediaBaseQuery($eventsQuery)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(source_type, \'unknown\') as source_type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('source_type')
            ->orderByDesc('aggregate')
            ->get();

        $total = (int) $rows->sum(fn ($row) => (int) $row->aggregate);

        return $rows->map(fn ($row) => [
            'key' => (string) $row->source_type,
            'label' => $this->sourceTypeLabel((string) $row->source_type),
            'count' => (int) $row->aggregate,
            'percentage' => $total > 0 ? round(((int) $row->aggregate / $total) * 100, 2) : 0.0,
        ])->values()->all();
    }

    public function eventStatusBreakdown(Builder $eventsQuery): array
    {
        $counts = (clone $eventsQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = array_sum(array_map('intval', $counts->all()));

        return collect(EventStatus::cases())->map(function (EventStatus $status) use ($counts, $total) {
            $count = (int) ($counts[$status->value] ?? 0);

            return [
                'key' => $status->value,
                'label' => self::EVENT_STATUS_LABELS[$status->value] ?? ucfirst($status->value),
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
            ];
        })->all();
    }

    public function moduleBreakdownFromSummary(array $summary): array
    {
        $counts = [
            'live' => (int) $summary['uploads_received'] + (int) $summary['gallery_views'] + (int) $summary['upload_views'] + (int) $summary['upload_completed'],
            'hub' => (int) $summary['hub_views'],
            'wall' => (int) $summary['wall_views'],
            'play' => (int) $summary['play_views'] + (int) $summary['play_game_views'] + (int) $summary['play_sessions'],
        ];

        $total = array_sum($counts);

        return collect($counts)->map(fn (int $count, string $key) => [
            'key' => $key,
            'label' => self::MODULE_LABELS[$key] ?? ucfirst($key),
            'count' => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
        ])->values()->all();
    }

    public function surfaceBreakdownFromSummary(array $summary, ?string $module = null): array
    {
        $surfaces = [
            'upload' => (int) $summary['upload_views'] + (int) $summary['upload_completed'],
            'gallery' => (int) $summary['gallery_views'],
            'hub' => (int) $summary['hub_views'],
            'wall' => (int) $summary['wall_views'],
            'play' => (int) $summary['play_views'] + (int) $summary['play_game_views'] + (int) $summary['play_sessions'],
        ];

        if ($module !== null) {
            $surfaces = collect($surfaces)
                ->filter(fn (int $count, string $key) => $this->surfaceMatchesModule($key, $module))
                ->all();
        }

        $total = array_sum($surfaces);

        return collect($surfaces)->map(fn (int $count, string $key) => [
            'key' => $key,
            'label' => self::SURFACE_LABELS[$key] ?? ucfirst($key),
            'count' => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.0,
        ])->values()->all();
    }

    public function topEvents(Builder $eventsQuery, CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $events = (clone $eventsQuery)
            ->with([
                'organization:id,trade_name,legal_name,slug',
                'client:id,name',
                'modules:event_id,module_key,is_enabled',
            ])
            ->get([
                'id',
                'organization_id',
                'client_id',
                'title',
                'slug',
                'status',
                'cover_image_path',
            ]);

        if ($events->isEmpty()) {
            return [];
        }

        $eventIds = $events->pluck('id');

        $mediaRows = EventMedia::query()
            ->whereIn('event_id', $eventIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('event_id')
            ->selectRaw('COUNT(*) as uploads_received')
            ->selectRaw("SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) as uploads_approved")
            ->selectRaw("SUM(CASE WHEN publication_status = 'published' THEN 1 ELSE 0 END) as uploads_published")
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $analyticsRows = AnalyticsEvent::query()
            ->whereIn('event_id', $eventIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_name', array_keys(self::ANALYTICS_EVENT_TO_SUMMARY_KEY))
            ->selectRaw('event_id')
            ->selectRaw("SUM(CASE WHEN event_name = 'hub.page_view' THEN 1 ELSE 0 END) as hub_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'gallery.page_view' THEN 1 ELSE 0 END) as gallery_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'wall.page_view' THEN 1 ELSE 0 END) as wall_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'upload.page_view' THEN 1 ELSE 0 END) as upload_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'upload.completed' THEN 1 ELSE 0 END) as upload_completed")
            ->selectRaw("SUM(CASE WHEN event_name = 'play.page_view' THEN 1 ELSE 0 END) as play_views")
            ->selectRaw("SUM(CASE WHEN event_name = 'play.game_view' THEN 1 ELSE 0 END) as play_game_views")
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $playRows = PlayGameSession::query()
            ->join('play_event_games', 'play_event_games.id', '=', 'play_game_sessions.event_game_id')
            ->whereIn('play_event_games.event_id', $eventIds)
            ->whereBetween('play_game_sessions.started_at', [$from, $to])
            ->selectRaw('play_event_games.event_id')
            ->selectRaw('COUNT(*) as play_sessions')
            ->groupBy('play_event_games.event_id')
            ->get()
            ->keyBy('event_id');

        $items = $events->map(function (Event $event) use ($mediaRows, $analyticsRows, $playRows) {
            $media = $mediaRows->get($event->id);
            $analytics = $analyticsRows->get($event->id);
            $play = $playRows->get($event->id);

            $uploads = (int) ($media->uploads_received ?? 0);
            $approved = (int) ($media->uploads_approved ?? 0);
            $published = (int) ($media->uploads_published ?? 0);
            $playSessions = (int) ($play->play_sessions ?? 0);

            $publicInteractions = (int) ($analytics->hub_views ?? 0)
                + (int) ($analytics->gallery_views ?? 0)
                + (int) ($analytics->wall_views ?? 0)
                + (int) ($analytics->upload_views ?? 0)
                + (int) ($analytics->upload_completed ?? 0)
                + (int) ($analytics->play_views ?? 0)
                + (int) ($analytics->play_game_views ?? 0)
                + $playSessions;

            return [
                'event_id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status?->value,
                'organization_name' => $event->organization?->trade_name
                    ?? $event->organization?->legal_name
                    ?? $event->organization?->slug,
                'client_name' => $event->client?->name,
                'cover_image_url' => $this->assetUrls->toPublicUrl($event->cover_image_path),
                'uploads' => $uploads,
                'approval_rate' => $uploads > 0 ? round(($approved / $uploads) * 100, 2) : 0.0,
                'publication_rate' => $uploads > 0 ? round(($published / $uploads) * 100, 2) : 0.0,
                'hub_views' => (int) ($analytics->hub_views ?? 0),
                'gallery_views' => (int) ($analytics->gallery_views ?? 0),
                'wall_views' => (int) ($analytics->wall_views ?? 0),
                'play_sessions' => $playSessions,
                'public_interactions' => $publicInteractions,
            ];
        });

        $totalInteractions = (int) $items->sum('public_interactions');

        return $items
            ->map(function (array $item) use ($totalInteractions) {
                $item['share_percentage'] = $totalInteractions > 0
                    ? round(($item['public_interactions'] / $totalInteractions) * 100, 2)
                    : 0.0;

                return $item;
            })
            ->sort(function (array $left, array $right) {
                $byInteractions = $right['public_interactions'] <=> $left['public_interactions'];

                if ($byInteractions !== 0) {
                    return $byInteractions;
                }

                return $right['uploads'] <=> $left['uploads'];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    public function funnelFromSummary(array $summary): array
    {
        $received = (int) ($summary['uploads_received'] ?? 0);
        $approved = (int) ($summary['uploads_approved'] ?? 0);
        $published = (int) ($summary['uploads_published'] ?? 0);

        return [
            ['key' => 'received', 'label' => 'Recebidos', 'count' => $received, 'percentage' => $received > 0 ? 100.0 : 0.0],
            ['key' => 'approved', 'label' => 'Aprovados', 'count' => $approved, 'percentage' => $received > 0 ? round(($approved / $received) * 100, 2) : 0.0],
            ['key' => 'published', 'label' => 'Publicados', 'count' => $published, 'percentage' => $received > 0 ? round(($published / $received) * 100, 2) : 0.0],
        ];
    }

    public function playOverviewForEvent(Event $event, CarbonImmutable $from, CarbonImmutable $to, ?string $module = null): ?array
    {
        $event->loadMissing(['playSettings', 'playGames.gameType', 'modules']);

        if (! $event->isModuleEnabled('play') || ! $event->playSettings?->is_enabled) {
            return null;
        }

        if ($module !== null && $module !== 'play') {
            return null;
        }

        $games = $event->playGames()
            ->with('gameType:id,key,name')
            ->orderBy('sort_order')
            ->get(['id', 'event_id', 'game_type_id', 'title', 'slug', 'is_active', 'sort_order', 'ranking_enabled']);

        $gameIds = $games->pluck('id');

        if ($gameIds->isEmpty()) {
            return [
                'enabled' => true,
                'ranking_enabled' => (bool) $event->playSettings->ranking_enabled,
                'games_count' => 0,
                'sessions' => 0,
                'unique_players' => 0,
                'games' => [],
            ];
        }

        $rows = PlayGameSession::query()
            ->whereIn('event_game_id', $gameIds)
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('event_game_id')
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT player_identifier) as unique_players')
            ->selectRaw("SUM(CASE WHEN status = 'finished' THEN 1 ELSE 0 END) as finished_sessions")
            ->groupBy('event_game_id')
            ->get()
            ->keyBy('event_game_id');

        $totalSessions = (int) $rows->sum(fn ($row) => (int) $row->sessions);
        $totalUniquePlayers = PlayGameSession::query()
            ->whereIn('event_game_id', $gameIds)
            ->whereBetween('started_at', [$from, $to])
            ->select('player_identifier')
            ->distinct()
            ->count('player_identifier');

        return [
            'enabled' => true,
            'ranking_enabled' => (bool) $event->playSettings->ranking_enabled,
            'games_count' => $games->count(),
            'sessions' => $totalSessions,
            'unique_players' => $totalUniquePlayers,
            'games' => $games->map(function ($game) use ($rows, $totalSessions) {
                $row = $rows->get($game->id);
                $sessions = (int) ($row->sessions ?? 0);
                $finishedSessions = (int) ($row->finished_sessions ?? 0);

                return [
                    'id' => $game->id,
                    'title' => $game->title,
                    'slug' => $game->slug,
                    'game_type_key' => $game->gameType?->key,
                    'game_type_name' => $game->gameType?->name,
                    'is_active' => (bool) $game->is_active,
                    'ranking_enabled' => (bool) $game->ranking_enabled,
                    'sessions' => $sessions,
                    'unique_players' => (int) ($row->unique_players ?? 0),
                    'completion_rate' => $sessions > 0 ? round(($finishedSessions / $sessions) * 100, 2) : 0.0,
                    'share_percentage' => $totalSessions > 0 ? round(($sessions / $totalSessions) * 100, 2) : 0.0,
                ];
            })->values()->all(),
        ];
    }

    private function analyticsCountMapForEvents(Builder $eventsQuery, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = AnalyticsEvent::query()
            ->whereIn('event_id', $this->eventIdsSubquery($eventsQuery))
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_name', array_keys(self::ANALYTICS_EVENT_TO_SUMMARY_KEY))
            ->selectRaw('event_name, COUNT(*) as aggregate')
            ->groupBy('event_name')
            ->pluck('aggregate', 'event_name');

        return collect(self::ANALYTICS_EVENT_TO_SUMMARY_KEY)
            ->mapWithKeys(fn (string $summaryKey, string $eventName) => [$summaryKey => (int) ($counts[$eventName] ?? 0)])
            ->all();
    }

    private function playSummaryForEvents(Builder $eventsQuery, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $baseQuery = $this->playSessionsBaseQuery($eventsQuery)
            ->whereBetween('play_game_sessions.started_at', [$from, $to]);

        $playSessions = (clone $baseQuery)->count();
        $uniquePlayers = (clone $baseQuery)
            ->select('play_game_sessions.player_identifier')
            ->distinct()
            ->count('play_game_sessions.player_identifier');

        return [
            'play_sessions' => $playSessions,
            'unique_players' => $uniquePlayers,
        ];
    }

    private function groupMediaCountsByDate(Builder $query, string $column): Collection
    {
        return $query
            ->selectRaw(sprintf('DATE(%s) as metric_date', $column))
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->pluck('aggregate', 'metric_date');
    }

    private function groupPublishedMediaCountsByDate(Builder $eventsQuery, AnalyticsPeriod $period): Collection
    {
        return $this->mediaBaseQuery($eventsQuery)
            ->where('publication_status', PublicationStatus::Published->value)
            ->where(function (Builder $query) use ($period) {
                $query
                    ->whereBetween('published_at', [$period->dateFrom, $period->dateTo])
                    ->orWhere(function (Builder $fallback) use ($period) {
                        $fallback
                            ->whereNull('published_at')
                            ->whereBetween('created_at', [$period->dateFrom, $period->dateTo]);
                    });
            })
            ->selectRaw('DATE(COALESCE(published_at, created_at)) as metric_date')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->pluck('aggregate', 'metric_date');
    }

    private function mediaBaseQuery(Builder $eventsQuery): Builder
    {
        return EventMedia::query()
            ->whereIn('event_id', $this->eventIdsSubquery($eventsQuery));
    }

    private function playSessionsBaseQuery(Builder $eventsQuery): Builder
    {
        return PlayGameSession::query()
            ->join('play_event_games', 'play_event_games.id', '=', 'play_game_sessions.event_game_id')
            ->whereIn('play_event_games.event_id', $this->eventIdsSubquery($eventsQuery));
    }

    private function eventIdsSubquery(Builder $eventsQuery): Builder
    {
        return (clone $eventsQuery)->reorder()->select('events.id');
    }

    private function filterTrafficPointByModule(array $point, ?string $module): array
    {
        if ($module === null) {
            return $point;
        }

        $surfaceToModule = [
            'hub_views' => 'hub',
            'gallery_views' => 'live',
            'wall_views' => 'wall',
            'upload_views' => 'live',
            'upload_completed' => 'live',
            'play_views' => 'play',
            'play_game_views' => 'play',
        ];

        foreach ($surfaceToModule as $key => $surfaceModule) {
            if ($surfaceModule !== $module) {
                $point[$key] = 0;
            }
        }

        return $point;
    }

    private function surfaceMatchesModule(string $surface, string $module): bool
    {
        return match ($module) {
            'live' => in_array($surface, ['upload', 'gallery'], true),
            default => $surface === $module,
        };
    }

    private function sourceTypeLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'public_upload' => 'Upload publico',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'channel' => 'Canal',
            'qrcode' => 'QR Code',
            'unknown' => 'Nao informado',
            default => str($sourceType)->replace('_', ' ')->title()->value(),
        };
    }
}

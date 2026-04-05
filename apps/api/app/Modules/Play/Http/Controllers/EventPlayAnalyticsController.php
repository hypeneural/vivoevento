<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Play\Http\Requests\ShowPlayAnalyticsRequest;
use App\Modules\Play\Http\Resources\PlayAdminSessionResource;
use App\Modules\Play\Http\Resources\PlayEventGameResource;
use App\Modules\Play\Queries\ListPlaySessionsQuery;
use App\Modules\Play\Services\GameAnalyticsService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventPlayAnalyticsController extends BaseController
{
    public function show(
        ShowPlayAnalyticsRequest $request,
        Event $event,
        GameAnalyticsService $analytics,
    ): JsonResponse {
        $this->authorize('viewPlay', $event);

        $validated = $request->validated();

        if (isset($validated['play_game_id'])) {
            abort_unless(
                $event->playGames()->whereKey((int) $validated['play_game_id'])->exists(),
                404,
            );
        }

        $sessionsQuery = (new ListPlaySessionsQuery(
            eventId: $event->id,
            playGameId: isset($validated['play_game_id']) ? (int) $validated['play_game_id'] : null,
            dateFrom: $validated['date_from'] ?? null,
            dateTo: $validated['date_to'] ?? null,
            status: $validated['status'] ?? null,
            search: $validated['search'] ?? null,
        ))->query();

        $sessions = (clone $sessionsQuery)
            ->orderByDesc('started_at')
            ->get();

        $recentSessions = $sessions
            ->take((int) ($validated['session_limit'] ?? 20))
            ->values();

        $games = $event->playGames()
            ->with(['gameType', 'assets.media.variants'])
            ->when(
                isset($validated['play_game_id']),
                fn ($query) => $query->whereKey((int) $validated['play_game_id']),
            )
            ->orderBy('sort_order')
            ->get();

        $gamesAnalytics = $games->map(function ($game) use ($analytics, $sessions) {
            $gameSessions = $sessions
                ->where('event_game_id', $game->id)
                ->values();

            return [
                'game' => new PlayEventGameResource($game),
                'analytics' => $analytics->summaryFromSessions($gameSessions),
            ];
        })->values();

        return $this->success([
            'filters' => [
                'play_game_id' => $validated['play_game_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'status' => $validated['status'] ?? null,
                'search' => $validated['search'] ?? null,
                'session_limit' => (int) ($validated['session_limit'] ?? 20),
            ],
            'summary' => $analytics->summaryFromSessions($sessions),
            'timeline' => $analytics->timelineFromSessions($sessions),
            'games' => $gamesAnalytics,
            'recent_sessions' => PlayAdminSessionResource::collection($recentSessions),
        ]);
    }
}

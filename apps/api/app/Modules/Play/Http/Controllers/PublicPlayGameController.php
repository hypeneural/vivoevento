<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use App\Modules\Play\Http\Requests\ShowPublicPlayGameRequest;
use App\Modules\Play\Http\Resources\PlayEventGameResource;
use App\Modules\Play\Http\Resources\PlayGameRankingResource;
use App\Modules\Play\Http\Resources\PlayGameSessionResource;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Services\GameAssetResolverService;
use App\Modules\Play\Services\GameSessionService;
use App\Modules\Play\Services\RankingService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPlayGameController extends BaseController
{
    public function show(
        ShowPublicPlayGameRequest $request,
        string $event,
        string $gameSlug,
        GameAssetResolverService $assets,
        RankingService $ranking,
        GameSessionService $sessions,
        AnalyticsTracker $analytics,
    ): JsonResponse {
        $game = $this->resolvePublicGame($event, $gameSlug);

        $leaderboard = $ranking->leaderboardWithPositions($game);
        $snapshot = $sessions->realtimePayload($game);

        $analytics->trackEvent(
            $game->event,
            'play.game_view',
            $request,
            [
                'surface' => 'play',
                'game_id' => $game->id,
                'game_slug' => $game->slug,
                'game_type_key' => $game->gameType?->key,
            ],
            channel: 'play',
        );

        return $this->success([
            'game' => new PlayEventGameResource($game->load(['gameType', 'assets.media.variants'])),
            'runtime' => [
                'assets' => $assets->resolve($game, $request->assetProfile()),
                'ranking' => PlayGameRankingResource::collection($leaderboard),
                'last_plays' => PlayGameSessionResource::collection($sessions->lastFinishedSessions($game)),
                'analytics' => $snapshot['analytics'],
                'realtime' => [
                    'channel' => "play.game.{$game->uuid}",
                    'events' => [
                        'leaderboard_updated' => 'play.leaderboard.updated',
                    ],
                ],
            ],
        ]);
    }

    public function ranking(string $event, string $gameSlug, RankingService $ranking): JsonResponse
    {
        $game = $this->resolvePublicGame($event, $gameSlug);
        $leaderboard = $ranking->leaderboardWithPositions($game);

        return $this->success(
            PlayGameRankingResource::collection($leaderboard),
        );
    }

    public function lastPlays(string $event, string $gameSlug, GameSessionService $sessions): JsonResponse
    {
        $game = $this->resolvePublicGame($event, $gameSlug);

        return $this->success(
            PlayGameSessionResource::collection($sessions->lastFinishedSessions($game)),
        );
    }

    private function resolvePublicGame(string $eventSlug, string $gameSlug): PlayEventGame
    {
        $event = Event::with(['modules', 'playGames.gameType'])
            ->where('slug', $eventSlug)
            ->firstOrFail();

        $settings = EventPlaySetting::query()->where('event_id', $event->id)->first();

        if (! $event->isModuleEnabled('play') || ! $settings?->is_enabled) {
            abort(404);
        }

        return PlayEventGame::query()
            ->with(['event', 'gameType', 'assets.media.variants'])
            ->where('event_id', $event->id)
            ->where('slug', $gameSlug)
            ->where('is_active', true)
            ->firstOrFail();
    }
}

<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\DTOs\HeartbeatGameSessionDTO;
use App\Modules\Play\DTOs\RecordGameMoveDTO;
use App\Modules\Play\DTOs\ResumeGameSessionDTO;
use App\Modules\Play\DTOs\StartGameSessionDTO;
use App\Modules\Play\Http\Requests\FinishPlaySessionRequest;
use App\Modules\Play\Http\Requests\HeartbeatPlaySessionRequest;
use App\Modules\Play\Http\Requests\ResumePlaySessionRequest;
use App\Modules\Play\Http\Requests\StartPlaySessionRequest;
use App\Modules\Play\Http\Requests\StorePlayMovesRequest;
use App\Modules\Play\Http\Resources\PlayGameRankingResource;
use App\Modules\Play\Http\Resources\PlayGameSessionResource;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Services\GameLaunchReadinessService;
use App\Modules\Play\Services\GameSessionService;
use App\Modules\Play\Services\RankingService;
use App\Modules\Play\Support\RuntimeAssetProfile;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicPlaySessionController extends BaseController
{
    public function start(
        StartPlaySessionRequest $request,
        string $event,
        string $gameSlug,
        GameSessionService $sessions,
        GameLaunchReadinessService $readiness,
    ): JsonResponse {
        $game = $this->resolvePublicGame($event, $gameSlug);
        $validated = $request->validated();
        $assetProfile = RuntimeAssetProfile::fromDevice($validated['device'] ?? null);
        $readinessState = $readiness->forGame($game, $assetProfile);

        if (! $readinessState->bootable) {
            return $this->error(
                'Este jogo ainda nao possui uma imagem valida para iniciar.',
                422,
                ['reason' => $readinessState->reason],
            );
        }

        $session = $sessions->start($game, new StartGameSessionDTO(
            eventGameId: $game->id,
            playerIdentifier: $validated['player_identifier'],
            playerName: $validated['player_name'] ?? null,
            device: $validated['device'] ?? null,
        ));

        return $this->success($sessions->bootPayload($session), 201);
    }

    public function heartbeat(
        HeartbeatPlaySessionRequest $request,
        string $sessionUuid,
        GameSessionService $sessions,
    ): JsonResponse {
        $validated = $request->validated();
        $session = $sessions->heartbeat(new HeartbeatGameSessionDTO(
            sessionUuid: $sessionUuid,
            state: $validated['state'],
            reason: $validated['reason'] ?? null,
            elapsedMs: isset($validated['elapsed_ms']) ? (int) $validated['elapsed_ms'] : null,
        ));

        return $this->success([
            'session' => new PlayGameSessionResource($session),
            'analytics' => $sessions->analytics($session),
        ]);
    }

    public function resume(
        ResumePlaySessionRequest $request,
        string $sessionUuid,
        GameSessionService $sessions,
    ): JsonResponse {
        $validated = $request->validated();
        $session = $sessions->findByUuid($sessionUuid, syncLifecycle: false);

        if (! $sessions->hasValidResumeToken($session, $validated['resume_token'])) {
            return $this->error('Token de retomada invalido.', 422);
        }

        if (! $sessions->canResume($session)) {
            $sessions->markAbandoned($session);

            return $this->error('A sessao expirou e nao pode mais ser retomada.', 410);
        }

        $resumedSession = $sessions->resume(new ResumeGameSessionDTO(
            sessionUuid: $sessionUuid,
            resumeToken: $validated['resume_token'],
        ));

        if ($resumedSession === null) {
            return $this->error('Nao foi possivel retomar a sessao.', 410);
        }

        return $this->success($sessions->bootPayload($resumedSession, includeRestore: true));
    }

    public function finish(
        FinishPlaySessionRequest $request,
        string $sessionUuid,
        GameSessionService $sessions,
        RankingService $ranking,
    ): JsonResponse {
        $validated = $request->validated();

        $session = $sessions->finish(new FinishGameSessionDTO(
            sessionUuid: $sessionUuid,
            score: (int) $validated['score'],
            completed: (bool) $validated['completed'],
            timeMs: (int) $validated['time_ms'],
            moves: (int) $validated['moves'],
            mistakes: isset($validated['mistakes']) ? (int) $validated['mistakes'] : null,
            accuracy: isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
            metadata: $validated['metadata'] ?? [],
        ));

        $leaderboard = $session->eventGame?->ranking_enabled
            ? PlayGameRankingResource::collection($ranking->leaderboardWithPositions($session->eventGame))
            : [];

        return $this->success([
            'session' => new PlayGameSessionResource($session),
            'status' => $session->status->value,
            'result' => $session->result_json,
            'authoritative_result' => $session->result_json,
            'analytics' => $sessions->analytics($session),
            'leaderboard' => $leaderboard,
            'last_plays' => $sessions->lastFinishedSessionsPayload($session->eventGame),
            'game_analytics' => $sessions->realtimePayload($session->eventGame)['analytics'],
        ]);
    }

    public function moves(
        StorePlayMovesRequest $request,
        string $sessionUuid,
        GameSessionService $sessions,
    ): JsonResponse {
        $validated = $request->validated();
        $before = $sessions->findByUuid($sessionUuid);
        $beforeCount = $before->moves->count();

        $session = $sessions->recordMoves(
            $sessionUuid,
            array_map(
                fn (array $move) => new RecordGameMoveDTO(
                    moveNumber: (int) $move['move_number'],
                    moveType: (string) $move['move_type'],
                    payload: $move['payload'] ?? [],
                    occurredAt: $move['occurred_at'] ?? null,
                ),
                $validated['moves'],
            ),
        );

        return $this->success([
            'session' => new PlayGameSessionResource($session),
            'accepted_moves' => max(0, $session->moves->count() - $beforeCount),
            'analytics' => $sessions->analytics($session),
        ]);
    }

    public function analytics(string $sessionUuid, GameSessionService $sessions): JsonResponse
    {
        $session = $sessions->findByUuid($sessionUuid);

        return $this->success([
            'session' => new PlayGameSessionResource($session),
            'analytics' => $sessions->analytics($session),
        ]);
    }

    private function resolvePublicGame(string $eventSlug, string $gameSlug): PlayEventGame
    {
        $event = Event::with('modules')
            ->where('slug', $eventSlug)
            ->firstOrFail();

        $settings = EventPlaySetting::query()->where('event_id', $event->id)->first();

        if (! $event->isModuleEnabled('play') || ! $settings?->is_enabled) {
            abort(404);
        }

        return PlayEventGame::query()
            ->where('event_id', $event->id)
            ->where('slug', $gameSlug)
            ->where('is_active', true)
            ->firstOrFail();
    }
}

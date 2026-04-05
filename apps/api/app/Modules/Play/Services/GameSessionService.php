<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\DTOs\FinishGameSessionDTO;
use App\Modules\Play\DTOs\HeartbeatGameSessionDTO;
use App\Modules\Play\DTOs\RecordGameMoveDTO;
use App\Modules\Play\DTOs\ResumeGameSessionDTO;
use App\Modules\Play\DTOs\StartGameSessionDTO;
use App\Modules\Play\Enums\PlayGameSessionStatus;
use App\Modules\Play\Events\PlayLeaderboardUpdated;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameMove;
use App\Modules\Play\Models\PlayGameRanking;
use App\Modules\Play\Models\PlayGameSession;
use App\Modules\Play\Support\RuntimeAssetProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GameSessionService
{
    private const ABANDON_AFTER_SECONDS = 120;
    private const RESUME_WINDOW_SECONDS = 180;

    public function __construct(
        private readonly GameAssetResolverService $assets,
        private readonly GameAnalyticsService $analytics,
        private readonly RankingService $ranking,
        private readonly ScoreCalculatorService $scores,
    ) {}

    public function start(PlayEventGame $game, StartGameSessionDTO $dto): PlayGameSession
    {
        $now = now();

        return PlayGameSession::query()->create([
            'event_game_id' => $game->id,
            'player_identifier' => $dto->playerIdentifier,
            'player_name' => $dto->playerName,
            'status' => PlayGameSessionStatus::Started,
            'resume_token' => Str::random(48),
            'started_at' => $now,
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addSeconds(self::RESUME_WINDOW_SECONDS),
            'result_json' => array_filter([
                'runtime_device' => RuntimeAssetProfile::fromDevice($dto->device)?->toArray(),
            ], fn ($value) => $value !== null && $value !== []),
        ]);
    }

    public function finish(FinishGameSessionDTO $dto): PlayGameSession
    {
        $session = $this->findByUuid($dto->sessionUuid, syncLifecycle: false);
        $existingResult = $session->result_json ?? [];
        $result = array_merge($existingResult, $this->scores->normalize($session, $dto));

        $session->forceFill([
            'status' => PlayGameSessionStatus::Finished,
            'finished_at' => now(),
            'last_activity_at' => now(),
            'result_json' => $result,
        ])->save();

        if ($session->eventGame?->ranking_enabled) {
            $this->ranking->updateFromSession($session);
            event(new PlayLeaderboardUpdated(
                $session->eventGame->uuid,
                $this->realtimePayload($session->eventGame),
            ));
        }

        return $session->fresh('eventGame');
    }

    public function heartbeat(HeartbeatGameSessionDTO $dto): PlayGameSession
    {
        $session = $this->findByUuid($dto->sessionUuid, syncLifecycle: false);

        if ($session->status === PlayGameSessionStatus::Finished) {
            return $session;
        }

        $lastActivityAt = $session->last_activity_at ?? $session->started_at;

        if ($lastActivityAt !== null && $lastActivityAt->copy()->addSeconds(self::ABANDON_AFTER_SECONDS)->lte(now())) {
            return $this->markAbandoned($session);
        }

        if ($this->isExpiredForResume($session)) {
            return $this->markAbandoned($session);
        }

        $status = $dto->state === 'hidden'
            ? PlayGameSessionStatus::Paused
            : PlayGameSessionStatus::Started;

        $session->forceFill([
            'status' => $status,
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds(self::RESUME_WINDOW_SECONDS),
            'result_json' => $this->mergeLifecycleMetadata($session, [
                'last_state' => $dto->state,
                'last_reason' => $dto->reason,
                'last_elapsed_ms' => $dto->elapsedMs,
                'last_heartbeat_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $session->fresh(['eventGame.gameType', 'moves']);
    }

    public function hasValidResumeToken(PlayGameSession $session, string $resumeToken): bool
    {
        return ! empty($session->resume_token) && hash_equals($session->resume_token, $resumeToken);
    }

    public function canResume(PlayGameSession $session): bool
    {
        if ($session->status === PlayGameSessionStatus::Finished) {
            return false;
        }

        return ! $this->isExpiredForResume($session);
    }

    public function resume(ResumeGameSessionDTO $dto): ?PlayGameSession
    {
        $session = $this->findByUuid($dto->sessionUuid, syncLifecycle: false);

        if (! $this->hasValidResumeToken($session, $dto->resumeToken) || ! $this->canResume($session)) {
            return null;
        }

        $session->forceFill([
            'status' => PlayGameSessionStatus::Started,
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds(self::RESUME_WINDOW_SECONDS),
            'result_json' => $this->mergeLifecycleMetadata($session, [
                'last_state' => 'visible',
                'last_reason' => 'resume',
                'last_resume_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $session->fresh(['eventGame.gameType', 'moves']);
    }

    /**
     * @param  array<int, RecordGameMoveDTO>  $moves
     */
    public function recordMoves(string $sessionUuid, array $moves): PlayGameSession
    {
        $session = $this->findByUuid($sessionUuid, syncLifecycle: false);

        if (! in_array($session->status, [PlayGameSessionStatus::Started, PlayGameSessionStatus::Paused], true)) {
            return $session->fresh(['eventGame', 'moves']);
        }

        foreach ($moves as $move) {
            PlayGameMove::query()->updateOrCreate(
                [
                    'game_session_id' => $session->id,
                    'move_number' => $move->moveNumber,
                ],
                [
                    'move_type' => $move->moveType,
                    'payload_json' => $move->payload,
                    'occurred_at' => $move->occurredAt ? Carbon::parse($move->occurredAt) : now(),
                    'created_at' => now(),
                ],
            );
        }

        $session->forceFill([
            'status' => PlayGameSessionStatus::Started,
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds(self::RESUME_WINDOW_SECONDS),
        ])->save();

        return $session->fresh(['eventGame.gameType', 'moves']);
    }

    public function bootPayload(PlayGameSession $session, bool $includeRestore = false): array
    {
        $session = $this->syncLifecycle($session->loadMissing('eventGame.gameType', 'moves'));
        $assetProfile = RuntimeAssetProfile::fromDevice(
            is_array($session->result_json['runtime_device'] ?? null)
                ? $session->result_json['runtime_device']
                : null,
        );

        $payload = [
            'sessionUuid' => $session->uuid,
            'eventGameId' => $session->event_game_id,
            'gameKey' => $session->eventGame->gameType?->key?->value ?? $session->eventGame->gameType?->key,
            'sessionSeed' => $session->uuid,
            'resumeToken' => $session->resume_token,
            'status' => $session->status?->value ?? $session->status,
            'startedAt' => $session->started_at?->toIso8601String(),
            'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
            'expiresAt' => $session->expires_at?->toIso8601String(),
            'authoritativeScoring' => true,
            'session' => [
                'uuid' => $session->uuid,
                'resumeToken' => $session->resume_token,
                'status' => $session->status?->value ?? $session->status,
                'startedAt' => $session->started_at?->toIso8601String(),
                'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
                'expiresAt' => $session->expires_at?->toIso8601String(),
                'authoritativeScoring' => true,
                'seed' => $session->uuid,
            ],
            'player' => [
                'identifier' => $session->player_identifier,
                'name' => $session->player_name,
            ],
            'settings' => $session->eventGame->settings_json ?? [],
            'assets' => $this->assets->resolve($session->eventGame, $assetProfile),
            'analytics' => $this->analytics->sessionAnalytics($session),
        ];

        if ($includeRestore) {
            $payload['restore'] = [
                'lastAcceptedMoveNumber' => (int) ($session->moves->max('move_number') ?? 0),
                'serverElapsedMs' => $session->started_at?->diffInMilliseconds(now()) ?? 0,
                'moves' => $this->movesPayload($session),
            ];
        }

        return $payload;
    }

    /**
     * @return Collection<int, PlayGameSession>
     */
    public function lastFinishedSessions(PlayEventGame $game, int $limit = 10): Collection
    {
        return PlayGameSession::query()
            ->where('event_game_id', $game->id)
            ->where('status', PlayGameSessionStatus::Finished)
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get();
    }

    public function analytics(PlayGameSession $session): array
    {
        return $this->analytics->sessionAnalytics($this->syncLifecycle($session));
    }

    public function realtimePayload(PlayEventGame $game): array
    {
        return [
            'game_uuid' => $game->uuid,
            'game_slug' => $game->slug,
            'leaderboard' => $this->leaderboardPayload($game),
            'last_plays' => $this->lastFinishedSessionsPayload($game),
            'analytics' => $this->analytics->gameAnalytics($game),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function findByUuid(string $sessionUuid, bool $syncLifecycle = true): PlayGameSession
    {
        $session = PlayGameSession::query()
            ->with(['eventGame.gameType', 'moves'])
            ->where('uuid', $sessionUuid)
            ->firstOrFail();

        return $syncLifecycle ? $this->syncLifecycle($session) : $session;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lastFinishedSessionsPayload(PlayEventGame $game, int $limit = 10): array
    {
        return $this->lastFinishedSessions($game, $limit)
            ->map(fn (PlayGameSession $session) => [
                'uuid' => $session->uuid,
                'event_game_id' => $session->event_game_id,
                'player_identifier' => $session->player_identifier,
                'player_name' => $session->player_name,
                'status' => $session->status?->value ?? $session->status,
                'started_at' => $session->started_at?->toIso8601String(),
                'finished_at' => $session->finished_at?->toIso8601String(),
                'result' => $session->result_json ?? [],
                'score' => $session->result_json['score'] ?? null,
                'time_ms' => $session->result_json['time_ms'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function leaderboardPayload(PlayEventGame $game, int $limit = 20): array
    {
        return $this->ranking->leaderboardWithPositions($game, $limit)
            ->map(fn (PlayGameRanking $entry) => [
                'position' => $entry->position,
                'player_identifier' => $entry->player_identifier,
                'player_name' => $entry->player_name,
                'best_score' => $entry->best_score,
                'best_time_ms' => $entry->best_time_ms,
                'best_moves' => $entry->best_moves,
                'total_sessions' => $entry->total_sessions,
                'total_wins' => $entry->total_wins,
                'last_played_at' => $entry->last_played_at?->toIso8601String(),
                'metrics' => $entry->metrics_json ?? [],
            ])
            ->values()
            ->all();
    }

    private function syncLifecycle(PlayGameSession $session): PlayGameSession
    {
        if ($session->status === PlayGameSessionStatus::Finished) {
            return $session;
        }

        $lastActivityAt = $session->last_activity_at ?? $session->started_at;

        if ($lastActivityAt !== null && $lastActivityAt->copy()->addSeconds(self::ABANDON_AFTER_SECONDS)->lte(now())) {
            return $this->markAbandoned($session);
        }

        return $session;
    }

    public function markAbandoned(PlayGameSession $session): PlayGameSession
    {
        if ($session->status === PlayGameSessionStatus::Finished || $session->status === PlayGameSessionStatus::Abandoned) {
            return $session->fresh(['eventGame.gameType', 'moves']) ?? $session;
        }

        $session->forceFill([
            'status' => PlayGameSessionStatus::Abandoned,
            'result_json' => $this->mergeLifecycleMetadata($session, [
                'last_state' => 'abandoned',
                'last_reason' => 'timeout',
                'abandoned_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $session->fresh(['eventGame.gameType', 'moves']);
    }

    private function isExpiredForResume(PlayGameSession $session): bool
    {
        $expiresAt = $session->expires_at ?? ($session->last_activity_at ?? $session->started_at)?->copy()->addSeconds(self::RESUME_WINDOW_SECONDS);

        return $expiresAt !== null && $expiresAt->lte(now());
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeLifecycleMetadata(PlayGameSession $session, array $lifecycle): array
    {
        $result = $session->result_json ?? [];
        $existingLifecycle = is_array($result['lifecycle'] ?? null) ? $result['lifecycle'] : [];
        $result['lifecycle'] = array_merge($existingLifecycle, array_filter($lifecycle, fn ($value) => $value !== null));

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function movesPayload(PlayGameSession $session): array
    {
        return $session->moves
            ->sortBy('move_number')
            ->values()
            ->map(fn (PlayGameMove $move) => [
                'moveNumber' => $move->move_number,
                'type' => $move->move_type,
                'payload' => $move->payload_json ?? [],
                'occurredAt' => $move->occurred_at?->toIso8601String(),
            ])
            ->all();
    }
}

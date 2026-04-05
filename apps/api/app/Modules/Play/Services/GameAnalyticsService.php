<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\Enums\PlayGameSessionStatus;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Models\PlayGameMove;
use App\Modules\Play\Models\PlayGameSession;
use Illuminate\Support\Collection;

class GameAnalyticsService
{
    /**
     * @param  Collection<int, PlayGameSession>  $sessions
     */
    public function summaryFromSessions(Collection $sessions): array
    {
        $sessions = $sessions->values();
        $finishedSessions = $sessions->filter(
            fn (PlayGameSession $session) => $session->status === PlayGameSessionStatus::Finished,
        )->values();
        $abandonedSessions = $sessions->filter(
            fn (PlayGameSession $session) => $session->status === PlayGameSessionStatus::Abandoned,
        )->values();

        $scores = $finishedSessions
            ->pluck('result_json')
            ->map(fn ($result) => is_array($result) ? ($result['score'] ?? null) : null)
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (int) $score)
            ->values();

        $times = $finishedSessions
            ->pluck('result_json')
            ->map(fn ($result) => is_array($result) ? ($result['time_ms'] ?? null) : null)
            ->filter(fn ($time) => $time !== null)
            ->map(fn ($time) => (int) $time)
            ->values();

        $moves = $finishedSessions
            ->pluck('result_json')
            ->map(fn ($result) => is_array($result) ? ($result['moves'] ?? null) : null)
            ->filter(fn ($count) => $count !== null)
            ->map(fn ($count) => (int) $count)
            ->values();

        $moveCount = $this->sumMovesForSessions($sessions);

        $lastFinishedAt = $finishedSessions
            ->sortByDesc('finished_at')
            ->first()?->finished_at;

        $totalSessions = $sessions->count();
        $finishedCount = $finishedSessions->count();

        return [
            'total_sessions' => $totalSessions,
            'finished_sessions' => $finishedCount,
            'abandoned_sessions' => $abandonedSessions->count(),
            'active_sessions' => $sessions->filter(
                fn (PlayGameSession $session) => $session->status === PlayGameSessionStatus::Started,
            )->count(),
            'completion_rate' => $totalSessions > 0 ? round(($finishedCount / $totalSessions) * 100, 2) : 0.0,
            'unique_players' => $sessions->pluck('player_identifier')->filter()->unique()->count(),
            'total_moves' => $moveCount,
            'average_score' => $scores->isNotEmpty() ? (int) round($scores->avg()) : null,
            'average_time_ms' => $times->isNotEmpty() ? (int) round($times->avg()) : null,
            'average_moves' => $moves->isNotEmpty() ? (int) round($moves->avg()) : null,
            'best_score' => $scores->max(),
            'last_finished_at' => $lastFinishedAt?->toIso8601String(),
        ];
    }

    public function sessionAnalytics(PlayGameSession $session): array
    {
        $session->loadMissing('moves');

        $moves = $session->moves
            ->sortBy('move_number')
            ->values();

        $moveBreakdown = $moves
            ->groupBy('move_type')
            ->map(fn (Collection $items) => $items->count())
            ->sortKeys()
            ->all();

        $firstMoveAt = $moves->first()?->occurred_at;
        $lastMoveAt = $moves->last()?->occurred_at;
        $sessionEndedAt = $session->finished_at ?? $lastMoveAt ?? now();

        return [
            'total_moves' => $moves->count(),
            'unique_move_types' => count($moveBreakdown),
            'move_type_breakdown' => $moveBreakdown,
            'last_move_number' => $moves->max('move_number'),
            'first_move_at' => $firstMoveAt?->toIso8601String(),
            'last_move_at' => $lastMoveAt?->toIso8601String(),
            'elapsed_ms' => $session->started_at?->diffInMilliseconds($sessionEndedAt),
            'activity_window_ms' => $firstMoveAt && $lastMoveAt
                ? $firstMoveAt->diffInMilliseconds($lastMoveAt)
                : 0,
            'completed' => (bool) ($session->result_json['completed'] ?? false),
            'score' => $session->result_json['score'] ?? null,
            'time_ms' => $session->result_json['time_ms'] ?? null,
            'moves_reported' => $session->result_json['moves'] ?? null,
            'mistakes' => $session->result_json['mistakes'] ?? null,
            'accuracy' => $session->result_json['accuracy'] ?? null,
        ];
    }

    public function gameAnalytics(PlayEventGame $game): array
    {
        $sessions = PlayGameSession::query()
            ->where('event_game_id', $game->id)
            ->get([
                'id',
                'player_identifier',
                'status',
                'started_at',
                'finished_at',
                'result_json',
            ]);

        return $this->summaryFromSessions($sessions);
    }

    /**
     * @param  Collection<int, PlayGameSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    public function timelineFromSessions(Collection $sessions): array
    {
        return $sessions
            ->groupBy(fn (PlayGameSession $session) => $session->started_at?->toDateString() ?? 'unknown')
            ->sortKeys()
            ->map(function (Collection $items, string $date) {
                $summary = $this->summaryFromSessions($items->values());

                return [
                    'date' => $date,
                    'sessions' => $summary['total_sessions'],
                    'finished_sessions' => $summary['finished_sessions'],
                    'abandoned_sessions' => $summary['abandoned_sessions'],
                    'unique_players' => $summary['unique_players'],
                    'total_moves' => $summary['total_moves'],
                    'average_score' => $summary['average_score'],
                    'best_score' => $summary['best_score'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PlayGameSession>  $sessions
     */
    private function sumMovesForSessions(Collection $sessions): int
    {
        if ($sessions->isEmpty()) {
            return 0;
        }

        $hasPreloadedCount = $sessions->first() !== null && isset($sessions->first()->moves_count);

        if ($hasPreloadedCount) {
            return (int) $sessions->sum(fn (PlayGameSession $session) => (int) ($session->moves_count ?? 0));
        }

        return (int) PlayGameMove::query()
            ->whereIn('game_session_id', $sessions->pluck('id'))
            ->count();
    }
}

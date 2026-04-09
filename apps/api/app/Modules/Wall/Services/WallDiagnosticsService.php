<?php

namespace App\Modules\Wall\Services;

use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallDiagnosticSummary;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WallDiagnosticsService
{
    public const OFFLINE_AFTER_SECONDS = 60;
    public const PRUNE_AFTER_HOURS = 24;

    public function recordHeartbeat(EventWallSetting $settings, array $payload): WallPlayerRuntimeStatus
    {
        $player = WallPlayerRuntimeStatus::query()->firstOrNew([
            'event_wall_setting_id' => $settings->id,
            'player_instance_id' => $payload['player_instance_id'],
        ]);

        $currentItemId = $payload['current_item_id'] ?? null;

        $player->fill([
            'runtime_status' => $payload['runtime_status'],
            'connection_status' => $payload['connection_status'],
            'current_item_id' => $currentItemId,
            'current_item_started_at' => $this->resolveCurrentItemStartedAt($player, $currentItemId),
            'current_sender_key' => $payload['current_sender_key'] ?? null,
            'ready_count' => (int) ($payload['ready_count'] ?? 0),
            'loading_count' => (int) ($payload['loading_count'] ?? 0),
            'error_count' => (int) ($payload['error_count'] ?? 0),
            'stale_count' => (int) ($payload['stale_count'] ?? 0),
            'cache_enabled' => (bool) ($payload['cache_enabled'] ?? false),
            'persistent_storage' => (string) ($payload['persistent_storage'] ?? 'none'),
            'cache_usage_bytes' => $payload['cache_usage_bytes'] ?? null,
            'cache_quota_bytes' => $payload['cache_quota_bytes'] ?? null,
            'cache_hit_count' => (int) ($payload['cache_hit_count'] ?? 0),
            'cache_miss_count' => (int) ($payload['cache_miss_count'] ?? 0),
            'cache_stale_fallback_count' => (int) ($payload['cache_stale_fallback_count'] ?? 0),
            'last_sync_at' => $payload['last_sync_at'] ?? null,
            'last_heartbeat_at' => now(),
            'last_fallback_reason' => $payload['last_fallback_reason'] ?? null,
        ]);

        $player->save();

        return $player->fresh();
    }

    public function diagnosticsPayload(EventWallSetting $settings): array
    {
        $summary = $settings->relationLoaded('diagnosticSummary')
            ? $settings->diagnosticSummary
            : $settings->diagnosticSummary()->first();

        if (! $summary) {
            $summary = $this->recalculateSummary($settings, broadcast: false);
        }

        $players = $this->playersQuery($settings)->get();

        return [
            'summary' => $this->summaryPayload($summary),
            'players' => $players->map(fn (WallPlayerRuntimeStatus $player) => $this->playerPayload($player))->all(),
            'updated_at' => $summary->updated_at?->toIso8601String(),
        ];
    }

    public function summaryPayloadForSettings(EventWallSetting $settings): array
    {
        $summary = $settings->relationLoaded('diagnosticSummary')
            ? $settings->diagnosticSummary
            : $settings->diagnosticSummary()->first();

        if (! $summary) {
            if ($settings->playerRuntimeStatuses()->exists()) {
                $summary = $this->recalculateSummary($settings, broadcast: false);

                return $this->summaryPayload($summary);
            }

            return $this->defaultSummaryPayload();
        }

        return $this->summaryPayload($summary);
    }

    public function recalculateSummary(EventWallSetting $settings, bool $broadcast = true): WallDiagnosticSummary
    {
        $this->pruneStaleSnapshots();

        $previous = $settings->relationLoaded('diagnosticSummary')
            ? $settings->diagnosticSummary
            : $settings->diagnosticSummary()->first();
        $players = $this->playersQuery($settings)->get();
        $summaryData = $this->buildSummaryData($players);

        $summary = WallDiagnosticSummary::query()->updateOrCreate(
            ['event_wall_setting_id' => $settings->id],
            array_merge($summaryData, ['refreshed_at' => now()]),
        );

        $settings->setRelation('diagnosticSummary', $summary);

        if ($broadcast && $this->hasMaterialSummaryChange($previous, $summary)) {
            event(new WallDiagnosticsUpdated(
                $settings->event_id,
                $this->summaryPayload($summary),
            ));
        }

        return $summary;
    }

    public function pruneStaleSnapshots(): int
    {
        return WallPlayerRuntimeStatus::query()
            ->where('last_heartbeat_at', '<', now()->subHours(self::PRUNE_AFTER_HOURS))
            ->delete();
    }

    public function playerPayload(WallPlayerRuntimeStatus $player): array
    {
        $healthStatus = $this->classifyPlayerHealth($player);

        return [
            'player_instance_id' => $player->player_instance_id,
            'health_status' => $healthStatus,
            'is_online' => $healthStatus !== 'offline',
            'runtime_status' => $player->runtime_status,
            'connection_status' => $player->connection_status,
            'current_item_id' => $player->current_item_id,
            'current_item_started_at' => $player->current_item_started_at?->toIso8601String(),
            'current_sender_key' => $player->current_sender_key,
            'ready_count' => (int) $player->ready_count,
            'loading_count' => (int) $player->loading_count,
            'error_count' => (int) $player->error_count,
            'stale_count' => (int) $player->stale_count,
            'cache_enabled' => (bool) $player->cache_enabled,
            'persistent_storage' => $player->persistent_storage ?: 'none',
            'cache_usage_bytes' => $player->cache_usage_bytes,
            'cache_quota_bytes' => $player->cache_quota_bytes,
            'cache_hit_count' => (int) $player->cache_hit_count,
            'cache_miss_count' => (int) $player->cache_miss_count,
            'cache_stale_fallback_count' => (int) $player->cache_stale_fallback_count,
            'cache_hit_rate' => $this->hitRate(
                (int) $player->cache_hit_count,
                (int) $player->cache_miss_count,
            ),
            'last_sync_at' => $player->last_sync_at?->toIso8601String(),
            'last_seen_at' => $player->last_heartbeat_at?->toIso8601String(),
            'last_fallback_reason' => $player->last_fallback_reason,
            'updated_at' => $player->updated_at?->toIso8601String(),
        ];
    }

    public function summaryPayload(WallDiagnosticSummary $summary): array
    {
        return [
            'health_status' => $summary->health_status,
            'total_players' => (int) $summary->total_players,
            'online_players' => (int) $summary->online_players,
            'offline_players' => (int) $summary->offline_players,
            'degraded_players' => (int) $summary->degraded_players,
            'ready_count' => (int) $summary->ready_count,
            'loading_count' => (int) $summary->loading_count,
            'error_count' => (int) $summary->error_count,
            'stale_count' => (int) $summary->stale_count,
            'cache_enabled_players' => (int) $summary->cache_enabled_players,
            'persistent_storage_players' => (int) $summary->persistent_storage_players,
            'cache_hit_rate_avg' => (int) $summary->cache_hit_rate_avg,
            'cache_usage_bytes_max' => $summary->cache_usage_bytes_max,
            'cache_quota_bytes_max' => $summary->cache_quota_bytes_max,
            'cache_stale_fallback_count' => (int) $summary->cache_stale_fallback_count,
            'last_seen_at' => $summary->last_seen_at?->toIso8601String(),
            'updated_at' => $summary->updated_at?->toIso8601String(),
        ];
    }

    public function defaultSummaryPayload(): array
    {
        return [
            'health_status' => 'idle',
            'total_players' => 0,
            'online_players' => 0,
            'offline_players' => 0,
            'degraded_players' => 0,
            'ready_count' => 0,
            'loading_count' => 0,
            'error_count' => 0,
            'stale_count' => 0,
            'cache_enabled_players' => 0,
            'persistent_storage_players' => 0,
            'cache_hit_rate_avg' => 0,
            'cache_usage_bytes_max' => null,
            'cache_quota_bytes_max' => null,
            'cache_stale_fallback_count' => 0,
            'last_seen_at' => null,
            'updated_at' => null,
        ];
    }

    private function playersQuery(EventWallSetting $settings): Builder
    {
        return WallPlayerRuntimeStatus::query()
            ->where('event_wall_setting_id', $settings->id)
            ->where(function (Builder $query): void {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '>=', now()->subHours(self::PRUNE_AFTER_HOURS));
            })
            ->orderByDesc('last_heartbeat_at')
            ->orderBy('player_instance_id');
    }

    private function buildSummaryData(Collection $players): array
    {
        $onlinePlayers = 0;
        $offlinePlayers = 0;
        $degradedPlayers = 0;
        $readyCount = 0;
        $loadingCount = 0;
        $errorCount = 0;
        $staleCount = 0;
        $cacheEnabledPlayers = 0;
        $persistentStoragePlayers = 0;
        $cacheHitCount = 0;
        $cacheMissCount = 0;
        $cacheUsageBytesMax = null;
        $cacheQuotaBytesMax = null;
        $cacheStaleFallbackCount = 0;
        $lastSeenAt = null;

        foreach ($players as $player) {
            $healthStatus = $this->classifyPlayerHealth($player);

            if ($healthStatus === 'offline') {
                $offlinePlayers++;
            } else {
                $onlinePlayers++;

                if ($healthStatus === 'degraded') {
                    $degradedPlayers++;
                }
            }

            $readyCount += (int) $player->ready_count;
            $loadingCount += (int) $player->loading_count;
            $errorCount += (int) $player->error_count;
            $staleCount += (int) $player->stale_count;
            $cacheEnabledPlayers += $player->cache_enabled ? 1 : 0;
            $persistentStoragePlayers += $this->hasPersistentStorage($player) ? 1 : 0;
            $cacheHitCount += (int) $player->cache_hit_count;
            $cacheMissCount += (int) $player->cache_miss_count;
            $cacheStaleFallbackCount += (int) $player->cache_stale_fallback_count;

            if ($player->cache_usage_bytes !== null) {
                $cacheUsageBytesMax = max((int) ($cacheUsageBytesMax ?? 0), (int) $player->cache_usage_bytes);
            }

            if ($player->cache_quota_bytes !== null) {
                $cacheQuotaBytesMax = max((int) ($cacheQuotaBytesMax ?? 0), (int) $player->cache_quota_bytes);
            }

            if ($player->last_heartbeat_at && ($lastSeenAt === null || $player->last_heartbeat_at->gt($lastSeenAt))) {
                $lastSeenAt = $player->last_heartbeat_at;
            }
        }

        return [
            'health_status' => $this->classifySummaryHealth(
                totalPlayers: $players->count(),
                onlinePlayers: $onlinePlayers,
                offlinePlayers: $offlinePlayers,
                degradedPlayers: $degradedPlayers,
            ),
            'total_players' => $players->count(),
            'online_players' => $onlinePlayers,
            'offline_players' => $offlinePlayers,
            'degraded_players' => $degradedPlayers,
            'ready_count' => $readyCount,
            'loading_count' => $loadingCount,
            'error_count' => $errorCount,
            'stale_count' => $staleCount,
            'cache_enabled_players' => $cacheEnabledPlayers,
            'persistent_storage_players' => $persistentStoragePlayers,
            'cache_hit_rate_avg' => $this->hitRate($cacheHitCount, $cacheMissCount),
            'cache_usage_bytes_max' => $cacheUsageBytesMax,
            'cache_quota_bytes_max' => $cacheQuotaBytesMax,
            'cache_stale_fallback_count' => $cacheStaleFallbackCount,
            'last_seen_at' => $lastSeenAt,
        ];
    }

    private function classifyPlayerHealth(WallPlayerRuntimeStatus $player): string
    {
        if (! $player->last_heartbeat_at || $player->last_heartbeat_at->lt(now()->subSeconds(self::OFFLINE_AFTER_SECONDS))) {
            return 'offline';
        }

        if (
            in_array($player->connection_status, ['disconnected', 'error'], true)
            || $player->runtime_status === 'error'
            || (int) $player->error_count > 0
            || (int) $player->stale_count > 0
        ) {
            return 'degraded';
        }

        return 'healthy';
    }

    private function classifySummaryHealth(
        int $totalPlayers,
        int $onlinePlayers,
        int $offlinePlayers,
        int $degradedPlayers,
    ): string {
        if ($totalPlayers === 0) {
            return 'idle';
        }

        if ($onlinePlayers === 0) {
            return 'offline';
        }

        if ($offlinePlayers > 0 || $degradedPlayers > 0) {
            return 'degraded';
        }

        return 'healthy';
    }

    private function hasPersistentStorage(WallPlayerRuntimeStatus $player): bool
    {
        return ! in_array($player->persistent_storage, ['none', 'unknown', 'unavailable', ''], true);
    }

    private function resolveCurrentItemStartedAt(WallPlayerRuntimeStatus $player, ?string $currentItemId): ?\Illuminate\Support\Carbon
    {
        if (! $currentItemId) {
            return null;
        }

        if (! $player->exists || $player->current_item_id !== $currentItemId) {
            return now();
        }

        return $player->current_item_started_at ?? now();
    }

    private function hasMaterialSummaryChange(
        ?WallDiagnosticSummary $previous,
        WallDiagnosticSummary $current,
    ): bool {
        if (! $previous) {
            return true;
        }

        return $this->summaryComparable($previous) !== $this->summaryComparable($current);
    }

    private function summaryComparable(WallDiagnosticSummary $summary): array
    {
        return [
            'health_status' => $summary->health_status,
            'total_players' => (int) $summary->total_players,
            'online_players' => (int) $summary->online_players,
            'offline_players' => (int) $summary->offline_players,
            'degraded_players' => (int) $summary->degraded_players,
            'ready_count' => (int) $summary->ready_count,
            'loading_count' => (int) $summary->loading_count,
            'error_count' => (int) $summary->error_count,
            'stale_count' => (int) $summary->stale_count,
            'cache_enabled_players' => (int) $summary->cache_enabled_players,
            'persistent_storage_players' => (int) $summary->persistent_storage_players,
            'cache_hit_rate_avg' => (int) $summary->cache_hit_rate_avg,
            'cache_usage_bytes_max' => $summary->cache_usage_bytes_max,
            'cache_quota_bytes_max' => $summary->cache_quota_bytes_max,
            'cache_stale_fallback_count' => (int) $summary->cache_stale_fallback_count,
            'last_seen_at' => $summary->last_seen_at?->toIso8601String(),
        ];
    }

    private function hitRate(int $hits, int $misses): int
    {
        $total = $hits + $misses;

        if ($total <= 0) {
            return 0;
        }

        return (int) round(($hits / $total) * 100);
    }
}

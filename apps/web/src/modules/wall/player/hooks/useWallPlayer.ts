import { useCallback, useEffect, useRef, useState } from 'react';

import { clearWallAssetCaches, getWallCacheDiagnostics } from '../engine/cache';
import { resolveOperationalTransitionEffect } from '../engine/motion';
import { resolveWallRuntimeTransitionMode } from '../engine/transition-scheduler';
import { resolveRenderableLayout } from '../engine/layoutStrategy';
import { getWallBoot, sendWallHeartbeat, WallUnavailableError } from '../api';
import {
  clearWallHeartbeatMeta,
  getOrCreateWallPlayerInstanceId,
  readWallHeartbeatMeta,
  resolveWallPersistentStorage,
  writeWallHeartbeatMeta,
} from '../heartbeat-storage';
import { resolveWallRuntimeProfile } from '../runtime-profile';
import {
  resolveWallCacheEnabled,
  resolveWallPerformanceTier,
} from '../runtime-capabilities';
import { getWallLayoutDefinition } from '../themes/registry';
import type {
  WallBoardRuntimeTelemetry,
  WallConnectionStatus,
  WallPlayerCommandPayload,
  WallRuntimeItem,
  WallSettings,
  WallTransition,
  WallTransitionFallbackReason,
  WallTransitionMode,
} from '../types';
import { useWallEngine } from './useWallEngine';
import { useWallRealtime } from './useWallRealtime';

const RESYNC_INTERVAL_CONNECTED = 120_000;
const RESYNC_INTERVAL_DEGRADED = 20_000;
const HEARTBEAT_INTERVAL_MS = 20_000;
const DEFAULT_BOARD_RUNTIME_TELEMETRY: WallBoardRuntimeTelemetry = {
  boardPieceCount: 0,
  boardBurstCount: 0,
  boardBudgetDowngradeCount: 0,
  decodeBacklogCount: 0,
  boardResetCount: 0,
  boardBudgetDowngradeReason: null,
};

function countAssetsByStatus(items: WallRuntimeItem[]) {
  return items.reduce((counts, item) => {
    if (item.assetStatus === 'ready') counts.ready += 1;
    if (item.assetStatus === 'stale') counts.stale += 1;
    if (item.assetStatus === 'loading') counts.loading += 1;
    if (item.assetStatus === 'error') counts.error += 1;
    return counts;
  }, {
    ready: 0,
    stale: 0,
    loading: 0,
    error: 0,
  });
}

interface TransitionRuntimeTelemetry {
  activeTransitionEffect: WallTransition | null;
  transitionMode: WallTransitionMode;
  fallbackReason: WallTransitionFallbackReason | null;
}

function resolveTransitionRuntimeTelemetry(input: {
  settings?: WallSettings | null;
  currentItem?: WallRuntimeItem | null;
  activeTransitionEffect?: WallTransition | null;
  runtimeProfile: ReturnType<typeof resolveWallRuntimeProfile>;
}): TransitionRuntimeTelemetry {
  const performanceTier = resolveWallPerformanceTier({
    prefersReducedMotion: input.runtimeProfile.prefers_reduced_motion ?? false,
    deviceMemoryGb: input.runtimeProfile.device_memory_gb ?? null,
    hardwareConcurrency: input.runtimeProfile.hardware_concurrency ?? null,
  });
  const settings = input.settings;
  const currentItem = input.currentItem;

  if (!settings || !currentItem) {
    return {
      activeTransitionEffect: null,
      transitionMode: 'fixed',
      fallbackReason: null,
    };
  }

  const resolvedLayout = resolveRenderableLayout(
    settings.layout,
    currentItem,
    settings.video_multi_layout_policy ?? 'disallow',
  );

  if (getWallLayoutDefinition(resolvedLayout).kind !== 'single') {
    return {
      activeTransitionEffect: null,
      transitionMode: 'fixed',
      fallbackReason: null,
    };
  }

  const transitionMode = resolveWallRuntimeTransitionMode(settings);
  const operational = resolveOperationalTransitionEffect(
    input.activeTransitionEffect ?? settings.transition_effect,
    performanceTier === 'performance',
    performanceTier,
    input.runtimeProfile.prefers_reduced_motion === true,
  );

  return {
    activeTransitionEffect: operational.effect,
    transitionMode,
    fallbackReason: operational.fallbackReason,
  };
}

export function useWallPlayer(code: string) {
  const engine = useWallEngine(code);
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(() => readWallHeartbeatMeta(code)?.lastSyncAt ?? null);
  const [syncVersion, setSyncVersion] = useState(0);
  const [boardRuntimeTelemetry, setBoardRuntimeTelemetryState] = useState<WallBoardRuntimeTelemetry>(
    DEFAULT_BOARD_RUNTIME_TELEMETRY,
  );

  const engineRef = useRef(engine);
  useEffect(() => {
    engineRef.current = engine;
  });

  const playerInstanceIdRef = useRef(getOrCreateWallPlayerInstanceId(code));
  const lastSyncAtRef = useRef<string | null>(lastSyncAt);
  const boardRuntimeTelemetryRef = useRef<WallBoardRuntimeTelemetry>(DEFAULT_BOARD_RUNTIME_TELEMETRY);
  const transitionRandomPickCountRef = useRef(0);
  const transitionFallbackCountRef = useRef(0);
  const lastRandomTransitionSignatureRef = useRef<string | null>(null);
  const lastTransitionFallbackSignatureRef = useRef<string | null>(null);

  useEffect(() => {
    playerInstanceIdRef.current = getOrCreateWallPlayerInstanceId(code);
    const stored = readWallHeartbeatMeta(code);
    setLastSyncAt(stored?.lastSyncAt ?? null);
    transitionRandomPickCountRef.current = 0;
    transitionFallbackCountRef.current = 0;
    lastRandomTransitionSignatureRef.current = null;
    lastTransitionFallbackSignatureRef.current = null;
  }, [code]);

  useEffect(() => {
    lastSyncAtRef.current = lastSyncAt;
  }, [lastSyncAt]);

  useEffect(() => {
    boardRuntimeTelemetryRef.current = boardRuntimeTelemetry;
  }, [boardRuntimeTelemetry]);

  const setBoardRuntimeTelemetry = useCallback((nextTelemetry: WallBoardRuntimeTelemetry) => {
    setBoardRuntimeTelemetryState((current) => (
      current.boardPieceCount === nextTelemetry.boardPieceCount
      && current.boardBurstCount === nextTelemetry.boardBurstCount
      && current.boardBudgetDowngradeCount === nextTelemetry.boardBudgetDowngradeCount
      && current.decodeBacklogCount === nextTelemetry.decodeBacklogCount
      && current.boardResetCount === nextTelemetry.boardResetCount
      && current.boardBudgetDowngradeReason === nextTelemetry.boardBudgetDowngradeReason
        ? current
        : nextTelemetry
    ));
  }, []);

  const sync = useCallback(async () => {
    setIsSyncing(true);

    try {
      const snapshot = await getWallBoot(code);
      engineRef.current.applySnapshot(snapshot);

      const syncedAt = new Date().toISOString();
      setLastSyncAt(syncedAt);
      writeWallHeartbeatMeta(code, {
        playerInstanceId: playerInstanceIdRef.current,
        lastSyncAt: syncedAt,
      });
      setSyncVersion((current) => current + 1);
    } catch (error) {
      if (error instanceof WallUnavailableError) {
        engineRef.current.markExpired(error.message);
      } else {
        engineRef.current.markSyncError('Nao foi possivel carregar o telao.');
      }
    } finally {
      setIsSyncing(false);
    }
  }, [code]);

  useEffect(() => {
    void sync();
  }, [sync]);

  const sendHeartbeatSafe = useCallback(async () => {
    const snapshot = engineRef.current;
    const counts = countAssetsByStatus(snapshot.state.items);
    const cacheDiagnostics = await getWallCacheDiagnostics();
    const runtimeProfile = resolveWallRuntimeProfile();
    const transitionTelemetry = resolveTransitionRuntimeTelemetry({
      settings: snapshot.state.settings,
      currentItem: snapshot.currentItem,
      activeTransitionEffect: snapshot.state.activeTransitionEffect,
      runtimeProfile,
    });
    const randomTransitionSignature = (
      transitionTelemetry.transitionMode === 'random'
      && snapshot.currentItem?.id
      && transitionTelemetry.activeTransitionEffect
    )
      ? `${snapshot.currentItem.id}:${snapshot.state.transitionAdvanceCount}:${transitionTelemetry.activeTransitionEffect}`
      : null;

    if (
      randomTransitionSignature
      && lastRandomTransitionSignatureRef.current !== randomTransitionSignature
    ) {
      transitionRandomPickCountRef.current += 1;
      lastRandomTransitionSignatureRef.current = randomTransitionSignature;
    }

    const transitionFallbackSignature = (
      transitionTelemetry.fallbackReason
      && snapshot.currentItem?.id
      && transitionTelemetry.activeTransitionEffect
    )
      ? `${snapshot.currentItem.id}:${snapshot.state.transitionAdvanceCount}:${transitionTelemetry.activeTransitionEffect}:${transitionTelemetry.fallbackReason}`
      : null;

    if (
      transitionFallbackSignature
      && lastTransitionFallbackSignatureRef.current !== transitionFallbackSignature
    ) {
      transitionFallbackCountRef.current += 1;
      lastTransitionFallbackSignatureRef.current = transitionFallbackSignature;
    }

    try {
      await sendWallHeartbeat(code, {
        player_instance_id: playerInstanceIdRef.current,
        runtime_status: snapshot.state.status,
        connection_status: connectionStatusRef.current,
        current_item_id: snapshot.currentItem?.id ?? snapshot.state.currentItemId ?? null,
        current_item_started_at: snapshot.state.currentItemStartedAt ?? null,
        active_transition_effect: transitionTelemetry.activeTransitionEffect,
        transition_mode: transitionTelemetry.transitionMode,
        transition_random_pick_count: transitionRandomPickCountRef.current,
        transition_fallback_count: transitionFallbackCountRef.current,
        transition_last_fallback_reason: transitionTelemetry.fallbackReason,
        current_sender_key: snapshot.currentItem?.senderKey ?? null,
        current_media_type: snapshot.currentItem?.type ?? null,
        current_video_phase: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.phase : null,
        current_video_exit_reason: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.exitReason : snapshot.state.videoPlayback.lastExitReason ?? null,
        current_video_failure_reason: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.failureReason : snapshot.state.videoPlayback.lastFailureReason ?? null,
        current_video_position_seconds: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.currentTime : null,
        current_video_duration_seconds: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.durationSeconds : null,
        current_video_ready_state: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.readyState : null,
        current_video_stall_count: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.stallCount : 0,
        current_video_poster_visible: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.posterVisible : null,
        current_video_first_frame_ready: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.firstFrameReady : null,
        current_video_playback_ready: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.playbackReady : null,
        current_video_playing_confirmed: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.playingConfirmed : null,
        current_video_startup_degraded: snapshot.state.videoPlayback.itemId ? snapshot.state.videoPlayback.startupDegraded : null,
        ready_count: counts.ready,
        loading_count: counts.loading,
        error_count: counts.error,
        stale_count: counts.stale,
        cache_enabled: cacheDiagnostics.cacheEnabled || resolveWallCacheEnabled(),
        persistent_storage: resolveWallPersistentStorage(),
        cache_usage_bytes: cacheDiagnostics.usageBytes,
        cache_quota_bytes: cacheDiagnostics.quotaBytes,
        cache_hit_count: cacheDiagnostics.hitCount,
        cache_miss_count: cacheDiagnostics.missCount,
        cache_stale_fallback_count: cacheDiagnostics.staleFallbackCount,
        board_piece_count: boardRuntimeTelemetryRef.current.boardPieceCount,
        board_burst_count: boardRuntimeTelemetryRef.current.boardBurstCount,
        board_budget_downgrade_count: boardRuntimeTelemetryRef.current.boardBudgetDowngradeCount,
        decode_backlog_count: boardRuntimeTelemetryRef.current.decodeBacklogCount,
        board_reset_count: boardRuntimeTelemetryRef.current.boardResetCount,
        board_budget_downgrade_reason: boardRuntimeTelemetryRef.current.boardBudgetDowngradeReason,
        ...runtimeProfile,
        last_sync_at: lastSyncAtRef.current,
        last_fallback_reason: snapshot.errorMessage ?? null,
      });

      writeWallHeartbeatMeta(code, {
        playerInstanceId: playerInstanceIdRef.current,
        lastSyncAt: lastSyncAtRef.current,
        lastHeartbeatAt: new Date().toISOString(),
      });
    } catch (error) {
      if (error instanceof WallUnavailableError) {
        snapshot.markExpired(error.message);
      }
    }
  }, [code]);

  const handlePlayerCommand = useCallback(async (payload: WallPlayerCommandPayload) => {
    const snapshot = engineRef.current;
    const itemUrls = snapshot.state.items
      .map((item) => item.url)
      .filter((value): value is string => Boolean(value));

    if (payload.command === 'clear-cache') {
      await clearWallAssetCaches();
      snapshot.resetAssetStatuses();
      void sendHeartbeatSafe();
      return;
    }

    if (payload.command === 'revalidate-assets') {
      await clearWallAssetCaches({ urls: itemUrls, resetMetrics: false });
      snapshot.resetAssetStatuses();
      await sync();
      return;
    }

    if (payload.command === 'reinitialize-engine') {
      await clearWallAssetCaches({ resetMetrics: false });
      clearWallHeartbeatMeta(code);
      snapshot.resetRuntime();
      await sync();
    }
  }, [code, sendHeartbeatSafe, sync]);

  const { connectionStatus } = useWallRealtime({
    code,
    onNewMedia: engine.handleNewMedia,
    onMediaUpdated: engine.handleMediaUpdated,
    onMediaDeleted: engine.handleMediaDeleted,
    onSettingsUpdated: engine.applySettings,
    onStatusChanged: engine.handleStatusChanged,
    onExpired: (payload) => engine.markExpired(payload.reason || 'O telao foi encerrado.'),
    onPlayerCommand: (payload) => {
      void handlePlayerCommand(payload);
    },
    onAdsUpdated: (payload) => {
      engine.handleAdsUpdated(payload.ads);
    },
  });

  const connectionStatusRef = useRef<WallConnectionStatus>(connectionStatus);
  useEffect(() => {
    connectionStatusRef.current = connectionStatus;
  }, [connectionStatus]);

  useEffect(() => {
    if (syncVersion === 0) {
      return;
    }

    void sendHeartbeatSafe();
  }, [sendHeartbeatSafe, syncVersion]);

  const prevConnectionRef = useRef<WallConnectionStatus>(connectionStatus);
  useEffect(() => {
    const previous = prevConnectionRef.current;
    if (connectionStatus === 'connected' && previous !== 'connected' && previous !== 'idle') {
      void sync();
    }
    prevConnectionRef.current = connectionStatus;
  }, [connectionStatus, sync]);

  useEffect(() => {
    const interval = connectionStatus === 'connected'
      ? RESYNC_INTERVAL_CONNECTED
      : RESYNC_INTERVAL_DEGRADED;

    const timer = window.setInterval(() => {
      void sync();
    }, interval);

    return () => window.clearInterval(timer);
  }, [connectionStatus, sync]);

  useEffect(() => {
    const timer = window.setInterval(() => {
      void sendHeartbeatSafe();
    }, HEARTBEAT_INTERVAL_MS);

    return () => window.clearInterval(timer);
  }, [sendHeartbeatSafe]);

  useEffect(() => {
    if (!engine.state.currentItemId || !engine.state.currentItemStartedAt) {
      return;
    }

    void sendHeartbeatSafe();
  }, [engine.state.currentItemId, engine.state.currentItemStartedAt, sendHeartbeatSafe]);

  useEffect(() => {
    if (!engine.state.videoPlayback.itemId && !engine.state.videoPlayback.lastExitReason && !engine.state.videoPlayback.lastFailureReason) {
      return;
    }

    void sendHeartbeatSafe();
  }, [
    engine.state.videoPlayback.currentTime,
    engine.state.videoPlayback.durationSeconds,
    engine.state.videoPlayback.exitReason,
    engine.state.videoPlayback.failureReason,
    engine.state.videoPlayback.firstFrameReady,
    engine.state.videoPlayback.itemId,
    engine.state.videoPlayback.lastExitReason,
    engine.state.videoPlayback.lastFailureReason,
    engine.state.videoPlayback.phase,
    engine.state.videoPlayback.playbackReady,
    engine.state.videoPlayback.playingConfirmed,
    engine.state.videoPlayback.posterVisible,
    engine.state.videoPlayback.readyState,
    engine.state.videoPlayback.stallCount,
    engine.state.videoPlayback.startupDegraded,
    sendHeartbeatSafe,
  ]);

  useEffect(() => {
    const handleVisibilityChange = () => {
      void sendHeartbeatSafe();
    };

    const handlePageHide = () => {
      void sendHeartbeatSafe();
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('pagehide', handlePageHide);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('pagehide', handlePageHide);
    };
  }, [sendHeartbeatSafe]);

  useEffect(() => {
    void sendHeartbeatSafe();
  }, [engine.state.status, sendHeartbeatSafe]);

  useEffect(() => {
    if (syncVersion === 0) {
      return;
    }

    void sendHeartbeatSafe();
  }, [boardRuntimeTelemetry, sendHeartbeatSafe, syncVersion]);

  useEffect(() => {
    void sendHeartbeatSafe();
  }, [connectionStatus, sendHeartbeatSafe]);

  useEffect(() => {
    if (!engine.errorMessage) {
      return;
    }

    void sendHeartbeatSafe();
  }, [engine.errorMessage, sendHeartbeatSafe]);

  return {
    state: engine.state,
    currentItem: engine.currentItem,
    errorMessage: engine.errorMessage,
    isSyncing,
    connectionStatus,
    lastSyncAt,
    handleAdFinished: engine.handleAdFinished,
    handleVideoStarting: engine.handleVideoStarting,
    handleVideoFirstFrame: engine.handleVideoFirstFrame,
    handleVideoPlaybackReady: engine.handleVideoPlaybackReady,
    handleVideoPlaying: engine.handleVideoPlaying,
    handleVideoProgress: engine.handleVideoProgress,
    handleVideoWaiting: engine.handleVideoWaiting,
    handleVideoStalled: engine.handleVideoStalled,
    handleVideoEnded: engine.handleVideoEnded,
    handleVideoFailure: engine.handleVideoFailure,
    videoRuntimeConfig: engine.videoRuntimeConfig,
    resync: sync,
    setBoardRuntimeTelemetry,
  };
}

export default useWallPlayer;

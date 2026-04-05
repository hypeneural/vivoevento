import { useCallback, useEffect, useRef, useState } from 'react';

import { clearWallAssetCaches, getWallCacheDiagnostics } from '../engine/cache';
import { getWallBoot, sendWallHeartbeat, WallUnavailableError } from '../api';
import {
  clearWallHeartbeatMeta,
  getOrCreateWallPlayerInstanceId,
  readWallHeartbeatMeta,
  resolveWallPersistentStorage,
  writeWallHeartbeatMeta,
} from '../heartbeat-storage';
import { resolveWallCacheEnabled } from '../runtime-capabilities';
import type { WallConnectionStatus, WallPlayerCommandPayload, WallRuntimeItem } from '../types';
import { useWallEngine } from './useWallEngine';
import { useWallRealtime } from './useWallRealtime';

const RESYNC_INTERVAL_CONNECTED = 120_000;
const RESYNC_INTERVAL_DEGRADED = 20_000;
const HEARTBEAT_INTERVAL_MS = 20_000;

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

export function useWallPlayer(code: string) {
  const engine = useWallEngine(code);
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(() => readWallHeartbeatMeta(code)?.lastSyncAt ?? null);
  const [syncVersion, setSyncVersion] = useState(0);

  const engineRef = useRef(engine);
  useEffect(() => {
    engineRef.current = engine;
  });

  const playerInstanceIdRef = useRef(getOrCreateWallPlayerInstanceId(code));
  const lastSyncAtRef = useRef<string | null>(lastSyncAt);

  useEffect(() => {
    playerInstanceIdRef.current = getOrCreateWallPlayerInstanceId(code);
    const stored = readWallHeartbeatMeta(code);
    setLastSyncAt(stored?.lastSyncAt ?? null);
  }, [code]);

  useEffect(() => {
    lastSyncAtRef.current = lastSyncAt;
  }, [lastSyncAt]);

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

    try {
      await sendWallHeartbeat(code, {
        player_instance_id: playerInstanceIdRef.current,
        runtime_status: snapshot.state.status,
        connection_status: connectionStatusRef.current,
        current_item_id: snapshot.currentItem?.id ?? snapshot.state.currentItemId ?? null,
        current_sender_key: snapshot.currentItem?.senderKey ?? null,
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
    resync: sync,
  };
}

export default useWallPlayer;

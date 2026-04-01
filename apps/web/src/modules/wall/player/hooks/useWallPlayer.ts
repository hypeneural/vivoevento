/**
 * useWallPlayer — Top-level composition hook.
 *
 * Wires together: Engine (state) + Boot (HTTP) + Realtime (WebSocket).
 * This is the only hook the page component needs to import.
 *
 * Uses refs for engine callbacks to avoid re-subscription loops.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { getWallBoot, WallUnavailableError } from '../api';
import { useWallEngine } from './useWallEngine';
import { useWallRealtime } from './useWallRealtime';
import type { WallConnectionStatus } from '../types';

const RESYNC_INTERVAL_CONNECTED = 120_000;   // 2 min
const RESYNC_INTERVAL_DEGRADED = 20_000;      // 20s

export function useWallPlayer(code: string) {
  const engine = useWallEngine(code);
  const [isSyncing, setIsSyncing] = useState(false);

  // Keep latest engine methods in a ref to avoid re-creating sync callback
  const engineRef = useRef(engine);
  useEffect(() => { engineRef.current = engine; });

  // ─── Boot / Resync ──────────────────────────────────────

  const sync = useCallback(async () => {
    setIsSyncing(true);
    try {
      const snapshot = await getWallBoot(code);
      engineRef.current.applySnapshot(snapshot);
    } catch (error) {
      if (error instanceof WallUnavailableError) {
        engineRef.current.markExpired(error.message);
      } else {
        engineRef.current.markSyncError('Não foi possível carregar o telão.');
      }
    } finally {
      setIsSyncing(false);
    }
  }, [code]);

  // Initial boot
  useEffect(() => {
    void sync();
  }, [sync]);

  // ─── Realtime ───────────────────────────────────────────

  const { connectionStatus } = useWallRealtime({
    code,
    onNewMedia: engine.handleNewMedia,
    onMediaUpdated: engine.handleMediaUpdated,
    onMediaDeleted: engine.handleMediaDeleted,
    onSettingsUpdated: engine.applySettings,
    onStatusChanged: engine.handleStatusChanged,
    onExpired: (payload) => engine.markExpired(payload.reason || 'O telão foi encerrado.'),
  });

  // ─── Resync on reconnect ────────────────────────────────

  const prevConnectionRef = useRef<WallConnectionStatus>(connectionStatus);
  useEffect(() => {
    const prev = prevConnectionRef.current;
    if (connectionStatus === 'connected' && prev !== 'connected' && prev !== 'idle') {
      void sync();
    }
    prevConnectionRef.current = connectionStatus;
  }, [connectionStatus, sync]);

  // ─── Periodic resync ────────────────────────────────────

  useEffect(() => {
    const interval = connectionStatus === 'connected'
      ? RESYNC_INTERVAL_CONNECTED
      : RESYNC_INTERVAL_DEGRADED;

    const timer = window.setInterval(() => { void sync(); }, interval);
    return () => window.clearInterval(timer);
  }, [connectionStatus, sync]);

  return {
    state: engine.state,
    currentItem: engine.currentItem,
    errorMessage: engine.errorMessage,
    isSyncing,
    connectionStatus,
    resync: sync,
  };
}

export default useWallPlayer;

import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import type {
  WallBootData,
  WallMediaDeletedPayload,
  WallMediaItem,
  WallPlayerStatus,
  WallRuntimeItem,
  WallSettings,
  WallStatusChangedPayload,
} from '../types';
import { primeWallAsset } from '../engine/cache';
import { resolveNextPreloadItem, preloadNextItem } from '../engine/preload';
import { createEmptyState, wallReducer } from '../engine/reducer';
import {
  clearWallRuntimeStorage,
  readWallRuntimeStorage,
  readWallRuntimeStorageAsync,
  writeWallRuntimeStorage,
  hydrateWallRuntimeItems,
  hydrateWallSenderStats,
} from '../engine/storage';

function uniqueItems(items: Array<WallRuntimeItem | null | undefined>): WallRuntimeItem[] {
  const seen = new Set<string>();
  const result: WallRuntimeItem[] = [];

  for (const item of items) {
    if (!item || seen.has(item.id)) {
      continue;
    }

    seen.add(item.id);
    result.push(item);
  }

  return result;
}

export function useWallEngine(code: string) {
  const [state, dispatch] = useReducer(wallReducer, createEmptyState(code));
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const stateRef = useRef(state);
  const persistedRef = useRef(readWallRuntimeStorage(code));

  useEffect(() => {
    stateRef.current = state;
  }, [state]);

  useEffect(() => {
    persistedRef.current = readWallRuntimeStorage(code);
    dispatch({ type: 'reset', code });
    setErrorMessage(null);
  }, [code]);

  useEffect(() => {
    let active = true;

    void readWallRuntimeStorageAsync(code).then((persistedState) => {
      if (!active || !persistedState) {
        return;
      }

      persistedRef.current = persistedState;
    });

    return () => {
      active = false;
    };
  }, [code]);

  useEffect(() => {
    writeWallRuntimeStorage(code, state);
  }, [code, state]);

  // Track when the last advance happened (for drift compensation)
  const lastAdvanceAtRef = useRef<number>(Date.now());

  useEffect(() => {
    if (state.status !== 'playing' || !state.settings || state.items.length === 0) {
      return;
    }

    const intervalMs = state.settings.interval_ms;

    // Phase 0.3: Compensate for timer throttling in background tabs.
    // When the tab regains visibility, check if enough time has elapsed
    // for an immediate advance.
    const handleVisibility = () => {
      if (document.visibilityState !== 'visible') return;

      const elapsed = Date.now() - lastAdvanceAtRef.current;
      if (elapsed > intervalMs) {
        lastAdvanceAtRef.current = Date.now();
        dispatch({ type: 'advance' });
      }
    };

    document.addEventListener('visibilitychange', handleVisibility);

    const timeout = window.setTimeout(() => {
      lastAdvanceAtRef.current = Date.now();
      dispatch({ type: 'advance' });
    }, intervalMs);

    return () => {
      window.clearTimeout(timeout);
      document.removeEventListener('visibilitychange', handleVisibility);
    };
  }, [state.currentItemId, state.items.length, state.settings, state.status]);

  const currentItem = useMemo(() => {
    if (!state.currentItemId) {
      return state.items[state.currentIndex] ?? null;
    }

    return state.items.find((item) => item.id === state.currentItemId) ?? null;
  }, [state.currentIndex, state.currentItemId, state.items]);

  const itemsToPrime = useMemo(() => {
    const topQueueItems = state.items
      .filter((item) => item.assetStatus === 'idle')
      .slice(0, 4);

    return uniqueItems([
      currentItem,
      ...topQueueItems,
    ]);
  }, [currentItem, state.items]);

  useEffect(() => {
    if (itemsToPrime.length === 0) {
      return;
    }

    itemsToPrime.forEach((item) => {
      void primeWallAsset(item, (result) => {
        dispatch({
          type: 'media-asset-status',
          payload: {
            id: item.id,
            assetStatus: result.status,
            width: result.width ?? null,
            height: result.height ?? null,
            orientation: result.orientation ?? null,
            errorMessage: result.errorMessage ?? null,
          },
        });
      });
    });
  }, [itemsToPrime]);

  // Phase 0.1: Aggressively preload the predicted NEXT item (img.decode / video preload=auto)
  useEffect(() => {
    if (state.status !== 'playing' || !state.currentItemId || !state.settings) {
      return;
    }

    const nextItem = resolveNextPreloadItem(
      state.items,
      state.currentItemId,
      state.settings,
      state.senderStats,
    );

    if (nextItem && nextItem.url) {
      void preloadNextItem(nextItem);
    }
  }, [state.currentItemId, state.status, state.settings, state.items, state.senderStats]);


  const applySnapshot = useCallback((snapshot: WallBootData) => {
    const persistedState = persistedRef.current ?? readWallRuntimeStorage(code);
    const runtimeItems = hydrateWallRuntimeItems(snapshot.files, persistedState);
    const senderStats = Object.keys(stateRef.current.senderStats).length > 0
      ? stateRef.current.senderStats
      : hydrateWallSenderStats(persistedState);
    const preferredCurrentItemId =
      stateRef.current.currentItemId
      ?? persistedState?.currentItemId
      ?? null;

    dispatch({
      type: 'apply-snapshot',
      snapshot,
      items: runtimeItems,
      senderStats,
      preferredCurrentItemId,
      fallbackStatus: 'playing' satisfies WallPlayerStatus,
    });

    setErrorMessage(null);
  }, [code]);

  const applySettings = useCallback((settings: WallSettings) => {
    dispatch({ type: 'apply-settings', settings });
  }, []);

  const handleStatusChanged = useCallback((payload: WallStatusChangedPayload) => {
    dispatch({ type: 'status-changed', payload });
  }, []);

  const handleNewMedia = useCallback((media: WallMediaItem) => {
    dispatch({ type: 'new-media', media });
  }, []);

  const handleMediaUpdated = useCallback((media: Partial<WallMediaItem> & { id: string }) => {
    dispatch({ type: 'media-updated', media });
  }, []);

  const handleMediaDeleted = useCallback((payload: WallMediaDeletedPayload) => {
    dispatch({ type: 'media-deleted', id: payload.id });
  }, []);

  const markExpired = useCallback((message?: string | null) => {
    dispatch({ type: 'mark-expired' });
    setErrorMessage(message || 'O telao foi encerrado.');
  }, []);

  const markSyncError = useCallback((message: string) => {
    dispatch({ type: 'sync-error' });
    setErrorMessage(message);
  }, []);

  const resetAssetStatuses = useCallback((ids?: string[]) => {
    dispatch({ type: 'reset-assets', ids });
  }, []);

  const resetRuntime = useCallback(() => {
    clearWallRuntimeStorage(code);
    persistedRef.current = null;
    dispatch({ type: 'reset', code });
    setErrorMessage(null);
  }, [code]);

  return {
    state,
    currentItem,
    errorMessage,
    applySnapshot,
    applySettings,
    handleStatusChanged,
    handleNewMedia,
    handleMediaUpdated,
    handleMediaDeleted,
    markExpired,
    markSyncError,
    resetAssetStatuses,
    resetRuntime,
  };
}

export default useWallEngine;

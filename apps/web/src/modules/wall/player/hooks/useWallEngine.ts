import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import type {
  WallAdItem,
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
import {
  DEFAULT_EVENT_VIDEO_RESUME_MODE,
  EVENT_VIDEO_STARTUP_DEADLINE_MS,
  EVENT_VIDEO_STALL_BUDGET_MS,
  resolveEventVideoCapMs,
  resolveEventVideoResumeMode,
} from '../engine/autoplay';
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

  const currentItem = useMemo(() => {
    if (!state.currentItemId) {
      return state.items[state.currentIndex] ?? null;
    }

    return state.items.find((item) => item.id === state.currentItemId) ?? null;
  }, [state.currentIndex, state.currentItemId, state.items]);

  // Track when the last advance happened (for drift compensation)
  const lastAdvanceAtRef = useRef<number>(Date.now());
  const videoPolicyConfig = useMemo(() => ({
    intervalMs: state.settings?.interval_ms ?? null,
    playbackMode: state.settings?.video_playback_mode ?? null,
    maxSeconds: state.settings?.video_max_seconds ?? null,
    resumeMode: state.settings?.video_resume_mode ?? null,
  }), [
    state.settings?.interval_ms,
    state.settings?.video_max_seconds,
    state.settings?.video_playback_mode,
    state.settings?.video_resume_mode,
  ]);

  useEffect(() => {
    if (
      state.status !== 'playing'
      || state.currentAd
      || !state.settings
      || state.items.length === 0
      || currentItem?.type === 'video'
    ) {
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
  }, [currentItem?.type, state.currentAd, state.currentItemId, state.items.length, state.settings, state.status]);

  useEffect(() => {
    if (
      state.status !== 'playing'
      || state.currentAd
      || !currentItem
      || currentItem.type !== 'video'
      || state.videoPlayback.itemId !== currentItem.id
      || !state.videoPlayback.playingConfirmed
    ) {
      return;
    }

    const capMs = resolveEventVideoCapMs(
      state.videoPlayback.durationSeconds ?? currentItem.duration_seconds ?? null,
      videoPolicyConfig,
    );

    if (capMs == null) {
      return;
    }

    const startedAt = state.videoPlayback.playbackStartedAt
      ? Date.parse(state.videoPlayback.playbackStartedAt)
      : NaN;
    const elapsed = Number.isFinite(startedAt)
      ? Math.max(0, Date.now() - startedAt)
      : 0;
    const remainingMs = Math.max(0, capMs - elapsed);

    if (remainingMs === 0) {
      dispatch({
        type: 'video-cap-reached',
        payload: {
          itemId: currentItem.id,
          currentTime: state.videoPlayback.currentTime,
          durationSeconds: state.videoPlayback.durationSeconds ?? currentItem.duration_seconds ?? null,
          readyState: state.videoPlayback.readyState,
        },
      });
      return;
    }

    const timeout = window.setTimeout(() => {
      dispatch({
        type: 'video-cap-reached',
        payload: {
          itemId: currentItem.id,
          currentTime: state.videoPlayback.currentTime,
          durationSeconds: state.videoPlayback.durationSeconds ?? currentItem.duration_seconds ?? null,
          readyState: state.videoPlayback.readyState,
        },
      });
    }, remainingMs);

    return () => {
      window.clearTimeout(timeout);
    };
  }, [
    currentItem,
    state.currentAd,
    state.status,
    state.videoPlayback.currentTime,
    state.videoPlayback.durationSeconds,
    state.videoPlayback.itemId,
    state.videoPlayback.playbackStartedAt,
    state.videoPlayback.playingConfirmed,
    state.videoPlayback.readyState,
    videoPolicyConfig,
  ]);

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
    const preferredCurrentItemStartedAt =
      stateRef.current.currentItemStartedAt
      ?? persistedState?.currentItemStartedAt
      ?? null;

    dispatch({
      type: 'apply-snapshot',
      snapshot,
      items: runtimeItems,
      senderStats,
      persistedVideoPlayback: persistedState?.videoPlayback ?? null,
      preferredCurrentItemId,
      preferredCurrentItemStartedAt,
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

  const handleAdsUpdated = useCallback((ads: WallAdItem[]) => {
    dispatch({ type: 'ads-updated', ads });
  }, []);

  const handleAdFinished = useCallback(() => {
    lastAdvanceAtRef.current = Date.now();
    dispatch({ type: 'ad-finished' });
  }, []);

  const handleVideoStarting = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-starting', payload });
  }, []);

  const handleVideoFirstFrame = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-first-frame', payload });
  }, []);

  const handleVideoPlaybackReady = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-playback-ready', payload });
  }, []);

  const handleVideoPlaying = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-playing', payload });
  }, []);

  const handleVideoProgress = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-progress', payload });
  }, []);

  const handleVideoWaiting = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-waiting', payload });
  }, []);

  const handleVideoStalled = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    dispatch({ type: 'video-stalled', payload });
  }, []);

  const handleVideoEnded = useCallback((payload: {
    itemId: string;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    lastAdvanceAtRef.current = Date.now();
    dispatch({ type: 'video-ended', payload });
  }, []);

  const handleVideoFailure = useCallback((payload: {
    itemId: string;
    exitReason: 'play_rejected' | 'stalled_timeout' | 'startup_timeout' | 'poster_then_skip' | 'startup_waiting_timeout' | 'startup_play_rejected';
    failureReason?: 'network_error' | 'unsupported_format' | 'autoplay_blocked' | 'decode_degraded' | 'src_missing' | 'variant_missing' | null;
    currentTime?: number;
    durationSeconds?: number | null;
    readyState?: number;
  }) => {
    lastAdvanceAtRef.current = Date.now();
    dispatch({ type: 'video-failure', payload });
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
    handleAdsUpdated,
    handleAdFinished,
    handleVideoStarting,
    handleVideoFirstFrame,
    handleVideoPlaybackReady,
    handleVideoPlaying,
    handleVideoProgress,
    handleVideoWaiting,
    handleVideoStalled,
    handleVideoEnded,
    handleVideoFailure,
    markExpired,
    markSyncError,
    resetAssetStatuses,
    resetRuntime,
    videoRuntimeConfig: {
      startupDeadlineMs: EVENT_VIDEO_STARTUP_DEADLINE_MS,
      stallBudgetMs: EVENT_VIDEO_STALL_BUDGET_MS,
      resumeMode: resolveEventVideoResumeMode(videoPolicyConfig) ?? DEFAULT_EVENT_VIDEO_RESUME_MODE,
    },
  };
}

export default useWallEngine;

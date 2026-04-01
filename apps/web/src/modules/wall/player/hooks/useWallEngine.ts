/**
 * useWallEngine — Core state machine for the wall player.
 *
 * Manages the slide queue, current index, auto-advance timer,
 * and maps API/WebSocket events into state updates.
 */

import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import type {
  WallBootData,
  WallMediaItem,
  WallMediaDeletedPayload,
  WallPlayerState,
  WallPlayerStatus,
  WallRuntimeItem,
  WallSettings,
  WallStatusChangedPayload,
  MediaOrientation,
} from '../types';

// ─── Helpers ───────────────────────────────────────────────

function detectOrientation(width?: number | null, height?: number | null): MediaOrientation | null {
  if (!width || !height) return null;
  const ratio = width / height;
  if (ratio > 1.15) return 'horizontal';
  if (ratio < 0.85) return 'vertical';
  return 'squareish';
}

function mediaToRuntime(media: WallMediaItem): WallRuntimeItem {
  return { ...media, orientation: null, width: null, height: null };
}

function createEmptyState(code: string): WallPlayerState {
  return {
    code,
    status: 'booting',
    event: null,
    settings: null,
    items: [],
    currentIndex: 0,
  };
}

// ─── Reducer ───────────────────────────────────────────────

type Action =
  | { type: 'reset'; code: string }
  | { type: 'apply-snapshot'; snapshot: WallBootData; fallbackStatus: WallPlayerStatus }
  | { type: 'apply-settings'; settings: WallSettings }
  | { type: 'status-changed'; payload: WallStatusChangedPayload }
  | { type: 'new-media'; media: WallMediaItem }
  | { type: 'media-updated'; media: Partial<WallMediaItem> & { id: string } }
  | { type: 'media-deleted'; id: string }
  | { type: 'media-dimensions'; id: string; width: number; height: number }
  | { type: 'advance' }
  | { type: 'mark-expired' }
  | { type: 'sync-error' };

function wallReducer(state: WallPlayerState, action: Action): WallPlayerState {
  switch (action.type) {
    case 'reset':
      return createEmptyState(action.code);

    case 'apply-snapshot': {
      const { snapshot, fallbackStatus } = action;
      const controlStatus = snapshot.event.status;
      const playerStatus: WallPlayerStatus =
        controlStatus === 'live' ? 'playing'
        : controlStatus === 'paused' ? 'paused'
        : controlStatus === 'stopped' ? 'stopped'
        : controlStatus === 'expired' ? 'expired'
        : fallbackStatus;

      const items = snapshot.files.map(mediaToRuntime);

      return {
        ...state,
        status: items.length > 0 ? playerStatus : (playerStatus === 'playing' ? 'idle' : playerStatus),
        event: snapshot.event,
        settings: snapshot.settings,
        items,
        currentIndex: 0,
      };
    }

    case 'apply-settings':
      return { ...state, settings: action.settings };

    case 'status-changed': {
      const { status } = action.payload;
      const playerStatus: WallPlayerStatus =
        status === 'live' ? (state.items.length > 0 ? 'playing' : 'idle')
        : status === 'paused' ? 'paused'
        : status === 'stopped' ? 'stopped'
        : status === 'expired' ? 'expired'
        : state.status;

      return { ...state, status: playerStatus };
    }

    case 'new-media': {
      const existing = state.items.findIndex(i => i.id === action.media.id);
      let items: WallRuntimeItem[];

      if (existing >= 0) {
        items = [...state.items];
        items[existing] = { ...items[existing], ...action.media };
      } else {
        // Prepend (newest first)
        items = [mediaToRuntime(action.media), ...state.items];

        // Trim to queue_limit
        const limit = state.settings?.queue_limit ?? 100;
        if (items.length > limit) {
          items = items.slice(0, limit);
        }
      }

      const newStatus = state.status === 'idle' && items.length > 0 ? 'playing' : state.status;

      return { ...state, items, status: newStatus };
    }

    case 'media-updated': {
      const idx = state.items.findIndex(i => i.id === action.media.id);
      if (idx < 0) return state;

      const items = [...state.items];
      items[idx] = { ...items[idx], ...action.media };
      return { ...state, items };
    }

    case 'media-deleted': {
      const items = state.items.filter(i => i.id !== action.id);
      let { currentIndex } = state;
      if (currentIndex >= items.length) currentIndex = 0;

      return {
        ...state,
        items,
        currentIndex,
        status: items.length === 0 && state.status === 'playing' ? 'idle' : state.status,
      };
    }

    case 'media-dimensions': {
      const idx = state.items.findIndex(i => i.id === action.id);
      if (idx < 0) return state;

      const items = [...state.items];
      items[idx] = {
        ...items[idx],
        width: action.width,
        height: action.height,
        orientation: detectOrientation(action.width, action.height),
      };
      return { ...state, items };
    }

    case 'advance': {
      if (state.items.length === 0) return state;
      return { ...state, currentIndex: (state.currentIndex + 1) % state.items.length };
    }

    case 'mark-expired':
      return { ...state, status: 'expired' };

    case 'sync-error':
      return state.items.length > 0 ? state : { ...state, status: 'error' };

    default:
      return state;
  }
}

// ─── Hook ──────────────────────────────────────────────────

export function useWallEngine(code: string) {
  const [state, dispatch] = useReducer(wallReducer, createEmptyState(code));
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const stateRef = useRef(state);

  useEffect(() => { stateRef.current = state; }, [state]);

  useEffect(() => {
    dispatch({ type: 'reset', code });
    setErrorMessage(null);
  }, [code]);

  // Auto-advance timer
  useEffect(() => {
    if (state.status !== 'playing' || !state.settings || state.items.length === 0) {
      return;
    }

    const timeout = window.setTimeout(() => {
      dispatch({ type: 'advance' });
    }, state.settings.interval_ms);

    return () => window.clearTimeout(timeout);
  }, [state.currentIndex, state.items.length, state.settings, state.status]);

  // Preload image dimensions
  const preloadDimensions = useCallback((item: WallRuntimeItem) => {
    if (item.type !== 'image' || item.width) return;

    const img = new Image();
    img.onload = () => {
      dispatch({
        type: 'media-dimensions',
        id: item.id,
        width: img.naturalWidth,
        height: img.naturalHeight,
      });
    };
    img.src = item.url;
  }, []);

  const applySnapshot = useCallback((snapshot: WallBootData) => {
    dispatch({ type: 'apply-snapshot', snapshot, fallbackStatus: 'playing' });
    setErrorMessage(null);

    // Preload first few items
    snapshot.files.slice(0, 5).forEach(f => {
      preloadDimensions(mediaToRuntime(f));
    });
  }, [preloadDimensions]);

  const applySettings = useCallback((settings: WallSettings) => {
    dispatch({ type: 'apply-settings', settings });
  }, []);

  const handleStatusChanged = useCallback((payload: WallStatusChangedPayload) => {
    dispatch({ type: 'status-changed', payload });
  }, []);

  const handleNewMedia = useCallback((media: WallMediaItem) => {
    dispatch({ type: 'new-media', media });
    preloadDimensions(mediaToRuntime(media));
  }, [preloadDimensions]);

  const handleMediaUpdated = useCallback((media: Partial<WallMediaItem> & { id: string }) => {
    dispatch({ type: 'media-updated', media });
  }, []);

  const handleMediaDeleted = useCallback((payload: WallMediaDeletedPayload) => {
    dispatch({ type: 'media-deleted', id: payload.id });
  }, []);

  const markExpired = useCallback((message?: string | null) => {
    dispatch({ type: 'mark-expired' });
    setErrorMessage(message || 'O telão foi encerrado.');
  }, []);

  const markSyncError = useCallback((message: string) => {
    dispatch({ type: 'sync-error' });
    setErrorMessage(message);
  }, []);

  const currentItem = useMemo(() => {
    if (state.items.length === 0) return null;
    return state.items[state.currentIndex % state.items.length] ?? null;
  }, [state.items, state.currentIndex]);

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
  };
}

export default useWallEngine;

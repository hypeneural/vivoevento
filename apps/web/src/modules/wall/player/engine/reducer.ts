import type {
  WallAdItem,
  WallBootData,
  WallMediaItem,
  WallPlayerState,
  WallPlayerStatus,
  WallSenderRuntimeStats,
  WallRuntimeItem,
  WallSettings,
  WallStatusChangedPayload,
} from '../types';
import {
  createAdSchedulerState,
  markAdPlayed,
  markPhotoAdvanced,
  selectNextAd,
  shouldPlayAd,
  updateAdSchedulerMode,
} from './adScheduler';
import {
  findWallCurrentIndex,
  isWallItemRenderable,
  mediaToRuntimeItem,
  pickNextWallItemId,
  resolveWallSelectionPolicy,
  resolveInitialWallItemId,
} from './selectors';

type MediaStatusPayload = {
  id: string;
  assetStatus: WallRuntimeItem['assetStatus'];
  width?: number | null;
  height?: number | null;
  orientation?: WallRuntimeItem['orientation'];
  errorMessage?: string | null;
};

export type WallEngineAction =
  | { type: 'reset'; code: string }
  | { type: 'reset-assets'; ids?: string[] }
  | {
      type: 'apply-snapshot';
      snapshot: WallBootData;
      items: WallRuntimeItem[];
      senderStats?: Record<string, WallSenderRuntimeStats>;
      fallbackStatus: WallPlayerStatus;
      preferredCurrentItemId?: string | null;
    }
  | { type: 'apply-settings'; settings: WallSettings }
  | { type: 'status-changed'; payload: WallStatusChangedPayload }
  | { type: 'new-media'; media: WallMediaItem }
  | { type: 'media-updated'; media: Partial<WallMediaItem> & { id: string } }
  | { type: 'media-deleted'; id: string }
  | { type: 'ads-updated'; ads: WallAdItem[] }
  | { type: 'media-asset-status'; payload: MediaStatusPayload }
  | { type: 'advance' }
  | { type: 'ad-finished' }
  | { type: 'mark-expired' }
  | { type: 'sync-error' };

function countQueueCandidates(items: WallRuntimeItem[]): number {
  return items.filter((item) => Boolean(item.url) && item.assetStatus !== 'error').length;
}

function clampInteger(value: number | undefined, fallback: number, min: number, max: number): number {
  if (!Number.isFinite(value)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.trunc(value as number)));
}

function sortWallAds(ads: WallAdItem[]): WallAdItem[] {
  return [...ads].sort((left, right) => {
    if (left.position !== right.position) {
      return left.position - right.position;
    }

    return left.id - right.id;
  });
}

function resolveAdFrequency(settings?: WallSettings | null): number {
  if ((settings?.ad_mode ?? 'disabled') === 'by_minutes') {
    return clampInteger(settings?.ad_interval_minutes, 3, 1, 60);
  }

  return clampInteger(settings?.ad_frequency, 5, 1, 100);
}

function syncAdSchedulerWithSettings(state: WallPlayerState, settings?: WallSettings | null) {
  return updateAdSchedulerMode(
    state.adScheduler,
    settings?.ad_mode ?? 'disabled',
    resolveAdFrequency(settings),
  );
}

function mapWallStatusToPlayerStatus(
  status: WallStatusChangedPayload['status'],
  fallbackStatus: WallPlayerStatus,
  items: WallRuntimeItem[],
): WallPlayerStatus {
  if (status === 'live') {
    return countQueueCandidates(items) > 0 ? 'playing' : 'idle';
  }

  if (status === 'paused') {
    return 'paused';
  }

  if (status === 'stopped' || status === 'disabled') {
    return 'stopped';
  }

  if (status === 'expired') {
    return 'expired';
  }

  return fallbackStatus;
}

function markPlayback(
  items: WallRuntimeItem[],
  senderStats: Record<string, WallSenderRuntimeStats>,
  itemId: string,
): {
  items: WallRuntimeItem[];
  senderStats: Record<string, WallSenderRuntimeStats>;
} {
  const playedAt = new Date().toISOString();
  const playedItem = items.find((item) => item.id === itemId);

  if (!playedItem) {
    return { items, senderStats };
  }

  const nextItems = items.map((item) => (
    item.id === itemId
      ? {
          ...item,
          playedAt,
          playCount: item.playCount + 1,
        }
      : item
  ));

  const existingSenderStats = senderStats[playedItem.senderKey] ?? {
    lastPlayedAt: null,
    recentPlayTimestamps: [],
    totalPlayCount: 0,
  };

  const nextSenderStats: Record<string, WallSenderRuntimeStats> = {
    ...senderStats,
    [playedItem.senderKey]: {
      lastPlayedAt: playedAt,
      recentPlayTimestamps: [
        ...existingSenderStats.recentPlayTimestamps,
        playedAt,
      ].slice(-20),
      totalPlayCount: existingSenderStats.totalPlayCount + 1,
    },
  };

  return {
    items: nextItems,
    senderStats: nextSenderStats,
  };
}

function resolveStateWithCurrentItem(
  state: WallPlayerState,
  items: WallRuntimeItem[],
  currentItemId?: string | null,
): WallPlayerState {
  const resolvedCurrentItemId = currentItemId && items.some((item) => item.id === currentItemId)
    ? currentItemId
    : null;

  return {
    ...state,
    items,
    senderStats: state.senderStats,
    currentItemId: resolvedCurrentItemId,
    currentIndex: findWallCurrentIndex(items, resolvedCurrentItemId),
  };
}

export function createEmptyState(code: string): WallPlayerState {
  return {
    code,
    status: 'booting',
    event: null,
    settings: null,
    items: [],
    ads: [],
    currentAd: null,
    adBaseItemId: null,
    adScheduler: createAdSchedulerState(),
    senderStats: {},
    currentIndex: 0,
    currentItemId: null,
  };
}

export function wallReducer(state: WallPlayerState, action: WallEngineAction): WallPlayerState {
  switch (action.type) {
    case 'reset':
      return createEmptyState(action.code);

    case 'reset-assets': {
      const ids = action.ids ? new Set(action.ids) : null;

      return {
        ...state,
        items: state.items.map((item) => {
          if (ids && !ids.has(item.id)) {
            return item;
          }

          if (!item.url) {
            return item;
          }

          return {
            ...item,
            assetStatus: 'idle',
            width: null,
            height: null,
            orientation: null,
            lastError: null,
          };
        }),
      };
    }

    case 'apply-snapshot': {
      const playerStatus = mapWallStatusToPlayerStatus(
        action.snapshot.event.status,
        action.fallbackStatus,
        action.items,
      );

      const preferredCurrentItemId = action.preferredCurrentItemId ?? state.currentItemId ?? null;
      const shouldMarkInitialItem = !state.currentItemId && !action.preferredCurrentItemId;
      const baselineSenderStats = action.senderStats ?? state.senderStats;
      const selectionPolicy = resolveWallSelectionPolicy(action.snapshot.settings);
      const resolvedCurrentItemId = resolveInitialWallItemId(
        action.items,
        preferredCurrentItemId,
        selectionPolicy,
        baselineSenderStats,
      );
      const playbackState = shouldMarkInitialItem && resolvedCurrentItemId
        ? markPlayback(action.items, baselineSenderStats, resolvedCurrentItemId)
        : {
            items: action.items,
            senderStats: baselineSenderStats,
          };
      const resolvedStatus = resolvedCurrentItemId
        ? playerStatus
        : (playerStatus === 'playing' ? 'idle' : playerStatus);
      const ads = sortWallAds(action.snapshot.ads ?? []);
      const currentAd = state.currentAd
        ? ads.find((ad) => ad.id === state.currentAd?.id) ?? null
        : null;

      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          status: resolvedStatus,
          event: action.snapshot.event,
          settings: action.snapshot.settings,
          ads,
          currentAd,
          adBaseItemId: currentAd ? (state.adBaseItemId ?? state.currentItemId ?? null) : null,
          adScheduler: updateAdSchedulerMode(
            state.adScheduler,
            action.snapshot.settings.ad_mode ?? 'disabled',
            resolveAdFrequency(action.snapshot.settings),
          ),
        },
        playbackState.items,
        resolvedCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'apply-settings':
      return {
        ...state,
        settings: action.settings,
        currentAd: action.settings.ad_mode === 'disabled' ? null : state.currentAd,
        adBaseItemId: action.settings.ad_mode === 'disabled'
          ? null
          : (state.currentAd ? (state.adBaseItemId ?? state.currentItemId ?? null) : state.adBaseItemId ?? null),
        adScheduler: updateAdSchedulerMode(
          state.adScheduler,
          action.settings.ad_mode ?? 'disabled',
          resolveAdFrequency(action.settings),
        ),
      };

    case 'status-changed': {
      const nextStatus = mapWallStatusToPlayerStatus(action.payload.status, state.status, state.items);
      return {
        ...state,
        status: nextStatus,
        currentAd: nextStatus === 'playing' ? state.currentAd : null,
        adBaseItemId: nextStatus === 'playing' ? state.adBaseItemId ?? null : null,
      };
    }

    case 'new-media': {
      const existingIndex = state.items.findIndex((item) => item.id === action.media.id);
      const existingItem = existingIndex >= 0 ? state.items[existingIndex] : null;
      const nextItem = mediaToRuntimeItem(action.media, existingItem);
      let items = existingIndex >= 0
        ? state.items.map((item, index) => (index === existingIndex ? nextItem : item))
        : [nextItem, ...state.items];

      const limit = state.settings?.queue_limit ?? 100;
      if (items.length > limit) {
        items = items.slice(0, limit);
      }

      const needsCurrentItem = !state.currentItemId && state.status !== 'stopped' && state.status !== 'expired';
      const selectionPolicy = resolveWallSelectionPolicy(state.settings);
      const nextCurrentItemId = needsCurrentItem
        ? resolveInitialWallItemId(items, nextItem.id, selectionPolicy, state.senderStats)
        : state.currentItemId;
      const playbackState = needsCurrentItem && nextCurrentItemId
        ? markPlayback(items, state.senderStats, nextCurrentItemId)
        : {
            items,
            senderStats: state.senderStats,
          };
      const nextStatus = state.status === 'idle' && countQueueCandidates(playbackState.items) > 0
        ? 'playing'
        : state.status;

      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          status: nextStatus,
        },
        playbackState.items,
        nextCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'media-updated': {
      const existingIndex = state.items.findIndex((item) => item.id === action.media.id);
      const existingItem = existingIndex >= 0 ? state.items[existingIndex] : null;
      const mergedMedia = {
        ...(existingItem ?? {}),
        ...action.media,
      } as WallMediaItem;

      const nextItem = mediaToRuntimeItem(mergedMedia, existingItem);
      const urlChanged = existingItem && existingItem.url !== nextItem.url;
      const normalizedItem = urlChanged
        ? {
            ...nextItem,
            assetStatus: 'idle' as const,
            width: null,
            height: null,
            orientation: null,
            lastError: null,
          }
        : nextItem;

      const items = existingIndex >= 0
        ? state.items.map((item, index) => (index === existingIndex ? normalizedItem : item))
        : [normalizedItem, ...state.items];

      const nextCurrentItemId = state.currentItemId && items.some((item) => item.id === state.currentItemId)
        ? state.currentItemId
        : resolveInitialWallItemId(
            items,
            normalizedItem.id,
            resolveWallSelectionPolicy(state.settings),
            state.senderStats,
          );

      return resolveStateWithCurrentItem(state, items, nextCurrentItemId);
    }

    case 'media-deleted': {
      const items = state.items.filter((item) => item.id !== action.id);
      const removedCurrentItem = state.currentItemId === action.id;
      const selectionPolicy = resolveWallSelectionPolicy(state.settings);
      const nextCurrentItemId = removedCurrentItem
        ? pickNextWallItemId(items, state.currentItemId, selectionPolicy, state.senderStats)
        : state.currentItemId;
      const playbackState = removedCurrentItem && nextCurrentItemId
        ? markPlayback(items, state.senderStats, nextCurrentItemId)
        : {
            items,
            senderStats: state.senderStats,
          };
      const nextStatus = countQueueCandidates(playbackState.items) === 0 && state.status === 'playing'
        ? 'idle'
        : state.status;

      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          status: nextStatus,
        },
        playbackState.items,
        nextCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'ads-updated': {
      const ads = sortWallAds(action.ads);
      const currentAd = state.currentAd
        ? ads.find((ad) => ad.id === state.currentAd?.id) ?? null
        : null;

      return {
        ...state,
        ads,
        currentAd,
        adBaseItemId: currentAd ? (state.adBaseItemId ?? state.currentItemId ?? null) : null,
      };
    }

    case 'media-asset-status': {
      const itemIndex = state.items.findIndex((item) => item.id === action.payload.id);
      if (itemIndex < 0) {
        return state;
      }

      const currentItem = state.items[itemIndex];
      const nextItem: WallRuntimeItem = {
        ...currentItem,
        assetStatus: action.payload.assetStatus,
        width: action.payload.width ?? currentItem.width ?? null,
        height: action.payload.height ?? currentItem.height ?? null,
        orientation: action.payload.orientation ?? currentItem.orientation ?? null,
        lastError: action.payload.errorMessage ?? (action.payload.assetStatus === 'error' ? currentItem.lastError : null),
      };

      const hasChanged =
        nextItem.assetStatus !== currentItem.assetStatus
        || nextItem.width !== currentItem.width
        || nextItem.height !== currentItem.height
        || nextItem.orientation !== currentItem.orientation
        || nextItem.lastError !== currentItem.lastError;

      if (!hasChanged) {
        return state;
      }

      const items = state.items.map((item, index) => (index === itemIndex ? nextItem : item));
      const currentWasInvalidated =
        state.currentItemId === nextItem.id
        && !isWallItemRenderable(nextItem);
      const noCurrentYet = !state.currentItemId && isWallItemRenderable(nextItem);
      const selectionPolicy = resolveWallSelectionPolicy(state.settings);

      const nextCurrentItemId = currentWasInvalidated
        ? pickNextWallItemId(items, state.currentItemId, selectionPolicy, state.senderStats)
        : (noCurrentYet
            ? resolveInitialWallItemId(items, nextItem.id, selectionPolicy, state.senderStats)
            : state.currentItemId);
      const shouldMarkReplacement = Boolean(
        nextCurrentItemId
        && nextCurrentItemId !== state.currentItemId
        && (currentWasInvalidated || noCurrentYet),
      );
      const playbackState = shouldMarkReplacement
        ? markPlayback(items, state.senderStats, nextCurrentItemId as string)
        : {
            items,
            senderStats: state.senderStats,
          };
      const nextStatus = state.status === 'idle' && countQueueCandidates(playbackState.items) > 0
        ? 'playing'
        : state.status;

      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          status: nextStatus,
        },
        playbackState.items,
        nextCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'advance': {
      if (state.currentAd) {
        return state;
      }

      if (state.items.length === 0) {
        return state;
      }

      const nextAdScheduler = markPhotoAdvanced(syncAdSchedulerWithSettings(state, state.settings));

      if (shouldPlayAd(nextAdScheduler, state.ads.length)) {
        const { ad, nextIndex } = selectNextAd(state.ads, nextAdScheduler.lastAdIndex);

        if (ad) {
          return {
            ...state,
            currentAd: ad,
            adBaseItemId: state.currentItemId ?? null,
            adScheduler: {
              ...nextAdScheduler,
              lastAdIndex: nextIndex,
            },
          };
        }
      }

      const nextCurrentItemId = pickNextWallItemId(
        state.items,
        state.currentItemId,
        resolveWallSelectionPolicy(state.settings),
        state.senderStats,
      );
      if (!nextCurrentItemId) {
        return {
          ...state,
          status: state.status === 'playing' ? 'idle' : state.status,
          adScheduler: nextAdScheduler,
        };
      }

      const playbackState = markPlayback(state.items, state.senderStats, nextCurrentItemId);
      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          adScheduler: nextAdScheduler,
        },
        playbackState.items,
        nextCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'ad-finished': {
      if (!state.currentAd) {
        return state;
      }

      const nextAdScheduler = markAdPlayed(syncAdSchedulerWithSettings(state, state.settings));
      const nextCurrentItemId = pickNextWallItemId(
        state.items,
        state.adBaseItemId ?? state.currentItemId,
        resolveWallSelectionPolicy(state.settings),
        state.senderStats,
      );

      if (!nextCurrentItemId) {
        return resolveStateWithCurrentItem(
          {
            ...state,
            status: state.status === 'playing' ? 'idle' : state.status,
            currentAd: null,
            adBaseItemId: null,
            adScheduler: nextAdScheduler,
          },
          state.items,
          null,
        );
      }

      const playbackState = markPlayback(state.items, state.senderStats, nextCurrentItemId);
      const nextState = resolveStateWithCurrentItem(
        {
          ...state,
          currentAd: null,
          adBaseItemId: null,
          adScheduler: nextAdScheduler,
        },
        playbackState.items,
        nextCurrentItemId,
      );

      return {
        ...nextState,
        senderStats: playbackState.senderStats,
      };
    }

    case 'mark-expired':
      return { ...state, status: 'expired', currentAd: null, adBaseItemId: null };

    case 'sync-error':
      return countQueueCandidates(state.items) > 0 ? state : { ...state, status: 'error' };

    default:
      return state;
  }
}

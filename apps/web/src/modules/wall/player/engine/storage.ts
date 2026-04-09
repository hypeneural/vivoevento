import type {
  WallMediaItem,
  WallPlayerState,
  WallSenderRuntimeStats,
  WallRuntimeItem,
  WallVideoPlaybackState,
} from '../types';
import { mediaToRuntimeItem } from './selectors';

const STORAGE_VERSION = 4;
const STORAGE_PREFIX = 'eventovivo:wall:runtime';
const INDEXED_DB_NAME = 'eventovivo-wall-runtime';
const INDEXED_DB_STORE = 'runtime';

interface PersistedWallRuntimeItem {
  id: string;
  senderKey: string;
  assetStatus: WallRuntimeItem['assetStatus'];
  playedAt?: string | null;
  playCount: number;
  lastError?: string | null;
  width?: number | null;
  height?: number | null;
  orientation?: WallRuntimeItem['orientation'];
}

interface PersistedWallRuntimeState {
  version: number;
  currentItemId?: string | null;
  currentItemStartedAt?: string | null;
  senderStats: Record<string, WallSenderRuntimeStats>;
  items: PersistedWallRuntimeItem[];
  videoPlayback?: Pick<
    WallVideoPlaybackState,
    | 'itemId'
    | 'phase'
    | 'currentTime'
    | 'durationSeconds'
    | 'readyState'
    | 'exitReason'
    | 'failureReason'
    | 'stallCount'
    | 'posterVisible'
    | 'firstFrameReady'
    | 'playbackReady'
    | 'playingConfirmed'
    | 'startupDegraded'
    | 'playbackStartedAt'
    | 'lastItemId'
    | 'lastExitReason'
    | 'lastFailureReason'
  >;
}

function storageKey(code: string): string {
  return `${STORAGE_PREFIX}:${code}`;
}

function isBrowser(): boolean {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
}

function supportsIndexedDb(): boolean {
  return typeof window !== 'undefined' && 'indexedDB' in window;
}

function isPersistedState(value: unknown): value is PersistedWallRuntimeState {
  return Boolean(
    value
    && typeof value === 'object'
    && (value as PersistedWallRuntimeState).version === STORAGE_VERSION
    && Array.isArray((value as PersistedWallRuntimeState).items),
  );
}

let indexedDbPromise: Promise<IDBDatabase | null> | null = null;

function openRuntimeDb(): Promise<IDBDatabase | null> {
  if (!supportsIndexedDb()) {
    return Promise.resolve(null);
  }

  if (indexedDbPromise) {
    return indexedDbPromise;
  }

  indexedDbPromise = new Promise((resolve) => {
    try {
      const request = window.indexedDB.open(INDEXED_DB_NAME, 1);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(INDEXED_DB_STORE)) {
          db.createObjectStore(INDEXED_DB_STORE);
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => resolve(null);
      request.onblocked = () => resolve(null);
    } catch {
      resolve(null);
    }
  });

  return indexedDbPromise;
}

async function readIndexedRuntimeState(code: string): Promise<PersistedWallRuntimeState | null> {
  const db = await openRuntimeDb();
  if (!db) {
    return null;
  }

  return new Promise((resolve) => {
    try {
      const tx = db.transaction(INDEXED_DB_STORE, 'readonly');
      const store = tx.objectStore(INDEXED_DB_STORE);
      const request = store.get(storageKey(code));

      request.onsuccess = () => {
        const value = request.result;
        resolve(isPersistedState(value) ? value : null);
      };
      request.onerror = () => resolve(null);
    } catch {
      resolve(null);
    }
  });
}

async function writeIndexedRuntimeState(
  code: string,
  payload: PersistedWallRuntimeState,
): Promise<void> {
  const db = await openRuntimeDb();
  if (!db) {
    return;
  }

  await new Promise<void>((resolve) => {
    try {
      const tx = db.transaction(INDEXED_DB_STORE, 'readwrite');
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
      tx.onabort = () => resolve();

      tx.objectStore(INDEXED_DB_STORE).put(payload, storageKey(code));
    } catch {
      resolve();
    }
  });
}

async function deleteIndexedRuntimeState(code: string): Promise<void> {
  const db = await openRuntimeDb();
  if (!db) {
    return;
  }

  await new Promise<void>((resolve) => {
    try {
      const tx = db.transaction(INDEXED_DB_STORE, 'readwrite');
      tx.oncomplete = () => resolve();
      tx.onerror = () => resolve();
      tx.onabort = () => resolve();

      tx.objectStore(INDEXED_DB_STORE).delete(storageKey(code));
    } catch {
      resolve();
    }
  });
}

export function readWallRuntimeStorage(code: string): PersistedWallRuntimeState | null {
  if (!isBrowser()) {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(storageKey(code));
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as PersistedWallRuntimeState;
    if (!isPersistedState(parsed)) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export function writeWallRuntimeStorage(code: string, state: WallPlayerState): void {
  if (!isBrowser()) {
    return;
  }

  const payload: PersistedWallRuntimeState = {
    version: STORAGE_VERSION,
    currentItemId: state.currentItemId ?? null,
    currentItemStartedAt: state.currentItemStartedAt ?? null,
    senderStats: state.senderStats,
    items: state.items.map((item) => ({
      id: item.id,
      senderKey: item.senderKey,
      assetStatus: item.assetStatus,
      playedAt: item.playedAt ?? null,
      playCount: item.playCount,
      lastError: item.lastError ?? null,
      width: item.width ?? null,
      height: item.height ?? null,
      orientation: item.orientation ?? null,
    })),
    videoPlayback: {
      itemId: state.videoPlayback.itemId,
      phase: state.videoPlayback.phase,
      currentTime: state.videoPlayback.currentTime,
      durationSeconds: state.videoPlayback.durationSeconds,
      readyState: state.videoPlayback.readyState,
      exitReason: state.videoPlayback.exitReason,
      failureReason: state.videoPlayback.failureReason,
      stallCount: state.videoPlayback.stallCount,
      posterVisible: state.videoPlayback.posterVisible,
      firstFrameReady: state.videoPlayback.firstFrameReady,
      playbackReady: state.videoPlayback.playbackReady,
      playingConfirmed: state.videoPlayback.playingConfirmed,
      startupDegraded: state.videoPlayback.startupDegraded,
      playbackStartedAt: state.videoPlayback.playbackStartedAt ?? null,
      lastItemId: state.videoPlayback.lastItemId ?? null,
      lastExitReason: state.videoPlayback.lastExitReason ?? null,
      lastFailureReason: state.videoPlayback.lastFailureReason ?? null,
    },
  };

  try {
    window.localStorage.setItem(storageKey(code), JSON.stringify(payload));
  } catch {
    // Ignore quota and serialization failures in the wall runtime.
  }

  void writeIndexedRuntimeState(code, payload);
}

export async function readWallRuntimeStorageAsync(code: string): Promise<PersistedWallRuntimeState | null> {
  const indexed = await readIndexedRuntimeState(code);
  return indexed ?? readWallRuntimeStorage(code);
}

export function clearWallRuntimeStorage(code: string): void {
  if (isBrowser()) {
    try {
      window.localStorage.removeItem(storageKey(code));
    } catch {
      // Ignore local cleanup failures.
    }
  }

  void deleteIndexedRuntimeState(code);
}

export function hydrateWallRuntimeItems(
  mediaItems: WallMediaItem[],
  persistedState?: PersistedWallRuntimeState | null,
): WallRuntimeItem[] {
  const persistedItems = new Map(
    (persistedState?.items ?? []).map((item) => [item.id, item] as const),
  );

  return mediaItems.map((item) => {
    const previous = persistedItems.get(item.id);
    return mediaToRuntimeItem(item, previous ?? null);
  });
}

export function hydrateWallSenderStats(
  persistedState?: PersistedWallRuntimeState | null,
): Record<string, WallSenderRuntimeStats> {
  return persistedState?.senderStats ?? {};
}

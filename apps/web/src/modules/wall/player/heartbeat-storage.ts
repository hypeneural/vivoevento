import { resolveWallPersistentStorage } from './runtime-capabilities';

const STORAGE_PREFIX = 'eventovivo:wall:heartbeat';

interface StoredWallHeartbeatMeta {
  playerInstanceId: string;
  lastHeartbeatAt?: string | null;
  lastSyncAt?: string | null;
}

function storageKey(code: string) {
  return `${STORAGE_PREFIX}:${code}`;
}

function isBrowser() {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
}

export function readWallHeartbeatMeta(code: string): StoredWallHeartbeatMeta | null {
  if (!isBrowser()) {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(storageKey(code));
    return raw ? JSON.parse(raw) as StoredWallHeartbeatMeta : null;
  } catch {
    return null;
  }
}

export function writeWallHeartbeatMeta(code: string, patch: Partial<StoredWallHeartbeatMeta>) {
  if (!isBrowser()) {
    return;
  }

  const current = readWallHeartbeatMeta(code) ?? {
    playerInstanceId: '',
    lastHeartbeatAt: null,
    lastSyncAt: null,
  };

  try {
    window.localStorage.setItem(storageKey(code), JSON.stringify({
      ...current,
      ...patch,
    }));
  } catch {
    // Ignore persistence failures in the public player.
  }
}

export function clearWallHeartbeatMeta(code: string) {
  if (!isBrowser()) {
    return;
  }

  try {
    window.localStorage.removeItem(storageKey(code));
  } catch {
    // Ignore cleanup failures in the public player.
  }
}

export function getOrCreateWallPlayerInstanceId(code: string): string {
  const existing = readWallHeartbeatMeta(code)?.playerInstanceId;
  if (existing) {
    return existing;
  }

  const generated = typeof window !== 'undefined' && window.crypto?.randomUUID
    ? window.crypto.randomUUID()
    : `${code}-${Math.random().toString(36).slice(2, 12)}`;

  writeWallHeartbeatMeta(code, { playerInstanceId: generated });
  return generated;
}

export { resolveWallPersistentStorage };

import type { WallPersistentStorage } from './types';

function isBrowser() {
  return typeof window !== 'undefined';
}

export function supportsWallLocalStorage() {
  return isBrowser() && typeof window.localStorage !== 'undefined';
}

export function supportsWallIndexedDb() {
  return isBrowser() && typeof window.indexedDB !== 'undefined';
}

export function supportsWallCacheApi() {
  return isBrowser() && 'caches' in window;
}

export function resolveWallPersistentStorage(): WallPersistentStorage {
  if (supportsWallIndexedDb()) {
    return 'indexeddb';
  }

  if (supportsWallLocalStorage()) {
    return 'localstorage';
  }

  if (supportsWallCacheApi()) {
    return 'cache_api';
  }

  return isBrowser() ? 'unavailable' : 'none';
}

export function resolveWallCacheEnabled() {
  return supportsWallCacheApi() || supportsWallIndexedDb() || supportsWallLocalStorage();
}

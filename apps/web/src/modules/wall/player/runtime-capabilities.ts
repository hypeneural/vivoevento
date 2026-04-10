import type { WallPerformanceTier, WallPersistentStorage } from './types';

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

export interface WallRuntimeBudget {
  performanceTier: WallPerformanceTier;
  maxBoardPieces: number;
  maxConcurrentDecode: number;
  maxBurstItems: number;
  maxStrongAnimations: number;
}

export function resolveWallPerformanceTier(options?: {
  prefersReducedMotion?: boolean | null;
  deviceMemoryGb?: number | null;
  hardwareConcurrency?: number | null;
}): WallPerformanceTier {
  const prefersReducedMotion = options?.prefersReducedMotion ?? false;
  const deviceMemoryGb = options?.deviceMemoryGb ?? null;
  const hardwareConcurrency = options?.hardwareConcurrency ?? null;
  const isLowEndDevice = Boolean(
    (deviceMemoryGb != null && deviceMemoryGb > 0 && deviceMemoryGb <= 4)
    || (hardwareConcurrency != null && hardwareConcurrency > 0 && hardwareConcurrency <= 4),
  );

  if (prefersReducedMotion || isLowEndDevice) {
    return 'performance';
  }

  return 'premium';
}

export function resolveWallPerformanceTierFromEnvironment(): WallPerformanceTier {
  const prefersReducedMotion = typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;

  const navigatorLike = typeof navigator !== 'undefined'
    ? navigator as Navigator & {
      deviceMemory?: number;
      hardwareConcurrency?: number;
    }
    : null;

  return resolveWallPerformanceTier({
    prefersReducedMotion,
    deviceMemoryGb: Number.isFinite(navigatorLike?.deviceMemory)
      ? Number(navigatorLike?.deviceMemory)
      : null,
    hardwareConcurrency: Number.isFinite(navigatorLike?.hardwareConcurrency)
      ? Number(navigatorLike?.hardwareConcurrency)
      : null,
  });
}

export function resolveWallRuntimeBudget(
  performanceTier: WallPerformanceTier,
): WallRuntimeBudget {
  if (performanceTier === 'performance') {
    return {
      performanceTier,
      maxBoardPieces: 6,
      maxConcurrentDecode: 1,
      maxBurstItems: 1,
      maxStrongAnimations: 1,
    };
  }

  return {
    performanceTier,
    maxBoardPieces: performanceTier === 'preview' ? 6 : 9,
    maxConcurrentDecode: performanceTier === 'preview' ? 1 : 2,
    maxBurstItems: performanceTier === 'preview' ? 1 : 2,
    maxStrongAnimations: performanceTier === 'preview' ? 1 : 2,
  };
}

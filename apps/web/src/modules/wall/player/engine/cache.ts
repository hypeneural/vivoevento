import type { WallRuntimeItem } from '../types';
import { detectOrientation } from './selectors';
import { loadWallImageReadiness, type WallImageReadinessOptions } from './readiness';

export interface WallAssetProbeResult {
  status: 'loading' | 'ready' | 'stale' | 'error';
  width?: number | null;
  height?: number | null;
  orientation?: WallRuntimeItem['orientation'];
  errorMessage?: string | null;
}

export interface WallCacheDiagnostics {
  cacheEnabled: boolean;
  usageBytes: number | null;
  quotaBytes: number | null;
  hitCount: number;
  missCount: number;
  staleFallbackCount: number;
  hitRate: number;
}

const wallAssetCache = new Map<string, WallAssetProbeResult>();
const wallAssetInflight = new Map<string, Promise<WallAssetProbeResult>>();
const WALL_ASSET_CACHE_NAME = 'eventovivo-wall-assets-v1';
const wallCacheMetrics = {
  hitCount: 0,
  missCount: 0,
  staleFallbackCount: 0,
};

function resolveCached(result: WallAssetProbeResult): WallAssetProbeResult {
  return {
    ...result,
    width: result.width ?? null,
    height: result.height ?? null,
    orientation: result.orientation ?? detectOrientation(result.width ?? null, result.height ?? null),
    errorMessage: result.errorMessage ?? null,
  };
}

function supportsCacheApi(): boolean {
  return typeof window !== 'undefined' && 'caches' in window;
}

function supportsStorageEstimate(): boolean {
  return typeof navigator !== 'undefined' && typeof navigator.storage?.estimate === 'function';
}

async function openWallAssetCache(): Promise<Cache | null> {
  if (!supportsCacheApi()) {
    return null;
  }

  try {
    return await window.caches.open(WALL_ASSET_CACHE_NAME);
  } catch {
    return null;
  }
}

async function cacheWallAsset(url: string): Promise<void> {
  const cache = await openWallAssetCache();
  if (!cache) {
    return;
  }

  try {
    const existing = await cache.match(url);
    if (existing) {
      return;
    }

    const response = await fetch(url, {
      method: 'GET',
      cache: 'force-cache',
      credentials: 'omit',
    });

    if (!response.ok) {
      return;
    }

    await cache.put(url, response.clone());
  } catch {
    // Cache API is a resilience layer. Ignore failures silently.
  }
}

async function readCachedAssetBlob(url: string): Promise<Blob | null> {
  const cache = await openWallAssetCache();
  if (!cache) {
    return null;
  }

  try {
    const match = await cache.match(url);
    if (!match) {
      return null;
    }

    return await match.blob();
  } catch {
    return null;
  }
}

async function deleteCachedAsset(url: string): Promise<void> {
  const cache = await openWallAssetCache();
  if (!cache) {
    return;
  }

  try {
    await cache.delete(url);
  } catch {
    // Ignore cache deletion failures.
  }
}

async function loadImageSource(
  source: string,
  options?: WallImageReadinessOptions,
): Promise<WallAssetProbeResult> {
  const readiness = await loadWallImageReadiness(source, options);

  if (readiness.status === 'ready') {
    return {
      status: 'ready',
      width: readiness.width,
      height: readiness.height,
      orientation: readiness.orientation,
    };
  }

  return {
    status: 'error',
    errorMessage: readiness.errorMessage ?? 'Falha ao carregar a imagem do telao.',
  };
}

function loadVideoSource(source: string): Promise<WallAssetProbeResult> {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    video.preload = 'metadata';

    const cleanup = () => {
      video.removeAttribute('src');
      video.load();
    };

    video.onloadedmetadata = () => {
      const result: WallAssetProbeResult = {
        status: 'ready',
        width: video.videoWidth || null,
        height: video.videoHeight || null,
        orientation: detectOrientation(video.videoWidth || null, video.videoHeight || null),
      };
      cleanup();
      resolve(result);
    };

    video.onerror = () => {
      cleanup();
      resolve({
        status: 'error',
        errorMessage: 'Falha ao carregar o video do telao.',
      });
    };

    video.src = source;
  });
}

async function loadAssetFromCachedBlob(item: WallRuntimeItem): Promise<WallAssetProbeResult | null> {
  if (!item.url) {
    return null;
  }

  const blob = await readCachedAssetBlob(item.url);
  if (!blob) {
    return null;
  }

  const objectUrl = URL.createObjectURL(blob);

  try {
    const result = item.type === 'video'
      ? await loadVideoSource(objectUrl)
      : await loadImageSource(objectUrl);

    if (result.status === 'ready') {
      return {
        ...result,
        status: 'stale',
        errorMessage: 'Exibindo cache local do telao enquanto a rede falha.',
      };
    }

    return result;
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}

export async function primeWallAsset(
  item: WallRuntimeItem,
  onStatus?: (result: WallAssetProbeResult) => void,
  options?: WallImageReadinessOptions,
): Promise<WallAssetProbeResult> {
  if (!item.url) {
    const result = resolveCached({
      status: 'error',
      errorMessage: 'Item do telao sem URL valida.',
    });
    onStatus?.(result);
    return result;
  }

  const cached = wallAssetCache.get(item.url);
  if (cached && cached.status !== 'stale') {
    wallCacheMetrics.hitCount += 1;
    const result = resolveCached(cached);
    onStatus?.(result);
    return result;
  }

  const inflight = wallAssetInflight.get(item.url);
  if (inflight) {
    onStatus?.({ status: 'loading' });
    const result = resolveCached(await inflight);
    onStatus?.(result);
    return result;
  }

  onStatus?.({ status: 'loading' });

  const loader = (async () => {
    const directResult = item.type === 'video'
      ? await loadVideoSource(item.url as string)
      : await loadImageSource(item.url as string, options);

    if (directResult.status === 'ready') {
      wallCacheMetrics.missCount += 1;
      void cacheWallAsset(item.url as string);
      return directResult;
    }

    const staleFallback = await loadAssetFromCachedBlob(item);
    if (staleFallback?.status === 'stale') {
      wallCacheMetrics.hitCount += 1;
      wallCacheMetrics.staleFallbackCount += 1;
      return staleFallback;
    }

    wallCacheMetrics.missCount += 1;
    return staleFallback ?? directResult;
  })();

  wallAssetInflight.set(item.url, loader);

  try {
    const result = resolveCached(await loader);
    if (result.status === 'ready') {
      wallAssetCache.set(item.url, result);
    }
    onStatus?.(result);
    return result;
  } finally {
    wallAssetInflight.delete(item.url);
  }
}

export async function clearWallAssetCaches(options?: {
  urls?: string[];
  resetMetrics?: boolean;
}): Promise<void> {
  const urls = options?.urls?.filter(Boolean) ?? [];

  if (urls.length === 0) {
    wallAssetCache.clear();
    wallAssetInflight.clear();

    const cache = await openWallAssetCache();
    if (cache) {
      try {
        const keys = await cache.keys();
        await Promise.all(keys.map((request) => cache.delete(request)));
      } catch {
        // Ignore cache cleanup failures.
      }
    }
  } else {
    urls.forEach((url) => {
      wallAssetCache.delete(url);
      wallAssetInflight.delete(url);
    });

    await Promise.all(urls.map((url) => deleteCachedAsset(url)));
  }

  if (options?.resetMetrics ?? true) {
    resetWallCacheMetrics();
  }
}

export function resetWallCacheMetrics(): void {
  wallCacheMetrics.hitCount = 0;
  wallCacheMetrics.missCount = 0;
  wallCacheMetrics.staleFallbackCount = 0;
}

export async function getWallCacheDiagnostics(): Promise<WallCacheDiagnostics> {
  let usageBytes: number | null = null;
  let quotaBytes: number | null = null;

  if (supportsStorageEstimate()) {
    try {
      const estimate = await navigator.storage.estimate();
      usageBytes = Number.isFinite(estimate.usage) ? (estimate.usage as number) : null;
      quotaBytes = Number.isFinite(estimate.quota) ? (estimate.quota as number) : null;
    } catch {
      usageBytes = null;
      quotaBytes = null;
    }
  }

  const total = wallCacheMetrics.hitCount + wallCacheMetrics.missCount;

  return {
    cacheEnabled: supportsCacheApi(),
    usageBytes,
    quotaBytes,
    hitCount: wallCacheMetrics.hitCount,
    missCount: wallCacheMetrics.missCount,
    staleFallbackCount: wallCacheMetrics.staleFallbackCount,
    hitRate: total > 0 ? Math.round((wallCacheMetrics.hitCount / total) * 100) : 0,
  };
}

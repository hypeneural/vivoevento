import { routeImports } from '@/app/routing/route-preload';
import type { PlayRuntimeAsset } from '@/lib/api-types';
import { queryClient } from '@/lib/query-client';
import { fetchPublicPlayGame } from '@/modules/play/api/playApi';
import { preloadPlayableGame } from '@/modules/play/phaser/registerDefaultGames';
import { getPlayAssetQueryProfile, getPlayConnectionProfile } from '@/modules/play/utils/play-device-profile';

type PrefetchMode = 'intent' | 'viewport' | 'route';

type WarmPublicGameParams = {
  eventSlug: string;
  gameSlug: string;
  gameTypeKey?: string | null;
};

const warmedAssetUrls = new Set<string>();

export function getPlayPrefetchPolicy() {
  const profile = getPlayConnectionProfile();
  const constrained = profile.saveData || profile.effectiveType === 'slow-2g' || profile.effectiveType === '2g';
  const standard = profile.effectiveType === '3g' || ((profile.downlink ?? 0) > 0 && (profile.downlink ?? 0) < 1.5);

  if (constrained) {
    return {
      allowRouteWarmup: true,
      allowIntentWarmup: true,
      allowViewportWarmup: false,
      allowRuntimeWarmupOnIntent: false,
      allowAssetWarmup: false,
      assetBudget: 0,
    };
  }

  if (standard) {
    return {
      allowRouteWarmup: true,
      allowIntentWarmup: true,
      allowViewportWarmup: false,
      allowRuntimeWarmupOnIntent: false,
      allowAssetWarmup: true,
      assetBudget: 1,
    };
  }

  return {
    allowRouteWarmup: true,
    allowIntentWarmup: true,
    allowViewportWarmup: true,
    allowRuntimeWarmupOnIntent: true,
    allowAssetWarmup: true,
    assetBudget: 4,
  };
}

export function schedulePlayIdleTask(task: () => void) {
  if (typeof window === 'undefined') {
    return () => undefined;
  }

  if ('requestIdleCallback' in window) {
    const handle = window.requestIdleCallback(() => task(), { timeout: 1200 });
    return () => window.cancelIdleCallback(handle);
  }

  const handle = window.setTimeout(task, 250);
  return () => window.clearTimeout(handle);
}

export function warmPublicGameRoute() {
  return routeImports.publicGame();
}

export function warmPublicGameExperience(
  params: WarmPublicGameParams,
  mode: PrefetchMode = 'intent',
) {
  const policy = getPlayPrefetchPolicy();
  const assetProfile = getPlayAssetQueryProfile();

  if (mode === 'viewport' && !policy.allowViewportWarmup) {
    return;
  }

  if (mode === 'intent' && !policy.allowIntentWarmup) {
    return;
  }

  if (mode === 'route' && !policy.allowRouteWarmup) {
    return;
  }

  void warmPublicGameRoute();

  if (mode !== 'route') {
    void queryClient.prefetchQuery({
      queryKey: ['public-play-game', params.eventSlug, params.gameSlug, assetProfile.cacheKey],
      queryFn: () => fetchPublicPlayGame(params.eventSlug, params.gameSlug, assetProfile.params),
      staleTime: 60_000,
    });
  }
}

export function warmPlayableGameRuntime(
  gameTypeKey: string | null | undefined,
  mode: PrefetchMode = 'intent',
  options?: { force?: boolean },
) {
  if (!gameTypeKey) {
    return Promise.resolve(null);
  }

  const policy = getPlayPrefetchPolicy();

  if (!options?.force) {
    if (mode === 'intent' && !policy.allowRuntimeWarmupOnIntent) {
      return Promise.resolve(null);
    }

    if (mode === 'viewport' && !policy.allowViewportWarmup) {
      return Promise.resolve(null);
    }
  }

  return preloadPlayableGame(gameTypeKey);
}

export function warmRuntimeAssets(
  assets: PlayRuntimeAsset[],
  options?: { maxItems?: number },
) {
  const policy = getPlayPrefetchPolicy();

  if (!policy.allowAssetWarmup) {
    return;
  }

  const budget = Math.max(0, options?.maxItems ?? policy.assetBudget);

  assets
    .filter((asset) => Boolean(asset.url))
    .slice(0, budget)
    .forEach((asset) => {
      if (!asset.url || warmedAssetUrls.has(asset.url)) {
        return;
      }

      warmedAssetUrls.add(asset.url);

      const image = new Image();
      image.decoding = 'async';
      image.onerror = () => {
        warmedAssetUrls.delete(asset.url as string);
      };
      image.src = asset.url;
    });
}

import type { WallRuntimeBudget } from '../runtime-capabilities';
import type { WallLayout, WallRuntimeItem } from '../types';
import { detectOrientation } from './selectors';

export interface WallImageReadinessOptions {
  fetchPriority?: 'high' | 'low' | 'auto';
  decoding?: 'sync' | 'async' | 'auto';
}

export interface WallImageReadinessResult {
  status: 'ready' | 'error';
  networkReady: boolean;
  decodeReady: boolean;
  width: number | null;
  height: number | null;
  orientation: WallRuntimeItem['orientation'];
  errorMessage?: string | null;
}

export interface WallPreloadPlanEntry {
  item: WallRuntimeItem;
  reason: 'current' | 'anchor' | 'visible' | 'next-burst';
  fetchPriority: 'high' | 'low' | 'auto';
  decoding: 'sync' | 'async' | 'auto';
}

export function waitForImageLoad(image: HTMLImageElement): Promise<void> {
  return new Promise((resolve, reject) => {
    image.onload = () => resolve();
    image.onerror = () => reject(new Error('image_load_failed'));
  });
}

export async function loadWallImageReadiness(
  source: string,
  options: WallImageReadinessOptions = {},
): Promise<WallImageReadinessResult> {
  const image = new Image();

  if ('fetchPriority' in image && options.fetchPriority) {
    image.fetchPriority = options.fetchPriority;
  }

  if (options.decoding) {
    image.decoding = options.decoding;
  }

  image.src = source;

  try {
    await waitForImageLoad(image);
  } catch {
    return {
      status: 'error',
      networkReady: false,
      decodeReady: false,
      width: null,
      height: null,
      orientation: null,
      errorMessage: 'Falha ao carregar a imagem do telao.',
    };
  }

  if (typeof image.decode === 'function') {
    try {
      await image.decode();
    } catch {
      return {
        status: 'error',
        networkReady: true,
        decodeReady: false,
        width: image.naturalWidth || null,
        height: image.naturalHeight || null,
        orientation: detectOrientation(image.naturalWidth || null, image.naturalHeight || null),
        errorMessage: 'Falha ao decodificar a imagem do telao.',
      };
    }
  }

  return {
    status: 'ready',
    networkReady: true,
    decodeReady: true,
    width: image.naturalWidth || null,
    height: image.naturalHeight || null,
    orientation: detectOrientation(image.naturalWidth || null, image.naturalHeight || null),
    errorMessage: null,
  };
}

function isBoardLayout(layout: WallLayout): boolean {
  return layout === 'carousel' || layout === 'mosaic' || layout === 'grid' || layout === 'puzzle';
}

function resolveVisibleSlotCount(layout: WallLayout, budget: WallRuntimeBudget): number {
  if (layout === 'puzzle') {
    return budget.maxBoardPieces;
  }

  if (isBoardLayout(layout)) {
    return 3;
  }

  return 1;
}

function getEligibleItems(items: WallRuntimeItem[]): WallRuntimeItem[] {
  return items.filter((item) => item.assetStatus !== 'error' && Boolean(item.url));
}

export function resolveWallPreloadPlan(options: {
  items: WallRuntimeItem[];
  currentItem: WallRuntimeItem | null;
  layout: WallLayout;
  budget: WallRuntimeBudget;
  anchorItemId?: string | null;
}): WallPreloadPlanEntry[] {
  const eligibleItems = getEligibleItems(options.items);
  const visibleSlotCount = resolveVisibleSlotCount(options.layout, options.budget);
  const entries: WallPreloadPlanEntry[] = [];
  const seen = new Set<string>();

  const pushEntry = (
    item: WallRuntimeItem | null | undefined,
    reason: WallPreloadPlanEntry['reason'],
    fetchPriority: WallPreloadPlanEntry['fetchPriority'],
    decoding: WallPreloadPlanEntry['decoding'],
  ) => {
    if (!item || !item.url || seen.has(item.id)) {
      return;
    }

    seen.add(item.id);
    entries.push({
      item,
      reason,
      fetchPriority,
      decoding,
    });
  };

  pushEntry(options.currentItem, 'current', 'high', 'sync');

  const anchorItem = options.anchorItemId
    ? eligibleItems.find((item) => item.id === options.anchorItemId) ?? null
    : null;

  pushEntry(anchorItem, 'anchor', 'high', 'sync');

  const visibleBudget = Math.max(0, visibleSlotCount - entries.length);

  eligibleItems
    .filter((item) => !seen.has(item.id))
    .slice(0, visibleBudget)
    .forEach((item) => {
      pushEntry(item, 'visible', 'auto', 'async');
    });

  eligibleItems
    .filter((item) => !seen.has(item.id))
    .slice(0, options.budget.maxBurstItems)
    .forEach((item) => {
      pushEntry(item, 'next-burst', 'high', 'auto');
    });

  return entries;
}

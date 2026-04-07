/**
 * useSideThumbnails — Rotating window of thumbnails for side columns.
 *
 * Features:
 * - Selects 8 items from the queue, excluding the current item
 * - Splits 4 left + 4 right
 * - Refreshes every 20 seconds (configurable)
 * - Returns empty arrays when < 2 items available
 * - Uses thumbnail_url when available for lower bandwidth
 *
 * Inspired by Kululu's smallMediaList pattern.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { WallRuntimeItem } from '../types';

const DEFAULT_THUMB_COUNT = 8;
const DEFAULT_REFRESH_MS = 20_000;

export interface SideThumbnailItem {
  id: string;
  url: string;
  sender_name?: string | null;
}

export interface SideThumbnailsResult {
  leftItems: SideThumbnailItem[];
  rightItems: SideThumbnailItem[];
  enabled: boolean;
}

/**
 * Select N items from the list, wrapping around from an offset.
 * Excludes the current item ID.
 */
function selectThumbnails(
  items: WallRuntimeItem[],
  currentItemId: string | null | undefined,
  count: number,
  offset: number,
): SideThumbnailItem[] {
  const candidates = items.filter(
    (item) => item.id !== currentItemId && item.assetStatus === 'ready',
  );

  if (candidates.length < 2) return [];

  const result: SideThumbnailItem[] = [];
  const total = candidates.length;

  for (let i = 0; i < Math.min(count, total); i++) {
    const idx = (offset + i) % total;
    const item = candidates[idx];
    result.push({
      id: item.id,
      url: item.url,
      sender_name: item.sender_name,
    });
  }

  return result;
}

export function useSideThumbnails(
  items: WallRuntimeItem[],
  currentItemId: string | null | undefined,
  options: {
    enabled?: boolean;
    count?: number;
    refreshMs?: number;
  } = {},
): SideThumbnailsResult {
  const {
    enabled = true,
    count = DEFAULT_THUMB_COUNT,
    refreshMs = DEFAULT_REFRESH_MS,
  } = options;

  const [offset, setOffset] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Advance offset on each refresh
  const advance = useCallback(() => {
    setOffset((prev) => prev + count);
  }, [count]);

  // Setup refresh timer
  useEffect(() => {
    if (!enabled) return;

    timerRef.current = setInterval(advance, refreshMs);

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, [enabled, refreshMs, advance]);

  const selected = useMemo(
    () => (enabled ? selectThumbnails(items, currentItemId, count, offset) : []),
    [items, currentItemId, count, offset, enabled],
  );

  const half = Math.ceil(selected.length / 2);

  return {
    leftItems: selected.slice(0, half),
    rightItems: selected.slice(half),
    enabled: enabled && selected.length > 0,
  };
}

// Export for testing
export { selectThumbnails };

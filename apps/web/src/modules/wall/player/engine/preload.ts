/**
 * Preload — Dedicated next-item preloading with img.decode().
 *
 * When the player is running, this module resolves the EXACT next item
 * that pickNextWallItemId() would return, and aggressively preloads it
 * using img.decode() (images) or preload="auto" (video) to eliminate
 * micro-flicker between transitions.
 *
 * Phase 0.1 of the Wall Player Improvements.
 */

import type { WallRuntimeItem, WallSenderRuntimeStats, WallSettings } from '../types';
import { pickNextWallItemId, resolveWallSelectionPolicy } from './selectors';

/**
 * Predict the next item that `advance` would select.
 */
export function resolveNextPreloadItem(
  items: WallRuntimeItem[],
  currentItemId: string | null | undefined,
  settings: WallSettings | null | undefined,
  senderStats: Record<string, WallSenderRuntimeStats>,
): WallRuntimeItem | null {
  if (!settings || items.length === 0 || !currentItemId) {
    return null;
  }

  const policy = resolveWallSelectionPolicy(settings);
  const nextId = pickNextWallItemId(items, currentItemId, policy, senderStats);

  if (!nextId || nextId === currentItemId) {
    return null;
  }

  return items.find((item) => item.id === nextId) ?? null;
}

/**
 * Preload an image using img.decode() for flicker-free rendering.
 * Returns true if decode succeeded, false otherwise.
 */
export function preloadImageWithDecode(url: string): Promise<boolean> {
  return new Promise((resolve) => {
    const img = new Image();
    img.src = url;

    if (typeof img.decode === 'function') {
      img.decode()
        .then(() => resolve(true))
        .catch(() => resolve(false));
    } else {
      // Fallback: rely on onload
      img.onload = () => resolve(true);
      img.onerror = () => resolve(false);
    }
  });
}

/**
 * Preload a video by promoting preload from "metadata" to "auto".
 * This triggers the browser to buffer the video data.
 */
export function preloadVideoAuto(url: string): void {
  const video = document.createElement('video');
  video.preload = 'auto';
  video.muted = true;
  video.src = url;
  // Just let the browser start buffering — we don't need to track it.
  // The element will be GC'd when the reference goes out of scope.
}

/**
 * Preload the next item based on its media type.
 */
export async function preloadNextItem(item: WallRuntimeItem): Promise<void> {
  if (!item.url) return;

  if (item.type === 'video') {
    preloadVideoAuto(item.url);
  } else {
    await preloadImageWithDecode(item.url);
  }
}

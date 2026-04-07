/**
 * useEmbedMode — Parses query params for embed/iframe mode.
 *
 * Supported params:
 * - embed=1 — activates embed mode (hides branding/QR, no fullscreen)
 * - hideSides=1 — force-hides side thumbnails
 * - hideQR=1 — hides QR code
 * - hideBranding=1 — hides branding overlay
 * - layout=<value> — override layout
 * - transition=<value> — override transition
 *
 * Inspired by Kululu's embed query param system.
 */

import { useMemo } from 'react';
import type { WallLayout, WallTransition } from '../types';

export interface EmbedOverrides {
  embedMode: boolean;
  hideSideThumbnails: boolean;
  hideQR: boolean;
  hideBranding: boolean;
  layoutOverride: WallLayout | null;
  transitionOverride: WallTransition | null;
}

function parseFlag(params: URLSearchParams, key: string): boolean {
  return params.get(key) === '1' || params.get(key) === 'true';
}

export function useEmbedMode(): EmbedOverrides {
  return useMemo(() => {
    const params = new URLSearchParams(window.location.search);
    const embedMode = parseFlag(params, 'embed');

    return {
      embedMode,
      hideSideThumbnails: parseFlag(params, 'hideSides'),
      hideQR: embedMode || parseFlag(params, 'hideQR'),
      hideBranding: embedMode || parseFlag(params, 'hideBranding'),
      layoutOverride: (params.get('layout') as WallLayout) || null,
      transitionOverride: (params.get('transition') as WallTransition) || null,
    };
  }, []);
}

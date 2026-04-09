/**
 * Layout Strategy — Auto-layout resolver
 *
 * When layout is "auto", picks the best layout based on media orientation
 * and whether the photo has a caption or is featured.
 */

import type { WallRuntimeItem, WallLayout } from '../types';

type RenderableLayout = Exclude<WallLayout, 'auto'>;

function hasCaption(media: WallRuntimeItem): boolean {
  return Boolean(media.caption?.trim());
}

export function resolveRenderableLayout(
  requested: WallLayout,
  media: WallRuntimeItem,
  videoMultiLayoutPolicy: 'disallow' | 'one' | 'all' = 'disallow',
): RenderableLayout {
  if (media.type === 'video' && isMultiItemLayout(requested) && videoMultiLayoutPolicy === 'disallow') {
    return 'fullscreen';
  }

  if (requested !== 'auto') return requested;

  const withCaption = hasCaption(media);

  switch (media.orientation) {
    case 'vertical':
      if (media.is_featured) return 'spotlight';
      return withCaption ? 'split' : 'cinematic';

    case 'squareish':
      if (media.is_featured) return 'polaroid';
      return withCaption ? 'gallery' : 'fullscreen';

    case 'horizontal':
    default:
      if (media.is_featured && !withCaption) return 'kenburns';
      return withCaption ? 'cinematic' : 'fullscreen';
  }
}

export function resolvePrimaryMediaFit(
  layout: RenderableLayout,
  media: WallRuntimeItem,
): 'contain' | 'cover' {
  if (media.type === 'video' && layout === 'split' && media.orientation === 'horizontal') {
    return 'cover';
  }

  return 'contain';
}

export function shouldRenderFloatingCaption(layout: RenderableLayout): boolean {
  return layout === 'fullscreen' || layout === 'cinematic';
}

const MULTI_ITEM_LAYOUTS = new Set<string>(['carousel', 'mosaic', 'grid']);

/**
 * Returns true if the layout requires multiple items displayed simultaneously.
 * Multi-item layouts are never auto-resolved—they must be explicitly chosen.
 */
export function isMultiItemLayout(layout: string): boolean {
  return MULTI_ITEM_LAYOUTS.has(layout);
}

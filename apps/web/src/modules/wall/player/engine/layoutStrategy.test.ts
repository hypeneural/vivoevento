/**
 * Layout Strategy tests — verifies auto-resolve routing for all layouts.
 */
import { describe, expect, it } from 'vitest';
import { resolveRenderableLayout, resolvePrimaryMediaFit, shouldRenderFloatingCaption } from '../engine/layoutStrategy';
import type { WallRuntimeItem, WallLayout } from '../types';

function makeMedia(overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
  return {
    id: 'test-1',
    url: 'https://cdn.example.com/test.jpg',
    type: 'image',
    sender_name: 'Ana',
    is_featured: false,
    caption: null,
    orientation: 'horizontal',
    senderKey: 'sender-ana',
    assetStatus: 'ready',
    playCount: 0,
    width: 1920,
    height: 1080,
    ...overrides,
  };
}

describe('resolveRenderableLayout', () => {
  it('returns the requested layout when not auto', () => {
    const layouts: WallLayout[] = ['fullscreen', 'polaroid', 'split', 'cinematic', 'kenburns', 'spotlight', 'gallery'];
    for (const layout of layouts) {
      expect(resolveRenderableLayout(layout, makeMedia())).toBe(layout);
    }
  });

  // ─── Horizontal ──────────────────────────────
  it('auto + horizontal + featured + no caption = kenburns', () => {
    const media = makeMedia({ orientation: 'horizontal', is_featured: true });
    expect(resolveRenderableLayout('auto', media)).toBe('kenburns');
  });

  it('auto + horizontal + featured + caption = cinematic', () => {
    const media = makeMedia({ orientation: 'horizontal', is_featured: true, caption: 'Nice photo' });
    expect(resolveRenderableLayout('auto', media)).toBe('cinematic');
  });

  it('auto + horizontal + not featured + caption = cinematic', () => {
    const media = makeMedia({ orientation: 'horizontal', caption: 'Nice photo' });
    expect(resolveRenderableLayout('auto', media)).toBe('cinematic');
  });

  it('auto + horizontal + not featured + no caption = fullscreen', () => {
    const media = makeMedia({ orientation: 'horizontal' });
    expect(resolveRenderableLayout('auto', media)).toBe('fullscreen');
  });

  // ─── Vertical ───────────────────────────────
  it('auto + vertical + featured = spotlight', () => {
    const media = makeMedia({ orientation: 'vertical', is_featured: true });
    expect(resolveRenderableLayout('auto', media)).toBe('spotlight');
  });

  it('auto + vertical + not featured + caption = split', () => {
    const media = makeMedia({ orientation: 'vertical', caption: 'A photo' });
    expect(resolveRenderableLayout('auto', media)).toBe('split');
  });

  it('auto + vertical + not featured + no caption = cinematic', () => {
    const media = makeMedia({ orientation: 'vertical' });
    expect(resolveRenderableLayout('auto', media)).toBe('cinematic');
  });

  // ─── Squareish ──────────────────────────────
  it('auto + squareish + featured = polaroid', () => {
    const media = makeMedia({ orientation: 'squareish', is_featured: true });
    expect(resolveRenderableLayout('auto', media)).toBe('polaroid');
  });

  it('auto + squareish + not featured + caption = gallery', () => {
    const media = makeMedia({ orientation: 'squareish', caption: 'A photo' });
    expect(resolveRenderableLayout('auto', media)).toBe('gallery');
  });

  it('auto + squareish + not featured + no caption = fullscreen', () => {
    const media = makeMedia({ orientation: 'squareish' });
    expect(resolveRenderableLayout('auto', media)).toBe('fullscreen');
  });
});

describe('resolvePrimaryMediaFit', () => {
  it('returns contain by default', () => {
    expect(resolvePrimaryMediaFit('fullscreen', makeMedia())).toBe('contain');
  });

  it('returns cover for split + horizontal video', () => {
    const media = makeMedia({ type: 'video', orientation: 'horizontal' });
    expect(resolvePrimaryMediaFit('split', media)).toBe('cover');
  });
});

describe('shouldRenderFloatingCaption', () => {
  it('returns true for fullscreen', () => {
    expect(shouldRenderFloatingCaption('fullscreen')).toBe(true);
  });

  it('returns true for cinematic', () => {
    expect(shouldRenderFloatingCaption('cinematic')).toBe(true);
  });

  it('returns false for polaroid', () => {
    expect(shouldRenderFloatingCaption('polaroid')).toBe(false);
  });
});

import { describe, expect, it } from 'vitest';

import {
  getWallLayoutDefinition,
  isBoardThemeLayout,
  wallLayoutRegistry,
} from './registry';

describe('wall layout registry', () => {
  it('registers current wall layouts plus puzzle with stable metadata', () => {
    expect(Object.keys(wallLayoutRegistry)).toEqual([
      'auto',
      'fullscreen',
      'polaroid',
      'split',
      'cinematic',
      'kenburns',
      'spotlight',
      'gallery',
      'carousel',
      'mosaic',
      'grid',
      'puzzle',
    ]);

    expect(getWallLayoutDefinition('fullscreen')).toMatchObject({
      id: 'fullscreen',
      kind: 'single',
      version: '2026-04-10',
    });
  });

  it('treats puzzle as a board layout with explicit v1 video guardrails', () => {
    expect(getWallLayoutDefinition('puzzle')).toMatchObject({
      id: 'puzzle',
      kind: 'board',
      capabilities: {
        supportsVideoPlayback: false,
        supportsVideoPosterOnly: false,
        supportsMultiVideo: false,
        maxSimultaneousVideos: 0,
        fallbackVideoLayout: 'cinematic',
        supportsThemeConfig: true,
      },
    });
  });

  it('marks carousel mosaic grid and puzzle as board layouts', () => {
    expect(isBoardThemeLayout('carousel')).toBe(true);
    expect(isBoardThemeLayout('mosaic')).toBe(true);
    expect(isBoardThemeLayout('grid')).toBe(true);
    expect(isBoardThemeLayout('puzzle')).toBe(true);
    expect(isBoardThemeLayout('fullscreen')).toBe(false);
  });
});

import { describe, expect, it } from 'vitest';

import { isPuzzleCoverAsset, resolvePuzzleCoverAsset } from './puzzleAssets';

describe('puzzleAssets', () => {
  it('accepts only image runtime assets as playable puzzle covers', () => {
    expect(isPuzzleCoverAsset({
      id: 'image-1',
      url: 'https://cdn.example.com/cover.webp',
      mimeType: 'image/webp',
    })).toBe(true);

    expect(isPuzzleCoverAsset({
      id: 'video-1',
      url: 'https://cdn.example.com/cover.mp4',
      mimeType: 'video/mp4',
    })).toBe(false);

    expect(isPuzzleCoverAsset({
      id: 'broken-1',
      url: null,
      mimeType: 'image/webp',
    })).toBe(false);
  });

  it('returns the first valid image cover and ignores videos', () => {
    const cover = resolvePuzzleCoverAsset([
      {
        id: 'video-1',
        url: 'https://cdn.example.com/cover.mp4',
        mimeType: 'video/mp4',
      },
      {
        id: 'image-1',
        url: 'https://cdn.example.com/cover.webp',
        mimeType: 'image/webp',
      },
    ]);

    expect(cover?.id).toBe('image-1');
  });
});

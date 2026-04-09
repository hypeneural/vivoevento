import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { MediaSurface } from './MediaSurface';
import type { WallRuntimeItem } from '../types';

function makeMedia(overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
  return {
    id: 'media_1',
    url: 'https://cdn.example.com/media-1.mp4',
    type: 'video',
    sender_name: 'Marina',
    sender_key: 'sender-marina',
    senderKey: 'sender-marina',
    source_type: 'public_upload',
    caption: 'Video da pista',
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    created_at: '2026-04-08T18:00:00Z',
    assetStatus: 'ready',
    playedAt: null,
    playCount: 0,
    lastError: null,
    width: 1920,
    height: 1080,
    orientation: 'horizontal',
    ...overrides,
  };
}

describe('MediaSurface', () => {
  it('renders slideshow videos as muted autoplay looping media', () => {
    const { container } = render(
      <MediaSurface media={makeMedia()} fit="cover" />,
    );

    const video = container.querySelector('video');

    expect(video).not.toBeNull();
    expect(video?.autoplay).toBe(true);
    expect(video?.muted).toBe(true);
    expect(video?.loop).toBe(false);
    expect(video?.hasAttribute('playsinline')).toBe(true);
    expect(video?.hasAttribute('controls')).toBe(false);
    expect(video?.hasAttribute('poster')).toBe(false);
    expect(video?.className).toContain('object-cover');
    expect(video?.getAttribute('src')).toContain('media-1.mp4');
  });

  it('renders poster-only surfaces for video background copies', () => {
    const { container } = render(
      <MediaSurface
        media={makeMedia({
          preview_url: 'https://cdn.example.com/media-1-poster.jpg',
        })}
        renderVideoPosterOnly
      />,
    );

    const image = container.querySelector('img');
    const video = container.querySelector('video');

    expect(image?.getAttribute('src')).toContain('media-1-poster.jpg');
    expect(video).toBeNull();
  });

  it('renders slideshow images with contain fit by default', () => {
    const { container } = render(
      <MediaSurface
        media={makeMedia({
          id: 'media_2',
          url: 'https://cdn.example.com/media-2.jpg',
          type: 'image',
        })}
      />,
    );

    const image = container.querySelector('img');

    expect(image).not.toBeNull();
    expect(image?.className).toContain('object-contain');
    expect(image?.getAttribute('src')).toContain('media-2.jpg');
  });
});

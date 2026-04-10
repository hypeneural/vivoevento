import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ThemeMediaSurface } from './ThemeMediaSurface';
import type { WallRuntimeItem } from '../../types';

function makeImage(id: string): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: `Sender ${id}`,
    sender_key: `sender-${id}`,
    senderKey: `sender-${id}`,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    assetStatus: 'ready',
    playCount: 0,
    width: 1200,
    height: 900,
    orientation: 'horizontal',
  };
}

function makeVideo(id: string): WallRuntimeItem {
  return {
    ...makeImage(id),
    url: `https://cdn.example.com/${id}.mp4`,
    preview_url: `https://cdn.example.com/${id}-poster.jpg`,
    type: 'video',
  };
}

describe('ThemeMediaSurface', () => {
  it('renders an image surface with the provided clip path', () => {
    render(
      <ThemeMediaSurface
        media={makeImage('image-1')}
        clipPathId="puzzle-shape-a"
      />,
    );

    const img = screen.getByRole('img');
    const shell = screen.getByTestId('theme-media-surface');

    expect(img).toHaveAttribute('src', 'https://cdn.example.com/image-1.jpg');
    expect(shell.style.clipPath).toContain('puzzle-shape-a');
  });

  it('renders only a poster image for videos and never mounts a video tag', () => {
    const { container } = render(
      <ThemeMediaSurface
        media={makeVideo('video-1')}
        clipPathId="puzzle-shape-a"
      />,
    );

    expect(container.querySelectorAll('video')).toHaveLength(0);
    expect(screen.getByRole('img')).toHaveAttribute('src', 'https://cdn.example.com/video-1-poster.jpg');
  });
});

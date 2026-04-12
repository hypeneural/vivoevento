import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { GalleryRenderer } from './components/GalleryRenderer';
import { galleryExperienceFixtures } from './gallery-builder';
import type { ApiEventMediaItem } from '@/lib/api-types';

function image(index: number): ApiEventMediaItem {
  return {
    id: index,
    event_id: 10,
    media_type: 'image',
    channel: 'upload',
    status: 'published',
    processing_status: null,
    moderation_status: 'approved',
    publication_status: 'published',
    sender_name: 'Convidado',
    caption: `Imagem ${index}`,
    thumbnail_url: `https://cdn.eventovivo.test/${index}-thumb.webp`,
    preview_url: `https://cdn.eventovivo.test/${index}-preview.webp`,
    original_url: null,
    created_at: null,
    published_at: null,
    is_featured: false,
    width: 1200,
    height: 800,
    orientation: 'landscape',
  };
}

describe('public gallery loading priority', () => {
  it('keeps hero-independent first media eager and below-fold media lazy', () => {
    render(
      <GalleryRenderer
        media={Array.from({ length: 8 }, (_, index) => image(index + 1))}
        experience={galleryExperienceFixtures.weddingPremiumLight}
      />,
    );

    expect(screen.getByRole('img', { name: 'Imagem 1' })).toHaveAttribute('loading', 'eager');
    expect(screen.getByRole('img', { name: 'Imagem 5' })).toHaveAttribute('loading', 'lazy');
  });
});

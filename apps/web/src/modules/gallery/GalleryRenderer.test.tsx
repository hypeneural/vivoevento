import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { GalleryRenderer } from './components/GalleryRenderer';
import type { ApiEventMediaItem } from '@/lib/api-types';
import { galleryExperienceFixtures } from './gallery-builder';

vi.mock('photoswipe/lightbox', () => ({
  default: class PhotoSwipeLightboxMock {
    init = vi.fn();
    destroy = vi.fn();
    loadAndOpen = vi.fn();
    on = vi.fn();
  },
}));

function mediaItem(overrides: Partial<ApiEventMediaItem>): ApiEventMediaItem {
  return {
    id: 1,
    event_id: 10,
    media_type: 'image',
    channel: 'upload',
    status: 'published',
    processing_status: null,
    moderation_status: 'approved',
    publication_status: 'published',
    sender_name: 'Convidado',
    caption: 'Foto do evento',
    thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
    preview_url: 'https://cdn.eventovivo.test/preview.webp',
    original_url: 'https://cdn.eventovivo.test/original.jpg',
    created_at: null,
    published_at: null,
    is_featured: false,
    width: 1200,
    height: 800,
    orientation: 'landscape',
    responsive_sources: {
      sizes: '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw',
      srcset: 'https://cdn.eventovivo.test/thumb.webp 320w, https://cdn.eventovivo.test/preview.webp 1200w',
      variants: [
        {
          variant_key: 'thumb',
          src: 'https://cdn.eventovivo.test/thumb.webp',
          width: 320,
          height: 213,
          mime_type: 'image/webp',
        },
        {
          variant_key: 'gallery',
          src: 'https://cdn.eventovivo.test/preview.webp',
          width: 1200,
          height: 800,
          mime_type: 'image/webp',
        },
      ],
    },
    ...overrides,
  };
}

describe('GalleryRenderer', () => {
  it('renders photos through the dedicated gallery renderer with responsive sources', () => {
    render(
      <GalleryRenderer
        media={[mediaItem({ id: 1, caption: 'Entrada dos noivos' })]}
        experience={galleryExperienceFixtures.weddingPremiumLight}
      />,
    );

    const image = screen.getByRole('img', { name: 'Entrada dos noivos' });
    expect(image).toHaveAttribute('src', 'https://cdn.eventovivo.test/preview.webp');
    expect(image).toHaveAttribute('srcset');
    expect(image).toHaveAttribute('sizes');
  });

  it('keeps first-band media eager and below-fold media lazy', () => {
    render(
      <GalleryRenderer
        media={Array.from({ length: 6 }, (_, index) => mediaItem({
          id: index + 1,
          caption: `Foto ${index + 1}`,
        }))}
        experience={galleryExperienceFixtures.weddingPremiumLight}
      />,
    );

    expect(screen.getByRole('img', { name: 'Foto 1' })).toHaveAttribute('loading', 'eager');
    expect(screen.getByRole('img', { name: 'Foto 6' })).toHaveAttribute('loading', 'lazy');
  });

  it('opens videos in the dedicated video modal instead of the photo lightbox', async () => {
    const user = userEvent.setup();
    render(
      <GalleryRenderer
        media={[
          mediaItem({
            id: 7,
            media_type: 'video',
            mime_type: 'video/mp4',
            caption: 'Video da pista',
            thumbnail_url: 'https://cdn.eventovivo.test/poster.webp',
            preview_url: 'https://cdn.eventovivo.test/video.mp4',
          }),
        ]}
        experience={galleryExperienceFixtures.weddingPremiumLight}
      />,
    );

    expect(screen.getByText('Video')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: /abrir video da pista/i }));

    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(screen.getByLabelText('Player do video')).toHaveAttribute('poster', 'https://cdn.eventovivo.test/poster.webp');
  });
});

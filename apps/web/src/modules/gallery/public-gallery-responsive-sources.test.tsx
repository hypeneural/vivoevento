import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicGalleryPage from './PublicGalleryPage';
import { galleryExperienceFixtures } from './gallery-builder';

const getPublicGalleryMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getPublicGallery: (...args: unknown[]) => getPublicGalleryMock(...args),
}));

vi.mock('photoswipe/lightbox', () => ({
  default: class PhotoSwipeLightboxMock {
    init = vi.fn();
    destroy = vi.fn();
    loadAndOpen = vi.fn();
    on = vi.fn();
  },
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/e/casamento/gallery']}>
        <Routes>
          <Route path="/e/:slug/gallery" element={<PublicGalleryPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicGalleryPage responsive sources', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uses the public responsive sources contract when rendering media', async () => {
    getPublicGalleryMock.mockResolvedValue({
      event: {
        id: 1,
        title: 'Casamento Ana e Leo',
        slug: 'casamento',
        event_type: 'wedding',
        branding: {
          logo_url: null,
          cover_image_url: null,
          primary_color: '#123456',
          secondary_color: '#abcdef',
        },
      },
      experience: galleryExperienceFixtures.weddingPremiumLight,
      data: [
        {
          id: 10,
          event_id: 1,
          media_type: 'image',
          channel: 'upload',
          status: 'published',
          processing_status: null,
          moderation_status: 'approved',
          publication_status: 'published',
          sender_name: 'Convidado',
          caption: 'Entrada dos noivos',
          thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
          preview_url: 'https://cdn.eventovivo.test/gallery.webp',
          original_url: null,
          created_at: null,
          published_at: null,
          is_featured: false,
          width: 1200,
          height: 800,
          orientation: 'landscape',
          responsive_sources: {
            sizes: '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw',
            srcset: 'https://cdn.eventovivo.test/thumb.webp 320w, https://cdn.eventovivo.test/gallery.webp 1200w',
            variants: [
              {
                variant_key: 'thumb',
                src: 'https://cdn.eventovivo.test/thumb.webp',
                width: 320,
                height: 213,
                mime_type: 'image/webp',
              },
            ],
          },
        },
      ],
      meta: {
        page: 1,
        per_page: 30,
        total: 1,
        last_page: 1,
        request_id: 'req_test',
        face_search: {
          public_search_enabled: false,
          find_me_url: null,
        },
      },
    });

    renderPage();

    expect(await screen.findByText('Casamento Ana e Leo')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Entrada dos noivos' })).toHaveAttribute('srcset');
    expect(screen.getByRole('img', { name: 'Entrada dos noivos' })).toHaveAttribute('sizes');
  });
});

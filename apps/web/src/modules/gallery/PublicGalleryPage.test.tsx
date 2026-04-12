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

function renderPage(initialEntry = '/e/casamento/gallery') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/e/:slug/gallery" element={<PublicGalleryPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicGalleryPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders public gallery thumbnails with lazy async image loading', async () => {
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
          thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
          preview_url: 'https://cdn.eventovivo.test/gallery.webp',
          original_url: 'https://cdn.eventovivo.test/original.jpg',
          caption: 'Noiva entrando na festa',
          sender_name: 'Convidado',
          created_at: null,
          published_at: null,
          is_featured: false,
          width: 1200,
          height: 800,
          orientation: 'landscape',
        },
      ],
      meta: {
        page: 1,
        per_page: 30,
        total: 1,
        last_page: 1,
        request_id: 'req_test',
        face_search: {
          public_search_enabled: true,
          find_me_url: 'https://eventovivo.test/e/casamento/find-me',
        },
      },
    });

    renderPage();

    expect(await screen.findByText('1 foto(s) publicadas para este evento.')).toBeInTheDocument();
    expect(getPublicGalleryMock).toHaveBeenCalledWith('casamento');
    expect(screen.getByRole('link', { name: /encontrar minhas fotos/i })).toHaveAttribute(
      'href',
      'https://eventovivo.test/e/casamento/find-me',
    );

    const image = screen.getByRole('img', { name: 'Noiva entrando na festa' });
    expect(image).toHaveAttribute('src', 'https://cdn.eventovivo.test/gallery.webp');
    expect(image).toHaveAttribute('loading', 'eager');
    expect(image).toHaveAttribute('decoding', 'async');
  });

  it('keeps the public gallery empty state explicit', async () => {
    getPublicGalleryMock.mockResolvedValue({
      event: null,
      experience: galleryExperienceFixtures.weddingPremiumLight,
      data: [],
      meta: {
        page: 1,
        per_page: 30,
        total: 0,
        last_page: 1,
        request_id: 'req_test',
        face_search: {
          public_search_enabled: false,
          find_me_url: null,
        },
      },
    });

    renderPage();

    expect(await screen.findByText('Ainda nao existem imagens publicadas para esta galeria.')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /encontrar minhas fotos/i })).not.toBeInTheDocument();
  });
});

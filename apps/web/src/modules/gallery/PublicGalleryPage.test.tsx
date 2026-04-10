import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicGalleryPage from './PublicGalleryPage';

const getPublicGalleryMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getPublicGallery: (...args: unknown[]) => getPublicGalleryMock(...args),
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
      data: [
        {
          id: 10,
          thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
          preview_url: 'https://cdn.eventovivo.test/gallery.webp',
          original_url: 'https://cdn.eventovivo.test/original.jpg',
          caption: 'Noiva entrando na festa',
          sender_name: 'Convidado',
        },
      ],
      meta: {
        total: 1,
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
    expect(image).toHaveAttribute('src', 'https://cdn.eventovivo.test/thumb.webp');
    expect(image).toHaveAttribute('loading', 'lazy');
    expect(image).toHaveAttribute('decoding', 'async');
  });

  it('keeps the public gallery empty state explicit', async () => {
    getPublicGalleryMock.mockResolvedValue({
      data: [],
      meta: {
        total: 0,
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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicFaceSearchPage from './PublicFaceSearchPage';

const getPublicFaceSearchBootstrapMock = vi.fn();
const searchPublicEventFacesMock = vi.fn();
const toastMock = vi.fn();

vi.mock('./api', () => ({
  getPublicFaceSearchBootstrap: (...args: unknown[]) => getPublicFaceSearchBootstrapMock(...args),
  searchPublicEventFaces: (...args: unknown[]) => searchPublicEventFacesMock(...args),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

function renderPage(initialEntry = '/e/casamento/find-me') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/e/:slug/find-me" element={<PublicFaceSearchPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicFaceSearchPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the public search page and sends the selfie search with the event slug and consent version', async () => {
    getPublicFaceSearchBootstrapMock.mockResolvedValue({
      event: {
        id: 42,
        title: 'Casamento Ana e Bruno',
        slug: 'casamento',
        cover_image_path: null,
        cover_image_url: null,
        logo_path: null,
        logo_url: null,
        primary_color: '#0f766e',
        secondary_color: '#0f172a',
        starts_at: null,
        location_name: null,
      },
      search: {
        enabled: true,
        status: 'available',
        reason: null,
        message: 'Envie uma selfie para localizar suas fotos publicadas.',
        instructions: 'Use uma selfie nitida com apenas uma pessoa visivel.',
        consent_required: true,
        consent_version: 'v1',
        selfie_retention_hours: 24,
        top_k: 20,
      },
      links: {
        find_me_url: 'https://eventovivo.test/e/casamento/find-me',
        find_me_api_url: 'https://api.eventovivo.test/public/events/casamento/face-search/search',
        gallery_url: 'https://eventovivo.test/e/casamento/gallery',
        hub_url: 'https://eventovivo.test/e/casamento',
      },
    });

    searchPublicEventFacesMock.mockResolvedValue({
      request: {
        id: 15,
        event_id: 42,
        requester_type: 'guest',
        requester_user_id: null,
        status: 'completed',
        consent_version: 'v1',
        selfie_storage_strategy: 'memory_only',
        faces_detected: 1,
        query_face_quality_score: 0.91,
        top_k: 20,
        best_distance: 0.13,
        result_photo_ids: [501],
        created_at: '2026-04-09T20:20:00Z',
        expires_at: '2026-04-10T20:20:00Z',
      },
      total_results: 1,
      results: [
        {
          rank: 1,
          event_media_id: 501,
          best_distance: 0.13,
          best_quality_score: 0.89,
          best_face_area_ratio: 0.24,
          matched_face_ids: [7001],
          media: {
            id: 501,
            event_id: 42,
            event_title: 'Casamento Ana e Bruno',
            thumbnail_url: 'https://cdn.eventovivo.test/media-501-thumb.jpg',
            preview_url: null,
            original_url: null,
            caption: 'Entrada dos noivos',
            sender_name: 'Convidado',
            publication_status: 'published',
            moderation_status: 'approved',
          },
        },
      ],
    });

    renderPage();

    expect(await screen.findByText(/casamento ana e bruno/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /abrir galeria publica/i })).toHaveAttribute(
      'href',
      'https://eventovivo.test/e/casamento/gallery',
    );

    const input = screen.getByTestId('face-search-file-input') as HTMLInputElement;
    const selfie = new File(['selfie'], 'guest-selfie.jpg', { type: 'image/jpeg' });

    fireEvent.change(input, {
      target: {
        files: [selfie],
      },
    });

    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(screen.getByRole('button', { name: /buscar minhas fotos/i }));

    await waitFor(() => {
      expect(searchPublicEventFacesMock).toHaveBeenCalledWith('casamento', selfie, 'v1');
    });

    expect(await screen.findByText(/entrada dos noivos/i)).toBeInTheDocument();
    expect(screen.getByText(/distancia 0.130/i)).toBeInTheDocument();
  });
});

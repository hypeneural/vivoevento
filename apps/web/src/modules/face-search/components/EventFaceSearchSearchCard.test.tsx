import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ComponentProps } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EventFaceSearchSearchCard } from './EventFaceSearchSearchCard';

const searchEventFacesMock = vi.fn();
const toastMock = vi.fn();

vi.mock('../api', () => ({
  searchEventFaces: (...args: unknown[]) => searchEventFacesMock(...args),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

function renderCard(props: Partial<ComponentProps<typeof EventFaceSearchSearchCard>> = {}) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventFaceSearchSearchCard
        eventId={42}
        enabled
        publicSearchUrl="https://eventovivo.test/e/casamento/find-me"
        {...props}
      />
    </QueryClientProvider>,
  );
}

describe('EventFaceSearchSearchCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the internal search surface and keeps the disabled guidance explicit', () => {
    renderCard({ enabled: false });

    expect(screen.getByText(/buscar fotos de uma pessoa/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /abrir link publico/i })).toHaveAttribute(
      'href',
      'https://eventovivo.test/e/casamento/find-me',
    );
    expect(screen.getByText(/ative o reconhecimento facial do evento para liberar esta busca/i)).toBeInTheDocument();
  });

  it('submits the selfie search to the event endpoint and renders the returned match', async () => {
    searchEventFacesMock.mockResolvedValue({
      request: {
        id: 9,
        event_id: 42,
        requester_type: 'user',
        requester_user_id: 5,
        status: 'completed',
        consent_version: null,
        selfie_storage_strategy: 'memory_only',
        faces_detected: 1,
        query_face_quality_score: 0.93,
        top_k: 50,
        best_distance: 0.11,
        result_photo_ids: [101],
        created_at: '2026-04-09T20:00:00Z',
        expires_at: null,
      },
      total_results: 1,
      results: [
        {
          rank: 1,
          event_media_id: 101,
          best_distance: 0.11,
          best_quality_score: 0.94,
          best_face_area_ratio: 0.22,
          matched_face_ids: [1001],
          media: {
            id: 101,
            event_id: 42,
            event_title: 'Casamento',
            thumbnail_url: 'https://cdn.eventovivo.test/media-101-thumb.jpg',
            preview_url: null,
            original_url: null,
            caption: 'Noiva sorrindo',
            sender_name: 'Convidado',
            publication_status: 'published',
            moderation_status: 'approved',
          },
        },
      ],
    });

    renderCard();

    const input = screen.getByTestId('face-search-file-input') as HTMLInputElement;
    const selfie = new File(['selfie'], 'selfie.jpg', { type: 'image/jpeg' });

    fireEvent.change(input, {
      target: {
        files: [selfie],
      },
    });

    fireEvent.click(screen.getByRole('button', { name: /buscar no evento/i }));

    await waitFor(() => {
      expect(searchEventFacesMock).toHaveBeenCalledWith(42, selfie, true);
    });

    expect(await screen.findByText(/noiva sorrindo/i)).toBeInTheDocument();
    expect(screen.getByText(/distancia 0.110/i)).toBeInTheDocument();
  });
});

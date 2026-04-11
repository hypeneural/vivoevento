import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MediaPage from './MediaPage';
import { mediaService } from './services/media.service';
import { eventPeopleApi } from '@/modules/event-people/api';
import { eventsService } from '@/modules/events/services/events.service';

vi.mock('./services/media.service', () => ({
  mediaService: {
    list: vi.fn(),
    show: vi.fn(),
  },
}));

vi.mock('@/modules/event-people/api', () => ({
  eventPeopleApi: {
    listReviewQueue: vi.fn(),
    listPeople: vi.fn(),
    listMediaFaces: vi.fn(),
    confirmReviewItem: vi.fn(),
    ignoreReviewItem: vi.fn(),
    splitReviewItem: vi.fn(),
    mergeReviewItem: vi.fn(),
  },
}));

vi.mock('@/modules/events/services/events.service', () => ({
  eventsService: {
    list: vi.fn(),
  },
}));

const emptyMediaPage = {
  data: [],
  meta: {
    page: 1,
    last_page: 1,
    total: 0,
    stats: {
      total: 0,
      images: 0,
      videos: 0,
      pending: 0,
      published: 0,
      featured: 0,
      pinned: 0,
      duplicates: 0,
      face_indexed: 0,
    },
  },
};

function renderMediaPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
        <MediaPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('MediaPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(mediaService.list).mockResolvedValue(emptyMediaPage);
    vi.mocked(eventsService.list).mockResolvedValue({
      data: [
        { id: 42, title: 'Evento Reconhecimento' },
      ],
      meta: { page: 1, last_page: 1, total: 1 },
    });
    vi.mocked(eventPeopleApi.listReviewQueue).mockResolvedValue({
      success: true,
      data: [
        {
          id: 88,
          event_id: 42,
          queue_key: 'unknown-face:88',
          type: 'unknown_person',
          status: 'pending',
          priority: 120,
          event_person_id: null,
          event_media_face_id: 901,
          payload: {
            question: 'Quem e esta pessoa?',
            event_media_id: 321,
          },
          last_signal_at: '2026-04-11T10:00:00Z',
          resolved_at: null,
          face: {
            id: 901,
            event_media_id: 321,
            face_index: 0,
            bbox: { x: 0.1, y: 0.1, w: 0.2, h: 0.2 },
          },
        },
      ],
      meta: { page: 1, per_page: 24, last_page: 1, total: 1 },
    });
    vi.mocked(eventPeopleApi.listMediaFaces).mockResolvedValue([]);
  });

  it('offers a simple face-search entry point from the media catalog', async () => {
    renderMediaPage();

    fireEvent.click(await screen.findByRole('button', { name: /buscar pessoa por foto/i }));

    expect(screen.getByRole('dialog', { name: /buscar pessoa por foto/i })).toBeInTheDocument();
    expect(await screen.findByText(/buscando dentro de: Evento Reconhecimento/i)).toBeInTheDocument();
    expect(screen.getByText(/envie uma selfie nitida/i)).toBeInTheDocument();
  });

  it('renders the event-people inbox on the media catalog surface', async () => {
    renderMediaPage();

    expect(await screen.findByText(/Organizar pessoas do evento/i)).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: /Quem e esta pessoa\?/i })).toBeInTheDocument();
    expect(await screen.findByText(/Midia #321/i)).toBeInTheDocument();
  });
});

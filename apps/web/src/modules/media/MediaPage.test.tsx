import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MediaPage from './MediaPage';
import { mediaService } from './services/media.service';
import { eventsService } from '@/modules/events/services/events.service';

vi.mock('./services/media.service', () => ({
  mediaService: {
    list: vi.fn(),
    show: vi.fn(),
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
  });

  it('offers a simple face-search entry point from the media catalog', async () => {
    renderMediaPage();

    fireEvent.click(await screen.findByRole('button', { name: /buscar pessoa por foto/i }));

    expect(screen.getByRole('dialog', { name: /buscar pessoa por foto/i })).toBeInTheDocument();
    expect(await screen.findByText(/buscando dentro de: Evento Reconhecimento/i)).toBeInTheDocument();
    expect(screen.getByText(/envie uma selfie nitida/i)).toBeInTheDocument();
  });
});

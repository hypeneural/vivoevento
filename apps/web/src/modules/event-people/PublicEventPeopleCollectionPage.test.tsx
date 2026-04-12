import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicEventPeopleCollectionPage from './PublicEventPeopleCollectionPage';
import { eventPeopleApi } from './api';

vi.mock('./api', () => ({
  eventPeopleApi: {
    getPublicRelationalCollection: vi.fn(),
  },
}));

function renderPage(initialEntry = '/momentos/tok_123') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
        <Routes>
          <Route path="/momentos/:token" element={<PublicEventPeopleCollectionPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicEventPeopleCollectionPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a public delivery page from the secure token', async () => {
    vi.mocked(eventPeopleApi.getPublicRelationalCollection).mockResolvedValue({
      event: {
        id: 42,
        title: 'Casamento Ana e Pedro',
        slug: 'casamento-ana-pedro',
        event_type: 'wedding',
        starts_at: null,
        location_name: 'Sao Paulo',
        public_gallery_url: 'https://eventovivo.test/e/casamento-ana-pedro/gallery',
        public_hub_url: 'https://eventovivo.test/e/casamento-ana-pedro',
      },
      collection: {
        id: 1,
        collection_key: 'must-have:couple_portrait',
        collection_type: 'must_have_delivery',
        display_name: 'Casal junto',
        metadata: {},
        item_count: 1,
        person_a: null,
        person_b: null,
        group: { id: 9, display_name: 'Casal', slug: 'couple', group_type: 'principal' },
        items: [{
          id: 11,
          event_media_id: 77,
          sort_order: 0,
          match_score: 126,
          matched_people_count: 2,
          is_published: true,
          media: {
            id: 77,
            caption: 'Casal no altar',
            preview_url: 'https://cdn.eventovivo.test/gallery.webp',
            thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
            original_url: 'https://cdn.eventovivo.test/original.jpg',
            publication_status: 'published',
            moderation_status: 'approved',
            created_at: null,
          },
        }],
      },
    });

    renderPage();

    expect(await screen.findByText('Casal junto')).toBeInTheDocument();
    expect(screen.getByText('Casamento Ana e Pedro')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Abrir galeria do evento' })).toHaveAttribute(
      'href',
      'https://eventovivo.test/e/casamento-ana-pedro/gallery',
    );
    expect(screen.getByRole('img', { name: 'Casal no altar' })).toHaveAttribute(
      'src',
      'https://cdn.eventovivo.test/gallery.webp',
    );
  });

  it('keeps the public unavailable state explicit', async () => {
    vi.mocked(eventPeopleApi.getPublicRelationalCollection).mockRejectedValue(new Error('gone'));

    renderPage();

    expect(await screen.findByText('Entrega indisponivel')).toBeInTheDocument();
  });
});

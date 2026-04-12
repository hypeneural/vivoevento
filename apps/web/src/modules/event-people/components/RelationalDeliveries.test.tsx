import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EventPeopleRelationalCollectionsPanel } from './EventPeopleRelationalCollectionsPanel';
import { eventPeopleApi } from '../api';

vi.mock('../api', () => ({
  eventPeopleApi: {
    getRelationalCollections: vi.fn(),
    refreshRelationalCollections: vi.fn(),
  },
}));

function buildResponse() {
  return {
    summary: {
      total_collections: 5,
      public_ready_collections: 2,
      internal_collections: 3,
      must_have_deliveries: 2,
      last_generated_at: '2026-04-12T18:00:00Z',
    },
    collections: [
      {
        id: 1,
        collection_key: 'must-have:couple_portrait',
        collection_type: 'must_have_delivery',
        source_type: 'coverage_target',
        display_name: 'Casal junto',
        status: 'active',
        visibility: 'public_ready',
        share_token: 'tok_123',
        public_url: 'https://eventovivo.test/momentos/tok_123',
        public_api_url: 'https://api.eventovivo.test/api/v1/public/people-collections/tok_123',
        item_count: 1,
        published_item_count: 1,
        person_a: null,
        person_b: null,
        group: { id: 10, display_name: 'Casal', slug: 'couple', group_type: 'principal' },
        metadata: { target_key: 'couple_portrait' },
        generated_at: '2026-04-12T18:00:00Z',
        published_at: '2026-04-12T18:00:00Z',
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
            preview_url: null,
            thumbnail_url: null,
            original_url: null,
            publication_status: 'published',
            moderation_status: 'approved',
            created_at: '2026-04-12T17:00:00Z',
          },
        }],
      },
      {
        id: 2,
        collection_key: 'pair-best-of:7:8',
        collection_type: 'pair_best_of',
        source_type: 'relation',
        display_name: 'Noiva + Noivo',
        status: 'active',
        visibility: 'internal',
        share_token: null,
        public_url: null,
        public_api_url: null,
        item_count: 2,
        published_item_count: 1,
        person_a: { id: 7, display_name: 'Noiva', type: 'bride' },
        person_b: { id: 8, display_name: 'Noivo', type: 'groom' },
        group: null,
        metadata: { relation_type: 'spouse_of' },
        generated_at: '2026-04-12T18:00:00Z',
        published_at: null,
        items: [],
      },
      {
        id: 3,
        collection_key: 'person-best-of:7',
        collection_type: 'person_best_of',
        source_type: 'person',
        display_name: 'Melhores de Noiva',
        status: 'active',
        visibility: 'internal',
        share_token: null,
        public_url: null,
        public_api_url: null,
        item_count: 1,
        published_item_count: 1,
        person_a: { id: 7, display_name: 'Noiva', type: 'bride' },
        person_b: null,
        group: null,
        metadata: {},
        generated_at: '2026-04-12T18:00:00Z',
        published_at: null,
        items: [],
      },
    ],
  };
}

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventPeopleRelationalCollectionsPanel
        eventId="42"
        selectedPerson={{
          id: 7,
          event_id: 42,
          display_name: 'Noiva',
          slug: 'noiva',
          type: 'bride',
          side: 'neutral',
          avatar_media_id: null,
          avatar_face_id: null,
          importance_rank: 100,
          notes: null,
          status: 'active',
          created_at: null,
          updated_at: null,
        }}
      />
    </QueryClientProvider>,
  );
}

describe('EventPeopleRelationalCollectionsPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders ready deliveries and collections linked to the selected person', async () => {
    vi.mocked(eventPeopleApi.getRelationalCollections).mockResolvedValue(buildResponse());

    renderPanel();

    expect(await screen.findByText('Momentos e entregas')).toBeInTheDocument();
    expect(await screen.findByText('Casal junto')).toBeInTheDocument();
    expect(await screen.findByText('Noiva + Noivo')).toBeInTheDocument();
    expect(await screen.findByText('Melhores de Noiva')).toBeInTheDocument();
    expect(screen.getByText('Colecoes ligadas a Noiva')).toBeInTheDocument();
    expect(screen.getAllByText('Publico pronto')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Abrir entrega publica' })).toHaveAttribute(
      'href',
      'https://eventovivo.test/momentos/tok_123',
    );
  });

  it('refreshes relational deliveries on demand', async () => {
    vi.mocked(eventPeopleApi.getRelationalCollections).mockResolvedValue(buildResponse());
    vi.mocked(eventPeopleApi.refreshRelationalCollections).mockResolvedValue(buildResponse());

    renderPanel();

    fireEvent.click(await screen.findByRole('button', { name: 'Gerar agora' }));

    await waitFor(() => {
      expect(eventPeopleApi.refreshRelationalCollections).toHaveBeenCalledWith('42');
    });
  });
});

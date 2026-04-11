import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventPeoplePage from './EventPeoplePage';
import { eventPeopleApi } from './api';

const getEventDetailMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
}));

vi.mock('./api', () => ({
  eventPeopleApi: {
    listPeople: vi.fn(),
    getPerson: vi.fn(),
    createPerson: vi.fn(),
    updatePerson: vi.fn(),
    getPresets: vi.fn(),
    createRelation: vi.fn(),
    updateRelation: vi.fn(),
    deleteRelation: vi.fn(),
  },
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

function renderPage(initialEntry = '/events/42/people') {
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
          <Route path="/events/:id/people" element={<EventPeoplePage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function buildPerson(overrides: Record<string, unknown> = {}) {
  return {
    id: 7,
    event_id: 42,
    display_name: 'Mae da noiva',
    slug: 'mae-da-noiva',
    type: 'mother',
    side: 'bride_side',
    avatar_media_id: null,
    avatar_face_id: null,
    importance_rank: 90,
    notes: 'Pessoa importante',
    status: 'active',
    stats: [{
      media_count: 6,
      solo_media_count: 2,
      with_others_media_count: 4,
      published_media_count: 5,
      pending_media_count: 1,
    }],
    representative_faces: [{
      id: 1,
      event_media_face_id: 99,
      rank_score: 98.1,
      quality_score: 0.93,
      pose_bucket: 'center-level',
      context_hash: 'ctx-1',
      sync_status: 'synced',
      last_synced_at: '2026-04-11T12:00:00Z',
      sync_payload: null,
      face: {
        id: 99,
        event_media_id: 88,
        face_index: 0,
        quality_score: 0.93,
        quality_tier: 'search_priority',
      },
    }],
    relations: [{
      id: 12,
      event_id: 42,
      person_pair_key: '7:8',
      relation_type: 'spouse_of',
      directionality: 'undirected',
      source: 'manual',
      confidence: null,
      strength: null,
      is_primary: true,
      notes: 'Casal principal',
      other_person: {
        id: 8,
        display_name: 'Noivo',
        type: 'groom',
        side: 'neutral',
        status: 'active',
      },
    }],
    created_at: '2026-04-11T12:00:00Z',
    updated_at: '2026-04-11T12:30:00Z',
    ...overrides,
  };
}

describe('EventPeoplePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    getEventDetailMock.mockResolvedValue({
      id: 42,
      title: 'Casamento Ana e Pedro',
    });

    vi.mocked(eventPeopleApi.listPeople).mockImplementation(async () => ({
      success: true,
      data: [buildPerson()],
      meta: { page: 1, per_page: 100, total: 1, last_page: 1 },
    }));

    vi.mocked(eventPeopleApi.getPerson).mockResolvedValue(buildPerson());
    vi.mocked(eventPeopleApi.getPresets).mockResolvedValue({
      event_type: 'wedding',
      people: [{ key: 'bride', label: 'Noiva', type: 'bride', side: 'neutral', importance_rank: 100 }],
      relations: [{ type: 'spouse_of', label: 'Conjuge de', directionality: 'undirected' }],
    });
    vi.mocked(eventPeopleApi.createPerson).mockResolvedValue(buildPerson({ id: 99, display_name: 'Cerimonialista' }));
    vi.mocked(eventPeopleApi.createRelation).mockResolvedValue({} as never);
    vi.mocked(eventPeopleApi.deleteRelation).mockResolvedValue(undefined);
  });

  it('renders the dedicated people workspace with local relations and representative sync state', async () => {
    renderPage();

    expect(await screen.findByText('Pessoas de Casamento Ana e Pedro')).toBeInTheDocument();
    expect(screen.getByText('Mae da noiva')).toBeInTheDocument();
    expect(await screen.findByText('Conjuge de')).toBeInTheDocument();
    expect(screen.getAllByText('synced').length).toBeGreaterThan(0);
    expect(screen.getByText('Casal principal')).toBeInTheDocument();
  });

  it('creates a person manually from the dedicated page without using the guided review flow', async () => {
    renderPage();

    expect(await screen.findByText('Pessoas de Casamento Ana e Pedro')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^Nova pessoa$/i }));
    fireEvent.change(screen.getByLabelText('Nome'), { target: { value: 'Cerimonialista' } });
    fireEvent.click(screen.getByRole('button', { name: /criar pessoa/i }));

    await waitFor(() => {
      expect(eventPeopleApi.createPerson).toHaveBeenCalledWith('42', expect.objectContaining({
        display_name: 'Cerimonialista',
      }));
    });
  });
});

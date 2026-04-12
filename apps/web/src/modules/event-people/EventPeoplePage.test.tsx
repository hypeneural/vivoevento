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
    getGraph: vi.fn(),
    createPerson: vi.fn(),
    updatePerson: vi.fn(),
    getPresets: vi.fn(),
    getCoverage: vi.fn(),
    refreshCoverage: vi.fn(),
    getRelationalCollections: vi.fn(),
    refreshRelationalCollections: vi.fn(),
    listGroups: vi.fn(),
    createGroup: vi.fn(),
    updateGroup: vi.fn(),
    deleteGroup: vi.fn(),
    applyPresetGroups: vi.fn(),
    addGroupMember: vi.fn(),
    removeGroupMember: vi.fn(),
    getOperationalStatus: vi.fn(),
    listReferencePhotoCandidates: vi.fn(),
    addGalleryReferencePhoto: vi.fn(),
    uploadReferencePhoto: vi.fn(),
    setPrimaryReferencePhoto: vi.fn(),
    createRelation: vi.fn(),
    updateRelation: vi.fn(),
    deleteRelation: vi.fn(),
  },
}));

vi.mock('./components/EventPeopleGraphView', () => ({
  EventPeopleGraphView: ({
    graph,
    onOpenPerson,
  }: {
    graph: { stats?: { people_count?: number } } | null;
    onOpenPerson?: (personId: number) => void;
  }) => (
    <div data-testid="event-people-graph-view">
      <p>Mapa carregado com {graph?.stats?.people_count ?? 0} pessoas</p>
      <button type="button" onClick={() => onOpenPerson?.(7)}>
        Abrir pessoa do mapa
      </button>
    </div>
  ),
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
      <MemoryRouter initialEntries={[initialEntry]} future={{ v7_relativeSplatPath: true, v7_startTransition: true }}>
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
    avatar: {
      media_id: 88,
      face_id: 99,
    },
    importance_rank: 90,
    notes: 'Pessoa importante',
    status: 'active',
    primary_photo: {
      reference_photo_id: 41,
      selection_mode: 'manual',
      source: 'event_face',
      media_id: 88,
      event_media_id: 88,
      event_media_face_id: 99,
      reference_upload_media_id: null,
      best_media_id: 88,
      latest_media_id: 88,
    },
    stats: [{
      media_count: 6,
      solo_media_count: 2,
      with_others_media_count: 4,
      published_media_count: 5,
      pending_media_count: 1,
    }],
    reference_photos: [{
      id: 41,
      source: 'event_face',
      event_media_id: 88,
      event_media_face_id: 99,
      reference_upload_media_id: null,
      purpose: 'both',
      status: 'active',
      quality_score: 0.93,
      is_primary_avatar: true,
      upload_media: null,
      face: {
        id: 99,
        event_media_id: 88,
        face_index: 0,
        quality_score: 0.93,
        quality_tier: 'search_priority',
      },
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
    vi.mocked(eventPeopleApi.getGraph).mockResolvedValue({
      people: [
        {
          id: 7,
          display_name: 'Mae da noiva',
          role_key: null,
          role_label: 'Mae da noiva',
          role_family: 'familia',
          type: 'mother',
          side: 'bride_side',
          status: 'active',
          avatar_url: null,
          importance_rank: 90,
          media_count: 6,
          published_media_count: 5,
          has_primary_photo: true,
        },
      ],
      relations: [],
      groups: [],
      stats: {
        people_count: 1,
        relation_count: 0,
        connected_people_count: 0,
        principal_people_count: 0,
        without_primary_photo_count: 0,
      },
      filters: {
        statuses: ['active'],
        sides: ['bride_side'],
        role_families: ['familia'],
        relation_types: [],
      },
    });
    vi.mocked(eventPeopleApi.getPresets).mockResolvedValue({
      event_type: 'wedding',
      model_key: 'wedding',
      people: [{
        key: 'bride',
        label: 'Noiva',
        role_key: 'bride',
        role_label: 'Noiva',
        role_family: 'principal',
        type: 'bride',
        side: 'neutral',
        importance_rank: 100,
      }],
      relations: [{ type: 'spouse_of', label: 'Conjuge de', directionality: 'undirected' }],
      groups: [{ key: 'couple', label: 'Casal', role_family: 'principal', member_role_keys: ['bride'], importance_rank: 100 }],
      coverage_targets: [{ key: 'couple_portrait', label: 'Casal junto', target_type: 'group', role_keys: ['bride'], group_key: 'couple', priority: 100 }],
    });
    vi.mocked(eventPeopleApi.listGroups).mockResolvedValue([]);
    vi.mocked(eventPeopleApi.getCoverage).mockResolvedValue({
      summary: {
        missing: 1,
        weak: 0,
        ok: 0,
        strong: 0,
        active_alerts: 1,
        last_evaluated_at: null,
      },
      targets: [],
      alerts: [],
    });
    vi.mocked(eventPeopleApi.getRelationalCollections).mockResolvedValue({
      summary: {
        total_collections: 3,
        public_ready_collections: 1,
        internal_collections: 2,
        must_have_deliveries: 1,
        last_generated_at: '2026-04-12T18:00:00Z',
      },
      collections: [{
        id: 1,
        collection_key: 'pair-best-of:7:8',
        collection_type: 'pair_best_of',
        source_type: 'relation',
        display_name: 'Mae da noiva + Noivo',
        status: 'active',
        visibility: 'internal',
        share_token: null,
        public_url: null,
        public_api_url: null,
        item_count: 2,
        published_item_count: 1,
        person_a: { id: 7, display_name: 'Mae da noiva', type: 'mother' },
        person_b: { id: 8, display_name: 'Noivo', type: 'groom' },
        group: null,
        metadata: {},
        generated_at: '2026-04-12T18:00:00Z',
        published_at: null,
        items: [],
      }],
    });
    vi.mocked(eventPeopleApi.createGroup).mockRejectedValue(new Error('not used'));
    vi.mocked(eventPeopleApi.updateGroup).mockRejectedValue(new Error('not used'));
    vi.mocked(eventPeopleApi.deleteGroup).mockResolvedValue(undefined);
    vi.mocked(eventPeopleApi.applyPresetGroups).mockResolvedValue([]);
    vi.mocked(eventPeopleApi.addGroupMember).mockRejectedValue(new Error('not used'));
    vi.mocked(eventPeopleApi.removeGroupMember).mockResolvedValue(undefined);
    vi.mocked(eventPeopleApi.getOperationalStatus).mockResolvedValue({
      people_active: 1,
      people_draft: 0,
      assignments_confirmed: 4,
      review_queue_pending: 2,
      review_queue_conflict: 1,
      aws_sync_pending: 1,
      aws_sync_failed: 0,
    });
    vi.mocked(eventPeopleApi.listReferencePhotoCandidates).mockResolvedValue([]);
    vi.mocked(eventPeopleApi.createPerson).mockResolvedValue(buildPerson({ id: 99, display_name: 'Cerimonialista' }));
    vi.mocked(eventPeopleApi.createRelation).mockResolvedValue({} as never);
    vi.mocked(eventPeopleApi.deleteRelation).mockResolvedValue(undefined);
  });

  it('renders the dedicated people workspace with cockpit status and separated image semantics', async () => {
    renderPage();

    expect(await screen.findByText('Pessoas de Casamento Ana e Pedro')).toBeInTheDocument();
    expect(screen.getByText('Mae da noiva')).toBeInTheDocument();
    expect(await screen.findByText('Conjuge de')).toBeInTheDocument();
    expect(screen.getAllByText('Sincronizado').length).toBeGreaterThan(0);
    expect(screen.getByText('Casal principal')).toBeInTheDocument();
    expect(screen.getAllByText('Avatar do catalogo').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Foto principal').length).toBeGreaterThan(0);
    expect(screen.getByText('Fotos de referencia')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Escolher da galeria' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Enviar foto de referencia' })).toBeInTheDocument();
    expect(screen.getByText('Referencias tecnicas')).toBeInTheDocument();
    expect(screen.getByText('Revisoes pendentes')).toBeInTheDocument();
    expect(screen.getByText('Modelo do evento')).toBeInTheDocument();
    expect(screen.getByText('Grupos do evento')).toBeInTheDocument();
    expect(screen.getByText('Cobertura importante')).toBeInTheDocument();
    expect(screen.getByText('Momentos e entregas')).toBeInTheDocument();
    expect(screen.getByText('Pessoas principais')).toBeInTheDocument();
    expect(screen.getByText('1 grupos sementes e 1 alvos de cobertura preparados para as proximas fases.')).toBeInTheDocument();
  }, 15_000);

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

  it('switches to the complementary relations map and can reopen the selected person sheet from it', async () => {
    renderPage();

    expect(await screen.findByText('Pessoas de Casamento Ana e Pedro')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Mapa de relacoes' }));

    await waitFor(() => {
      expect(eventPeopleApi.getGraph).toHaveBeenCalledWith('42');
    });
    expect(await screen.findByTestId('event-people-graph-view')).toBeInTheDocument();
    expect(screen.getByText('Mapa carregado com 1 pessoas')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Abrir pessoa do mapa' }));

    await waitFor(() => {
      expect(screen.queryByTestId('event-people-graph-view')).not.toBeInTheDocument();
    });
    expect(screen.getByText('Cadastro e relacoes')).toBeInTheDocument();
    expect(screen.getAllByText('Mae da noiva').length).toBeGreaterThan(0);
  });
});

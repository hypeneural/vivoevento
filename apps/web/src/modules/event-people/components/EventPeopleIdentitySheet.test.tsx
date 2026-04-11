import type React from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { eventPeopleApi } from '../api';
import { EventPeopleIdentitySheet } from './EventPeopleIdentitySheet';

vi.mock('../api', () => ({
  eventPeopleApi: {
    listPeople: vi.fn(),
  },
}));

function renderIdentitySheet(props: Partial<React.ComponentProps<typeof EventPeopleIdentitySheet>> = {}) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventPeopleIdentitySheet
        open
        onOpenChange={vi.fn()}
        eventId={42}
        pendingAction={null}
        face={{
          id: 19,
          event_media_id: 321,
          face_index: 0,
          bbox: { x: 0.1, y: 0.1, w: 0.25, h: 0.3 },
          quality: { score: 0.95, tier: 'search_priority', rejection_reason: null },
          assignments: [],
          current_assignment: null,
          review_item: {
            id: 900,
            event_id: 42,
            queue_key: 'unknown-face:19',
            type: 'unknown_person',
            status: 'pending',
            priority: 120,
            event_person_id: null,
            event_media_face_id: 19,
            payload: { question: 'Quem e esta pessoa?' },
            last_signal_at: '2026-04-11T10:00:00Z',
            resolved_at: null,
          },
        }}
        onConfirmExisting={vi.fn()}
        onCreatePerson={vi.fn()}
        onIgnore={vi.fn()}
        onSplit={vi.fn()}
        onMerge={vi.fn()}
        {...props}
      />
    </QueryClientProvider>,
  );
}

describe('EventPeopleIdentitySheet', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(eventPeopleApi.listPeople).mockResolvedValue({
      success: true,
      data: [
        {
          id: 7,
          event_id: 42,
          display_name: 'Maria Silva',
          slug: 'maria-silva',
          type: 'guest',
          side: 'neutral',
          avatar_media_id: null,
          avatar_face_id: null,
          importance_rank: 0,
          notes: null,
          status: 'active',
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { page: 1, per_page: 12, total: 1, last_page: 1 },
    });
  });

  it('confirms an existing person from the local event search list', async () => {
    const onConfirmExisting = vi.fn();

    renderIdentitySheet({ onConfirmExisting });

    fireEvent.click(screen.getByRole('tab', { name: /Pessoa existente/i }));
    expect(await screen.findByLabelText(/Buscar pessoa no evento/i)).toBeInTheDocument();
    const personButton = await screen.findByRole('button', { name: /Maria Silva/i });
    fireEvent.click(personButton);
    fireEvent.click(screen.getByRole('button', { name: /Confirmar nessa pessoa/i }));

    expect(onConfirmExisting).toHaveBeenCalledWith(7);
  });

  it('creates a new person inline without leaving the guided flow', async () => {
    const onCreatePerson = vi.fn();

    renderIdentitySheet({ onCreatePerson });

    fireEvent.click(screen.getByRole('tab', { name: /Criar pessoa/i }));
    fireEvent.change(await screen.findByLabelText(/Nome da pessoa/i), { target: { value: 'Mae da Noiva' } });
    fireEvent.click(screen.getByRole('button', { name: /Criar pessoa e confirmar/i }));

    expect(onCreatePerson).toHaveBeenCalledWith({
      display_name: 'Mae da Noiva',
      type: 'guest',
      side: 'neutral',
    });
  });

  it('surfaces conflict candidates and allows merging locally', async () => {
    const onMerge = vi.fn();

    vi.mocked(eventPeopleApi.listPeople).mockResolvedValue({
      success: true,
      data: [
        {
          id: 7,
          event_id: 42,
          display_name: 'Maria Silva',
          slug: 'maria-silva',
          type: 'guest',
          side: 'neutral',
          avatar_media_id: null,
          avatar_face_id: null,
          importance_rank: 0,
          notes: null,
          status: 'active',
          created_at: null,
          updated_at: null,
        },
        {
          id: 8,
          event_id: 42,
          display_name: 'Maria de Souza',
          slug: 'maria-de-souza',
          type: 'guest',
          side: 'neutral',
          avatar_media_id: null,
          avatar_face_id: null,
          importance_rank: 0,
          notes: null,
          status: 'active',
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { page: 1, per_page: 12, total: 2, last_page: 1 },
    });

    renderIdentitySheet({
      onMerge,
      face: {
        id: 19,
        event_media_id: 321,
        face_index: 0,
        bbox: { x: 0.1, y: 0.1, w: 0.25, h: 0.3 },
        quality: { score: 0.95, tier: 'search_priority', rejection_reason: null },
        assignments: [],
        current_assignment: {
          id: 14,
          event_person_id: 8,
          event_media_face_id: 19,
          source: 'manual_confirmed',
          confidence: 1,
          status: 'confirmed',
          reviewed_at: null,
          person: {
            id: 8,
            event_id: 42,
            display_name: 'Maria de Souza',
            slug: 'maria-de-souza',
            type: 'guest',
            side: 'neutral',
            avatar_media_id: null,
            avatar_face_id: null,
            importance_rank: 0,
            notes: null,
            status: 'active',
            created_at: null,
            updated_at: null,
          },
        },
        review_item: {
          id: 901,
          event_id: 42,
          queue_key: 'identity-conflict:19',
          type: 'identity_conflict',
          status: 'conflict',
          priority: 200,
          event_person_id: 8,
          event_media_face_id: 19,
          payload: {
            question: 'Essas pessoas representam a mesma identidade?',
            current_person_id: 8,
            candidate_people: [
              {
                id: 8,
                display_name: 'Maria de Souza',
                type: 'guest',
                side: 'neutral',
                status: 'active',
                assignment_status: 'confirmed',
                source: 'manual_confirmed',
              },
              {
                id: 7,
                display_name: 'Maria Silva',
                type: 'guest',
                side: 'neutral',
                status: 'active',
                assignment_status: 'rejected',
                source: 'manual_corrected',
              },
            ],
          },
          last_signal_at: '2026-04-11T10:00:00Z',
          resolved_at: null,
        },
      },
    });

    expect(await screen.findByText(/Identidades concorrentes para o mesmo rosto/i)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Mesclar pessoas/i }));

    expect(onMerge).toHaveBeenCalledWith(7, 8);
  });
});

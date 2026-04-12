import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EventPeopleGroupsPanel } from './EventPeopleGroupsPanel';
import { eventPeopleApi } from '../api';

vi.mock('../api', () => ({
  eventPeopleApi: {
    listGroups: vi.fn(),
    createGroup: vi.fn(),
    updateGroup: vi.fn(),
    deleteGroup: vi.fn(),
    applyPresetGroups: vi.fn(),
    addGroupMember: vi.fn(),
    removeGroupMember: vi.fn(),
  },
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

function renderPanel() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventPeopleGroupsPanel
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
          stats: [],
          reference_photos: [],
          representative_faces: [],
          relations: [],
        }}
        presetGroups={[
          {
            key: 'couple',
            label: 'Casal',
            role_family: 'principal',
            member_role_keys: ['bride', 'groom'],
            importance_rank: 100,
          },
        ]}
      />
    </QueryClientProvider>,
  );
}

describe('EventPeopleGroupsPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('creates a group manually inside the event people workspace', async () => {
    vi.mocked(eventPeopleApi.listGroups).mockResolvedValue([]);
    vi.mocked(eventPeopleApi.createGroup).mockResolvedValue({
      id: 90,
      event_id: 42,
      display_name: 'Familia da noiva',
      slug: 'familia-da-noiva',
      group_type: 'familia',
      side: 'bride_side',
      notes: null,
      importance_rank: 90,
      status: 'active',
      stats: {
        member_count: 0,
        people_with_primary_photo_count: 0,
        people_with_media_count: 0,
        media_count: 0,
        published_media_count: 0,
      },
      memberships: [],
      created_at: null,
      updated_at: null,
    });

    renderPanel();

    expect(await screen.findByText('Grupos do evento')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Nome do grupo'), { target: { value: 'Familia da noiva' } });
    fireEvent.change(screen.getByLabelText('Tipo do grupo'), { target: { value: 'familia' } });
    fireEvent.click(screen.getByRole('button', { name: 'Criar grupo' }));

    await waitFor(() => {
      expect(eventPeopleApi.createGroup).toHaveBeenCalledWith('42', expect.objectContaining({
        display_name: 'Familia da noiva',
        group_type: 'familia',
      }));
    });

    expect(await screen.findByText('Familia da noiva')).toBeInTheDocument();
  });

  it('adds the selected person to the current group and refreshes memberships', async () => {
    vi.mocked(eventPeopleApi.listGroups)
      .mockResolvedValueOnce([{
        id: 10,
        event_id: 42,
        display_name: 'Casal',
        slug: 'couple',
        group_type: 'principal',
        side: 'neutral',
        notes: null,
        importance_rank: 100,
        status: 'active',
        stats: {
          member_count: 0,
          people_with_primary_photo_count: 0,
          people_with_media_count: 0,
          media_count: 0,
          published_media_count: 0,
        },
        memberships: [],
        created_at: null,
        updated_at: null,
      }])
      .mockResolvedValueOnce([{
        id: 10,
        event_id: 42,
        display_name: 'Casal',
        slug: 'couple',
        group_type: 'principal',
        side: 'neutral',
        notes: null,
        importance_rank: 100,
        status: 'active',
        stats: {
          member_count: 1,
          people_with_primary_photo_count: 1,
          people_with_media_count: 0,
          media_count: 0,
          published_media_count: 0,
        },
        memberships: [{
          id: 501,
          event_person_group_id: 10,
          event_person_id: 7,
          role_label: null,
          source: 'manual',
          confidence: null,
          status: 'active',
          person: {
            id: 7,
            display_name: 'Noiva',
            type: 'bride',
            side: 'neutral',
            status: 'active',
            has_primary_photo: true,
          },
          created_at: null,
          updated_at: null,
        }],
        created_at: null,
        updated_at: null,
      }]);
    vi.mocked(eventPeopleApi.addGroupMember).mockResolvedValue({
      id: 501,
      event_person_group_id: 10,
      event_person_id: 7,
      role_label: null,
      source: 'manual',
      confidence: null,
      status: 'active',
      person: {
        id: 7,
        display_name: 'Noiva',
        type: 'bride',
        side: 'neutral',
        status: 'active',
        has_primary_photo: true,
      },
      created_at: null,
      updated_at: null,
    });

    renderPanel();

    expect(await screen.findByText('Casal')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Adicionar Noiva' }));

    await waitFor(() => {
      expect(eventPeopleApi.addGroupMember).toHaveBeenCalledWith('42', 10, { event_person_id: 7 });
    });

    expect(await screen.findByText('Noiva')).toBeInTheDocument();
  });
});

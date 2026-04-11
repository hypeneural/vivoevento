import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useParams } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventWorkspaceLayout from './EventWorkspaceLayout';
import EventWorkspaceModulePage from './EventWorkspaceModulePage';
import { formatEventDate } from './workspace-utils';

const useAuthMock = vi.fn();
const navigateMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

  return {
    ...actual,
    useNavigate: () => navigateMock,
  };
});

beforeEach(() => {
  vi.clearAllMocks();
  window.HTMLElement.prototype.scrollIntoView = vi.fn();
});

function buildWorkspace(overrides: Record<string, unknown> = {}) {
  return {
    event_id: 101,
    event_uuid: 'evt-101',
    event_title: 'Casamento Ana e Joao',
    event_slug: 'casamento-ana-joao',
    event_date: '2026-06-10',
    event_status: 'active',
    organization_id: 10,
    organization_name: 'Cerimonial Aurora',
    organization_slug: 'cerimonial-aurora',
    role_key: 'event.operator',
    role_label: 'Operar evento',
    persisted_role: 'operator',
    capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
    entry_path: '/my-events/101',
    ...overrides,
  };
}

function buildAuthValue(overrides: Record<string, unknown> = {}) {
  return {
    meUser: {
      id: 1,
      name: 'DJ Bruno',
      avatar_url: null,
      role: {
        key: 'viewer',
        name: 'Viewer',
      },
    },
    workspaces: {
      organizations: [],
      event_accesses: [
        buildWorkspace(),
        buildWorkspace({
          event_id: 202,
          event_uuid: 'evt-202',
          event_title: 'Casamento Beatriz e Leo',
          event_slug: 'casamento-beatriz-leo',
          event_date: '2026-07-15',
          organization_id: 20,
          organization_name: 'Bella Assessoria',
          organization_slug: 'bella-assessoria',
          role_key: 'event.moderator',
          role_label: 'Moderar midias',
          persisted_role: 'moderator',
          capabilities: ['overview', 'media', 'moderation'],
          entry_path: '/my-events/202',
        }),
      ],
    },
    activeContext: {
      type: 'event',
      organization_id: 10,
      event_id: 101,
      role_key: 'event.operator',
      role_label: 'Operar evento',
      capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
      entry_path: '/my-events/101',
    },
    isEventOnlySession: true,
    preferredHomePath: '/my-events',
    setEventContext: vi.fn().mockResolvedValue(undefined),
    logout: vi.fn(),
    ...overrides,
  };
}

function EventHomeProbe() {
  const { eventId } = useParams<{ eventId?: string }>();

  return <div>Resumo do evento {eventId}</div>;
}

function renderLayout(initialEntry = '/my-events/101', authOverrides: Record<string, unknown> = {}) {
  useAuthMock.mockReturnValue(buildAuthValue(authOverrides));

  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/my-events" element={<EventWorkspaceLayout />}>
          <Route index element={<div>Lista de eventos</div>} />
          <Route path=":eventId" element={<EventHomeProbe />} />
          <Route path=":eventId/:section" element={<EventWorkspaceModulePage />} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('workspace selector contracts', () => {
  it('shows plain-language event context without organization-wide navigation for an event-only session', async () => {
    renderLayout('/my-events/101');

    expect(screen.queryByRole('link', { name: /voltar ao painel/i })).not.toBeInTheDocument();
    expect(screen.getByText('DJ Bruno')).toBeInTheDocument();
    expect(screen.getAllByText('Cerimonial Aurora').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Operar evento').length).toBeGreaterThan(0);
    expect(screen.getByText(formatEventDate('2026-06-10'))).toBeInTheDocument();
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('preserves the selected section when switching the active event', async () => {
    const setEventContext = vi.fn().mockResolvedValue(undefined);

    renderLayout('/my-events/101/moderation', {
      setEventContext,
    });

    const combobox = screen.getByRole('combobox');
    fireEvent.mouseDown(combobox);
    fireEvent.keyDown(combobox, { key: 'ArrowDown' });
    fireEvent.click(await screen.findByText(/casamento beatriz e leo/i));

    await waitFor(() => {
      expect(setEventContext).toHaveBeenCalledWith(202);
      expect(navigateMock).toHaveBeenCalledWith('/my-events/202/moderation');
    });
  });

  it('redirects a user back to the event home when the selected module is not allowed in that event context', async () => {
    renderLayout('/my-events/303/wall', {
      workspaces: {
        organizations: [],
        event_accesses: [
          buildWorkspace({
            event_id: 303,
            event_uuid: 'evt-303',
            event_title: 'Festa Sofia 15',
            event_slug: 'festa-sofia-15',
            organization_id: 30,
            organization_name: 'Luz Cerimonial',
            organization_slug: 'luz-cerimonial',
            role_key: 'event.media-viewer',
            role_label: 'Ver midias',
            persisted_role: 'viewer',
            capabilities: ['overview', 'media'],
            entry_path: '/my-events/303',
          }),
        ],
      },
      activeContext: {
        type: 'event',
        organization_id: 30,
        event_id: 303,
        role_key: 'event.media-viewer',
        role_label: 'Ver midias',
        capabilities: ['overview', 'media'],
        entry_path: '/my-events/303',
      },
    });

    expect(await screen.findByText('Resumo do evento 303')).toBeInTheDocument();
    expect(screen.queryByText(/estrutura inicial pronta/i)).not.toBeInTheDocument();
  });
});

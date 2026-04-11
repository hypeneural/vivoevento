import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MyEventsPage from './MyEventsPage';
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
    workspaces: {
      organizations: [],
      event_accesses: [
        buildWorkspace(),
        buildWorkspace({
          event_id: 102,
          event_uuid: 'evt-102',
          event_title: 'Casamento Marina e Leo',
          event_slug: 'casamento-marina-leo',
          event_date: '2026-06-15',
          event_status: 'draft',
          role_key: 'event.moderator',
          role_label: 'Moderar midias',
          persisted_role: 'moderator',
          capabilities: ['overview', 'media', 'moderation'],
          entry_path: '/my-events/102',
        }),
        buildWorkspace({
          event_id: 201,
          event_uuid: 'evt-201',
          event_title: 'Festa Sofia 15',
          event_slug: 'festa-sofia-15',
          event_date: '2026-07-20',
          organization_id: 20,
          organization_name: 'Bella Assessoria',
          organization_slug: 'bella-assessoria',
          role_key: 'event.media-viewer',
          role_label: 'Ver midias',
          persisted_role: 'viewer',
          capabilities: ['overview', 'media'],
          entry_path: '/my-events/201',
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
    setEventContext: vi.fn().mockResolvedValue(undefined),
    meOrganization: null,
    ...overrides,
  };
}

function renderPage(authOverrides: Record<string, unknown> = {}) {
  useAuthMock.mockReturnValue(buildAuthValue(authOverrides));

  return render(
    <MemoryRouter>
      <MyEventsPage />
    </MemoryRouter>,
  );
}

describe('/my-events contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('groups event cards by partner organization and keeps plain-language role summaries', () => {
    renderPage();

    expect(screen.getAllByText(/cerimonial aurora/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/bella assessoria/i).length).toBeGreaterThan(0);
    expect(screen.getByText('Casamento Ana e Joao')).toBeInTheDocument();
    expect(screen.getByText('Festa Sofia 15')).toBeInTheDocument();
    expect(screen.getByText('Em uso')).toBeInTheDocument();
    expect(screen.getAllByText(/moderar/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/ver midias/i).length).toBeGreaterThan(0);
    expect(screen.queryByText('event.operator')).not.toBeInTheDocument();
    expect(screen.queryByText('event.media-viewer')).not.toBeInTheDocument();
    expect(screen.getByText(formatEventDate('2026-06-10'))).toBeInTheDocument();
  });

  it('shows only the media action for a media-viewer event access', () => {
    renderPage({
      workspaces: {
        organizations: [],
        event_accesses: [
          buildWorkspace({
            event_id: 201,
            event_uuid: 'evt-201',
            event_title: 'Festa Sofia 15',
            event_slug: 'festa-sofia-15',
            event_date: '2026-07-20',
            organization_id: 20,
            organization_name: 'Bella Assessoria',
            organization_slug: 'bella-assessoria',
            role_key: 'event.media-viewer',
            role_label: 'Ver midias',
            persisted_role: 'viewer',
            capabilities: ['overview', 'media'],
            entry_path: '/my-events/201',
          }),
        ],
      },
      activeContext: null,
    });

    expect(screen.getAllByText(/ver midias/i).length).toBeGreaterThan(0);
    expect(screen.queryByText('Moderar midias')).not.toBeInTheDocument();
    expect(screen.queryByText('Operar telao')).not.toBeInTheDocument();
    expect(screen.queryByText('Operar jogos')).not.toBeInTheDocument();
  });

  it('shows moderation, media, wall and play actions for an operator event access', () => {
    renderPage({
      workspaces: {
        organizations: [],
        event_accesses: [buildWorkspace()],
      },
    });

    expect(screen.getByText(/^Mídias$/i)).toBeInTheDocument();
    expect(screen.getByText(/^Moderação$/i)).toBeInTheDocument();
    expect(screen.getByText(/^Telão$/i)).toBeInTheDocument();
    expect(screen.getByText(/^Jogos$/i)).toBeInTheDocument();
    expect(screen.getByText(/moderar/i)).toBeInTheDocument();
    expect(screen.getByText(/aprovar ou recusar mídias deste evento/i)).toBeInTheDocument();
    expect(screen.getByText('Em uso')).toBeInTheDocument();
  });

  it('shows a dedicated empty state when the session has no event access at all', () => {
    renderPage({
      workspaces: {
        organizations: [],
        event_accesses: [],
      },
      activeContext: null,
    });

    expect(screen.getByText(/nenhum evento dispon/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Este acesso ainda não possui convites ativos por evento\./i).length).toBeGreaterThan(0);
  });

  it('shows a filtered empty state when the current search does not match any event', () => {
    renderPage();

    fireEvent.click(screen.getByRole('button', { name: /filtros e ordena/i }));
    fireEvent.change(screen.getByPlaceholderText(/buscar por parceiro, evento ou perfil/i), {
      target: { value: 'evento inexistente' },
    });

    expect(screen.getByText(/nenhum evento encontrado/i)).toBeInTheDocument();
    expect(screen.getByText(/ajuste os filtros para localizar outro acesso dispon/i)).toBeInTheDocument();
  });
});

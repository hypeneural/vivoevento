import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MyEventsPage from './MyEventsPage';

const useAuthMock = vi.fn();
const navigateMock = vi.fn();

const buildAuthValue = (overrides: Record<string, unknown> = {}) => ({
  workspaces: {
    organizations: [],
    event_accesses: [
      {
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
      },
      {
        event_id: 102,
        event_uuid: 'evt-102',
        event_title: 'Casamento Marina e Leo',
        event_slug: 'casamento-marina-leo',
        event_date: '2026-06-15',
        event_status: 'draft',
        organization_id: 10,
        organization_name: 'Cerimonial Aurora',
        organization_slug: 'cerimonial-aurora',
        role_key: 'event.moderator',
        role_label: 'Moderar mídias',
        persisted_role: 'moderator',
        capabilities: ['overview', 'media', 'moderation'],
        entry_path: '/my-events/102',
      },
      {
        event_id: 201,
        event_uuid: 'evt-201',
        event_title: 'Festa Sofia 15',
        event_slug: 'festa-sofia-15',
        event_date: '2026-07-20',
        event_status: 'active',
        organization_id: 20,
        organization_name: 'Bella Assessoria',
        organization_slug: 'bella-assessoria',
        role_key: 'event.media-viewer',
        role_label: 'Ver mídias',
        persisted_role: 'viewer',
        capabilities: ['overview', 'media'],
        entry_path: '/my-events/201',
      },
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
});

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

describe('MyEventsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue(buildAuthValue());
  });

  it('groups cards by partner and surfaces safe event actions', () => {
    render(
      <MemoryRouter>
        <MyEventsPage />
      </MemoryRouter>,
    );

    expect(screen.getAllByText(/bella assessoria/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/cerimonial aurora/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/casamento ana e joao/i)).toBeInTheDocument();
    expect(screen.getByText(/festa sofia 15/i)).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /abrir evento/i })).toHaveLength(2);
  });

  it('filters events by search text and opens the selected workspace', async () => {
    const setEventContext = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthValue({
      workspaces: {
        organizations: [],
        event_accesses: [
          {
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
          },
          {
            event_id: 201,
            event_uuid: 'evt-201',
            event_title: 'Festa Sofia 15',
            event_slug: 'festa-sofia-15',
            event_date: '2026-07-20',
            event_status: 'active',
            organization_id: 20,
            organization_name: 'Bella Assessoria',
            organization_slug: 'bella-assessoria',
            role_key: 'event.media-viewer',
            role_label: 'Ver mídias',
            persisted_role: 'viewer',
            capabilities: ['overview', 'media'],
            entry_path: '/my-events/201',
          },
        ],
      },
      activeContext: null,
      setEventContext,
      meOrganization: null,
    }));

    render(
      <MemoryRouter>
        <MyEventsPage />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('button', { name: /filtros e ordenação/i }));
    fireEvent.change(screen.getByPlaceholderText(/buscar por parceiro/i), {
      target: { value: 'Sofia' },
    });

    expect(screen.queryByText(/casamento ana e joao/i)).not.toBeInTheDocument();
    expect(screen.getByText(/festa sofia 15/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /abrir evento/i }));

    await waitFor(() => {
      expect(setEventContext).toHaveBeenCalledWith(201);
      expect(navigateMock).toHaveBeenCalledWith('/my-events/201');
    });
  });
});

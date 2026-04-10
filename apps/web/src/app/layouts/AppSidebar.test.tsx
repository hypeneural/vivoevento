import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AppSidebar } from './AppSidebar';

const useAuthMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/app/routing/route-preload', () => ({
  preloadRouteForPath: vi.fn(),
}));

describe('AppSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the partners navigation item when the session has partners.view.any access', () => {
    useAuthMock.mockReturnValue({
      meUser: {
        name: 'Admin Plataforma',
        avatar_url: null,
        role: {
          key: 'platform-admin',
          name: 'Platform Admin',
        },
      },
      workspaces: {
        organizations: [],
        event_accesses: [],
      },
      can: (permission: string) => permission === 'partners.view.any',
      canAccessModule: (moduleKey: string) => moduleKey === 'partners',
      logout: vi.fn(),
    });

    render(
      <MemoryRouter>
        <AppSidebar collapsed={false} onToggle={vi.fn()} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: /parceiros/i })).toBeInTheDocument();
  });

  it('shows the IA navigation item when the session can manage settings', () => {
    useAuthMock.mockReturnValue({
      meUser: {
        name: 'Admin Plataforma',
        avatar_url: null,
        role: {
          key: 'platform-admin',
          name: 'Platform Admin',
        },
      },
      workspaces: {
        organizations: [],
        event_accesses: [],
      },
      can: (permission: string) => permission === 'settings.manage',
      canAccessModule: (moduleKey: string) => moduleKey === 'settings',
      logout: vi.fn(),
    });

    render(
      <MemoryRouter>
        <AppSidebar collapsed={false} onToggle={vi.fn()} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: /modera/i })).toBeInTheDocument();
  });

  it('shows the my-events navigation item when the session has event-scoped workspaces', () => {
    useAuthMock.mockReturnValue({
      meUser: {
        name: 'DJ Eventual',
        avatar_url: null,
        role: {
          key: 'viewer',
          name: 'Viewer',
        },
      },
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
        ],
      },
      can: (permission: string) => ['dashboard.view', 'events.view', 'media.view'].includes(permission),
      canAccessModule: (moduleKey: string) => ['dashboard', 'events', 'media'].includes(moduleKey),
      logout: vi.fn(),
    });

    render(
      <MemoryRouter>
        <AppSidebar collapsed={false} onToggle={vi.fn()} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: /dashboard/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /eventos/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /midias/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /meus eventos/i })).toBeInTheDocument();
  });
});

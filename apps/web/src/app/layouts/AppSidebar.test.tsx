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
      can: (permission: string) => permission === 'settings.manage',
      canAccessModule: (moduleKey: string) => moduleKey === 'settings',
      logout: vi.fn(),
    });

    render(
      <MemoryRouter>
        <AppSidebar collapsed={false} onToggle={vi.fn()} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: /^ia$/i })).toBeInTheDocument();
  });
});

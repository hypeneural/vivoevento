import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AppHeader } from './AppHeader';
import { mockNotifications } from '@/shared/mock/data';

const useAuthMock = vi.fn();
const useThemeMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/app/providers/ThemeProvider', () => ({
  useTheme: () => useThemeMock(),
}));

vi.mock('@/shared/components/GlobalSearch', () => ({
  GlobalSearch: () => <div>Busca global</div>,
}));

vi.mock('@/shared/components/UserAvatar', () => ({
  UserAvatar: ({ name }: { name: string }) => <div>{name}</div>,
}));

describe('AppHeader characterization', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      meUser: {
        id: 1,
        name: 'Admin Plataforma',
        email: 'admin@eventovivo.test',
        avatar_url: null,
        role: {
          key: 'platform-admin',
          name: 'Platform Admin',
        },
      },
      meOrganization: {
        id: 10,
        name: 'Evento Vivo',
      },
      availableUsers: [],
      can: (permission: string) => permission === 'events.create' || permission === 'settings.manage',
      loginMock: vi.fn(),
      logout: vi.fn(),
    });

    useThemeMock.mockReturnValue({
      theme: 'light',
      resolvedTheme: 'light',
      setTheme: vi.fn(),
    });
  });

  it('characterizes that the notifications badge in the app header still comes from mock notification data', () => {
    render(
      <MemoryRouter>
        <AppHeader />
      </MemoryRouter>,
    );

    const unreadCount = mockNotifications.filter((item) => !item.read).length;

    expect(screen.getByText(String(unreadCount))).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /abrir notificações/i })).toBeInTheDocument();
  });
});

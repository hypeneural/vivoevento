import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicEventInvitationPage from './PublicEventInvitationPage';

const getPublicInvitationMock = vi.fn();
const acceptPublicInvitationMock = vi.fn();
const acceptAuthenticatedInvitationMock = vi.fn();
const persistSessionMock = vi.fn();
const setTokenMock = vi.fn();
const useAuthMock = vi.fn();
const logoutMock = vi.fn();

vi.mock('./api', () => ({
  eventInvitationsApi: {
    getPublicInvitation: (...args: unknown[]) => getPublicInvitationMock(...args),
    acceptPublicInvitation: (...args: unknown[]) => acceptPublicInvitationMock(...args),
    acceptAuthenticatedInvitation: (...args: unknown[]) => acceptAuthenticatedInvitationMock(...args),
  },
}));

vi.mock('@/modules/auth/services/auth.service', () => ({
  persistSession: (...args: unknown[]) => persistSessionMock(...args),
}));

vi.mock('@/lib/api', async () => {
  const actual = await vi.importActual<typeof import('@/lib/api')>('@/lib/api');

  return {
    ...actual,
    setToken: (...args: unknown[]) => setTokenMock(...args),
  };
});

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

function LoginRouteProbe() {
  const location = useLocation();

  return <div>{`${location.pathname}${location.search}`}</div>;
}

function renderPage(initialEntry = '/convites/eventos/token-123') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  const router = createMemoryRouter(
    [
      {
        path: '/convites/eventos/:token',
        element: (
          <QueryClientProvider client={queryClient}>
            <PublicEventInvitationPage />
          </QueryClientProvider>
        ),
      },
      {
        path: '/login',
        element: <LoginRouteProbe />,
      },
    ],
    {
      initialEntries: [initialEntry],
    },
  );

  render(<RouterProvider router={router} />);

  return { router };
}

describe('PublicEventInvitationPage routing', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthMock.mockReturnValue({
      isAuthenticated: false,
      logout: logoutMock,
      meUser: null,
    });
  });

  const existingInvitation = {
    id: 1,
    status: 'pending',
    requires_existing_login: true,
    invitee_name: 'DJ Bruno',
    invitee_contact: {
      email: 'dj-bruno@eventovivo.test',
      phone_masked: '+55 (11) *****7665',
    },
    event: {
      id: 55,
      title: 'Casamento da Lara',
      date: '2026-05-18',
      status: 'active',
    },
    organization: {
      id: 7,
      name: 'Cerimonial Aurora',
      slug: 'cerimonial-aurora',
    },
    invited_by: {
      name: 'Marina Aurora',
    },
    access: {
      preset_key: 'event.operator',
      role_label: 'Operar evento',
      description: 'Opera telao e jogos, alem de moderar midias.',
      capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
    },
    next_path: '/my-events/55',
    token_expires_at: '2026-05-10T12:00:00Z',
  };

  it('navigates to the login route with returnTo when the existing-user CTA is clicked', async () => {
    getPublicInvitationMock.mockResolvedValue(existingInvitation);

    const user = userEvent.setup({ delay: null });

    renderPage();

    expect(await screen.findByText('Casamento da Lara')).toBeInTheDocument();

    await user.click(screen.getByRole('link', { name: /entrar para aceitar convite/i }));

    expect(await screen.findByText('/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123')).toBeInTheDocument();
  });

  it('navigates to the forgot-password login route when the recovery CTA is clicked', async () => {
    getPublicInvitationMock.mockResolvedValue(existingInvitation);

    const user = userEvent.setup({ delay: null });

    renderPage();

    expect(await screen.findByText('Casamento da Lara')).toBeInTheDocument();

    await user.click(screen.getByRole('link', { name: /esqueci a senha/i }));

    expect(
      await screen.findByText('/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123&flow=forgot'),
    ).toBeInTheDocument();
  });

  it('navigates to the login route with returnTo after switching account', async () => {
    getPublicInvitationMock.mockResolvedValue(existingInvitation);
    useAuthMock.mockReturnValue({
      isAuthenticated: true,
      logout: logoutMock,
      meUser: { name: 'Conta errada' },
    });

    const user = userEvent.setup({ delay: null });

    renderPage();

    expect(await screen.findByText('Casamento da Lara')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /usar outra conta/i }));

    await waitFor(() => {
      expect(logoutMock).toHaveBeenCalled();
    });

    expect(await screen.findByText('/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123')).toBeInTheDocument();
  });
});

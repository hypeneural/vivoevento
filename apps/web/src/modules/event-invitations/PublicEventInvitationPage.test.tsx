import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicEventInvitationPage from './PublicEventInvitationPage';

const getPublicInvitationMock = vi.fn();
const acceptPublicInvitationMock = vi.fn();
const acceptAuthenticatedInvitationMock = vi.fn();
const persistSessionMock = vi.fn();
const setTokenMock = vi.fn();
const useAuthMock = vi.fn();
const redirectToInvitationNextPathMock = vi.fn();
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

vi.mock('./navigation', () => ({
  redirectToInvitationNextPath: (...args: unknown[]) => redirectToInvitationNextPathMock(...args),
}));

function renderPage(initialEntry = '/convites/eventos/token-123') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/convites/eventos/:token" element={<PublicEventInvitationPage />} />
          <Route path="/login" element={<div>login-page</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicEventInvitationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthMock.mockReturnValue({
      isAuthenticated: false,
      logout: logoutMock,
      meUser: null,
    });
  });

  it('renders the invitation context and asks the existing user to login before accepting', async () => {
    getPublicInvitationMock.mockResolvedValue({
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
        description: 'Opera telão e jogos, além de moderar mídias.',
        capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
      },
      next_path: '/my-events/55',
      token_expires_at: '2026-05-10T12:00:00Z',
    });

    renderPage();

    expect(await screen.findByText('Casamento da Lara')).toBeInTheDocument();
    expect(screen.getAllByText(/cerimonial aurora/i)).toHaveLength(2);
    expect(screen.getByText(/marina aurora convidou você/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /entrar para aceitar convite/i })).toHaveAttribute(
      'href',
      '/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123',
    );
    expect(screen.getByRole('link', { name: /esqueci a senha/i })).toHaveAttribute(
      'href',
      '/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123&flow=forgot',
    );
    expect(screen.getByText(/você voltará para este convite após entrar/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/senha/i)).not.toBeInTheDocument();
  });

  it('accepts a new invitation by creating password and hydrating the session', async () => {
    getPublicInvitationMock.mockResolvedValue({
      id: 2,
      status: 'pending',
      requires_existing_login: false,
      invitee_name: 'Noiva Julia',
      invitee_contact: {
        email: null,
        phone_masked: '+55 (11) *****7670',
      },
      event: {
        id: 88,
        title: 'Casamento Julia e Caio',
        date: '2026-06-20',
        status: 'active',
      },
      organization: {
        id: 9,
        name: 'Bella Assessoria',
        slug: 'bella-assessoria',
      },
      invited_by: {
        name: 'Clara Bella',
      },
      access: {
        preset_key: 'event.media-viewer',
        role_label: 'Ver mídias',
        description: 'Acompanha apenas as mídias deste evento.',
        capabilities: ['overview', 'media'],
      },
      next_path: '/my-events/88',
      token_expires_at: '2026-06-01T12:00:00Z',
    });

    acceptPublicInvitationMock.mockResolvedValue({
      accepted: true,
      token: 'token-publico',
      next_path: '/my-events/88',
      session: {
        user: { id: 99, name: 'Noiva Julia', email: 'invite+5511998877670@eventovivo.local', phone: '5511998877670', role: { key: 'viewer', label: 'Visualização' }, permissions: [] },
        organization: null,
        access: { accessible_modules: ['dashboard', 'events'], feature_flags: {}, entitlements: null },
        subscription: null,
        active_context: { type: 'event', organization_id: 9, event_id: 88, role_key: 'event.media-viewer', role_label: 'Ver mídias', capabilities: ['overview', 'media'], entry_path: '/my-events/88' },
        workspaces: { organizations: [], event_accesses: [] },
      },
    });

    renderPage();

    expect(await screen.findByText('Casamento Julia e Caio')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText(/^senha$/i), { target: { value: 'SenhaForte123!' } });
    fireEvent.change(screen.getByLabelText(/confirmar senha/i), { target: { value: 'SenhaForte123!' } });
    fireEvent.click(screen.getByRole('button', { name: /criar conta, aceitar convite e entrar/i }));

    await waitFor(() => {
      expect(acceptPublicInvitationMock).toHaveBeenCalledWith('token-123', expect.objectContaining({
        password: 'SenhaForte123!',
        password_confirmation: 'SenhaForte123!',
      }));
    });

    expect(setTokenMock).toHaveBeenCalledWith('token-publico');
    expect(persistSessionMock).toHaveBeenCalled();
    expect(redirectToInvitationNextPathMock).toHaveBeenCalledWith('/my-events/88');
  });

  it('allows the authenticated user to switch account before accepting an event invitation', async () => {
    getPublicInvitationMock.mockResolvedValue({
      id: 3,
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
    });

    useAuthMock.mockReturnValue({
      isAuthenticated: true,
      logout: logoutMock,
      meUser: { name: 'Conta errada' },
    });

    renderPage();

    expect(await screen.findByText('Casamento da Lara')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /usar outra conta/i }));

    await waitFor(() => {
      expect(logoutMock).toHaveBeenCalled();
    });

    expect(await screen.findByText('login-page')).toBeInTheDocument();
  });
});

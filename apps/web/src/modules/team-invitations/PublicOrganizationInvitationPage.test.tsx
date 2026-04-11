import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicOrganizationInvitationPage from './PublicOrganizationInvitationPage';

const getPublicInvitationMock = vi.fn();
const acceptPublicInvitationMock = vi.fn();
const acceptAuthenticatedInvitationMock = vi.fn();
const persistSessionMock = vi.fn();
const setTokenMock = vi.fn();
const useAuthMock = vi.fn();
const redirectToInvitationNextPathMock = vi.fn();
const logoutMock = vi.fn();

vi.mock('./api', () => ({
  organizationInvitationsApi: {
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

vi.mock('@/modules/event-invitations/navigation', () => ({
  redirectToInvitationNextPath: (...args: unknown[]) => redirectToInvitationNextPathMock(...args),
}));

function renderPage(initialEntry = '/convites/equipe/token-123') {
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
          <Route path="/convites/equipe/:token" element={<PublicOrganizationInvitationPage />} />
          <Route path="/login" element={<div>login-page</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicOrganizationInvitationPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuthMock.mockReturnValue({
      isAuthenticated: false,
      logout: logoutMock,
      meUser: null,
    });
  });

  it('renders the organization invitation context and asks the existing user to login first', async () => {
    getPublicInvitationMock.mockResolvedValue({
      id: 1,
      requires_existing_login: true,
      invitee_name: 'Secretaria Ana',
      invitee_contact: {
        email: 'ana@eventovivo.test',
        phone_masked: '+55 ******7765',
      },
      organization: {
        id: 7,
        name: 'Cerimonial Aurora',
        slug: 'cerimonial-aurora',
        logo_url: null,
      },
      invited_by: {
        name: 'Marina Aurora',
      },
      access: {
        role_key: 'partner-manager',
        role_label: 'Gerente / Secretaria',
        description: 'Pode organizar clientes, eventos e a rotina da operacao da empresa.',
      },
      invitation_url: 'https://eventovivo.test/convites/equipe/token-123',
      token_expires_at: '2026-05-10T12:00:00Z',
    });

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Cerimonial Aurora' })).toBeInTheDocument();
    expect(screen.getByText(/marina aurora convidou você/i)).toBeInTheDocument();
    expect(screen.getByText(/quem convidou:/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /entrar para aceitar convite/i })).toHaveAttribute(
      'href',
      '/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123',
    );
    expect(screen.getByRole('link', { name: /esqueci a senha/i })).toHaveAttribute(
      'href',
      '/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot',
    );
    expect(screen.getByText(/você voltará para este convite após entrar/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/^senha$/i)).not.toBeInTheDocument();
  });

  it('accepts a new organization invitation by creating a password and hydrating the session', async () => {
    getPublicInvitationMock.mockResolvedValue({
      id: 2,
      requires_existing_login: false,
      invitee_name: 'Financeiro Leo',
      invitee_contact: {
        email: null,
        phone_masked: '+55 ******7670',
      },
      organization: {
        id: 9,
        name: 'Bella Assessoria',
        slug: 'bella-assessoria',
        logo_url: null,
      },
      invited_by: {
        name: 'Paula Bella',
      },
      access: {
        role_key: 'financeiro',
        role_label: 'Financeiro',
        description: 'Pode acompanhar cobrancas, faturamento e dados financeiros da organizacao.',
      },
      invitation_url: 'https://eventovivo.test/convites/equipe/token-123',
      token_expires_at: '2026-06-01T12:00:00Z',
    });

    acceptPublicInvitationMock.mockResolvedValue({
      accepted: true,
      token: 'token-organizacao',
      next_path: '/',
      session: {
        user: { id: 99, name: 'Financeiro Leo', email: 'invite+5511998877670@eventovivo.local', phone: '5511998877670', role: { key: 'financeiro', label: 'Financeiro' }, permissions: [] },
        organization: { id: 9, uuid: 'org-9', name: 'Bella Assessoria', slug: 'bella-assessoria', logo_url: null, branding: { primary_color: '#7c3aed', secondary_color: '#3b82f6' } },
        access: { accessible_modules: ['dashboard', 'billing'], feature_flags: {}, entitlements: null },
        subscription: null,
        active_context: { type: 'organization', organization_id: 9, event_id: null, role_key: 'financeiro', role_label: 'Financeiro', capabilities: [], entry_path: '/' },
        workspaces: { organizations: [], event_accesses: [] },
      },
    });

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Bella Assessoria' })).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText(/^senha$/i), { target: { value: 'SenhaForte123!' } });
    fireEvent.change(screen.getByLabelText(/confirmar senha/i), { target: { value: 'SenhaForte123!' } });
    fireEvent.click(screen.getByRole('button', { name: /criar conta, aceitar convite e entrar/i }));

    await waitFor(() => {
      expect(acceptPublicInvitationMock).toHaveBeenCalledWith('token-123', expect.objectContaining({
        password: 'SenhaForte123!',
        password_confirmation: 'SenhaForte123!',
      }));
    });

    expect(setTokenMock).toHaveBeenCalledWith('token-organizacao');
    expect(persistSessionMock).toHaveBeenCalled();
    expect(redirectToInvitationNextPathMock).toHaveBeenCalledWith('/');
  });

  it('allows the authenticated user to switch account before accepting an organization invitation', async () => {
    getPublicInvitationMock.mockResolvedValue({
      id: 3,
      requires_existing_login: true,
      invitee_name: 'Secretaria Ana',
      invitee_contact: {
        email: 'ana@eventovivo.test',
        phone_masked: '+55 ******7765',
      },
      organization: {
        id: 7,
        name: 'Cerimonial Aurora',
        slug: 'cerimonial-aurora',
        logo_url: null,
      },
      invited_by: {
        name: 'Marina Aurora',
      },
      access: {
        role_key: 'partner-manager',
        role_label: 'Gerente / Secretaria',
        description: 'Pode organizar clientes, eventos e a rotina da operacao da empresa.',
      },
      invitation_url: 'https://eventovivo.test/convites/equipe/token-123',
      token_expires_at: '2026-05-10T12:00:00Z',
    });

    useAuthMock.mockReturnValue({
      isAuthenticated: true,
      logout: logoutMock,
      meUser: { name: 'Conta errada' },
    });

    renderPage();

    expect(await screen.findByRole('heading', { name: 'Cerimonial Aurora' })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /usar outra conta/i }));

    await waitFor(() => {
      expect(logoutMock).toHaveBeenCalled();
    });

    expect(await screen.findByText('login-page')).toBeInTheDocument();
  });
});

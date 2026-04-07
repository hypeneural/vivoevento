import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import SettingsPage from './SettingsPage';

const useAuthMock = vi.fn();
const listCurrentOrganizationTeamMock = vi.fn();
const updateCurrentOrganizationMock = vi.fn();
const updateCurrentOrganizationBrandingMock = vi.fn();
const uploadCurrentOrganizationLogoMock = vi.fn();
const inviteCurrentOrganizationTeamMemberMock = vi.fn();
const removeCurrentOrganizationTeamMemberMock = vi.fn();
const updateCurrentUserPreferencesMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

vi.mock('./api', () => ({
  settingsService: {
    listCurrentOrganizationTeam: (...args: unknown[]) => listCurrentOrganizationTeamMock(...args),
    updateCurrentOrganization: (...args: unknown[]) => updateCurrentOrganizationMock(...args),
    updateCurrentOrganizationBranding: (...args: unknown[]) => updateCurrentOrganizationBrandingMock(...args),
    uploadCurrentOrganizationLogo: (...args: unknown[]) => uploadCurrentOrganizationLogoMock(...args),
    inviteCurrentOrganizationTeamMember: (...args: unknown[]) => inviteCurrentOrganizationTeamMemberMock(...args),
    removeCurrentOrganizationTeamMember: (...args: unknown[]) => removeCurrentOrganizationTeamMemberMock(...args),
    updateCurrentUserPreferences: (...args: unknown[]) => updateCurrentUserPreferencesMock(...args),
  },
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <SettingsPage />
      </QueryClientProvider>
    </MemoryRouter>,
  );
}

function activateTab(name: RegExp) {
  const tab = screen.getByRole('tab', { name });
  fireEvent.mouseDown(tab);
  fireEvent.click(tab);
}

function buildAuthMock(overrides: Record<string, unknown> = {}) {
  return {
    meUser: {
      id: 1,
      name: 'Admin Teste',
      email: 'admin@eventovivo.test',
      avatar_url: null,
      role: {
        key: 'partner-owner',
        name: 'partner-owner',
      },
      preferences: {
        theme: 'light',
        timezone: 'America/Sao_Paulo',
        locale: 'pt-BR',
        email_notifications: true,
        push_notifications: false,
        compact_mode: false,
      },
    },
    meOrganization: {
      id: 77,
      uuid: 'org-77',
      name: 'Organizacao Teste',
      slug: 'organizacao-teste',
      logo_url: null,
      branding: {
        primary_color: '#7c3aed',
        secondary_color: '#3b82f6',
      },
    },
    refreshSession: vi.fn(),
    can: (permission: string) =>
      ['team.manage', 'branding.manage', 'settings.manage', 'integrations.manage'].includes(permission),
    ...overrides,
  };
}

describe('SettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue(buildAuthMock());

    listCurrentOrganizationTeamMock.mockResolvedValue({
      success: true,
      data: [
        {
          id: 10,
          role_key: 'partner-owner',
          is_owner: true,
          status: 'active',
          invited_at: null,
          joined_at: '2026-04-07T12:00:00Z',
          user: {
            id: 8,
            name: 'Maria Team',
            email: 'maria@org.test',
          },
        },
        {
          id: 11,
          role_key: 'partner-manager',
          is_owner: false,
          status: 'active',
          invited_at: null,
          joined_at: '2026-04-07T12:05:00Z',
          user: {
            id: 9,
            name: 'Carlos Gestor',
            email: 'carlos@org.test',
          },
        },
      ],
    });
    updateCurrentOrganizationMock.mockResolvedValue({ success: true, data: {} });
    updateCurrentOrganizationBrandingMock.mockResolvedValue({ success: true, data: {} });
    uploadCurrentOrganizationLogoMock.mockResolvedValue({ success: true, data: {} });
    inviteCurrentOrganizationTeamMemberMock.mockResolvedValue({ success: true, data: {} });
    removeCurrentOrganizationTeamMemberMock.mockResolvedValue({ success: true });
    updateCurrentUserPreferencesMock.mockResolvedValue({ success: true, data: {} });
  });

  it('switches visible settings sections when the user changes tabs', async () => {
    renderPage();

    const profileTab = screen.getByRole('tab', { name: /perfil/i });
    const organizationTab = screen.getByRole('tab', { name: /organizacao/i });
    const teamTab = screen.getByRole('tab', { name: /equipe/i });

    expect(profileTab).toHaveAttribute('data-state', 'active');

    fireEvent.mouseDown(organizationTab);
    fireEvent.click(organizationTab);

    await waitFor(() => {
      expect(profileTab).toHaveAttribute('data-state', 'inactive');
      expect(organizationTab).toHaveAttribute('data-state', 'active');
    });

    fireEvent.mouseDown(teamTab);
    fireEvent.click(teamTab);

    await waitFor(() => {
      expect(teamTab).toHaveAttribute('data-state', 'active');
    });
  });

  it('loads the current organization team from the API instead of mock users', async () => {
    renderPage();

    await waitFor(() => {
      expect(listCurrentOrganizationTeamMock).toHaveBeenCalledTimes(1);
    });
  });

  it('hides the permissions matrix for organization roles even when they can manage settings', () => {
    renderPage();

    expect(screen.queryByRole('tab', { name: /permiss/i })).not.toBeInTheDocument();
  });

  it('shows permissions and integrations only for super admins', () => {
    useAuthMock.mockReturnValue(buildAuthMock({
      meUser: {
        id: 1,
        name: 'Super',
        email: 'super@eventovivo.test',
        avatar_url: null,
        role: {
          key: 'super-admin',
          name: 'super-admin',
        },
        preferences: {
          theme: 'light',
          timezone: 'America/Sao_Paulo',
          locale: 'pt-BR',
          email_notifications: true,
          push_notifications: false,
          compact_mode: false,
        },
      },
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage', 'channels.manage'].includes(permission),
    }));

    renderPage();

    expect(screen.getByRole('tab', { name: /permiss/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /integrac/i })).toBeInTheDocument();
  });

  it('hides permissions and integrations for platform admins', () => {
    useAuthMock.mockReturnValue(buildAuthMock({
      meUser: {
        id: 1,
        name: 'Plataforma',
        email: 'platform@eventovivo.test',
        avatar_url: null,
        role: {
          key: 'platform-admin',
          name: 'platform-admin',
        },
        preferences: {
          theme: 'light',
          timezone: 'America/Sao_Paulo',
          locale: 'pt-BR',
          email_notifications: true,
          push_notifications: false,
          compact_mode: false,
        },
      },
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage', 'channels.manage'].includes(permission),
    }));

    renderPage();

    expect(screen.queryByRole('tab', { name: /permiss/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /integrac/i })).not.toBeInTheDocument();
  });

  it('does not query the current organization team when the session lacks team access', () => {
    useAuthMock.mockReturnValue(buildAuthMock({
      meUser: {
        id: 1,
        name: 'Admin Teste',
        email: 'admin@eventovivo.test',
        avatar_url: null,
        role: {
          key: 'viewer',
          name: 'viewer',
        },
        preferences: {
          theme: 'light',
          timezone: 'America/Sao_Paulo',
          locale: 'pt-BR',
          email_notifications: true,
          push_notifications: false,
          compact_mode: false,
        },
      },
      can: (permission: string) => ['branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    expect(listCurrentOrganizationTeamMock).not.toHaveBeenCalled();
    expect(screen.queryByRole('tab', { name: /equipe/i })).not.toBeInTheDocument();
  });

  it('persists current organization data from the settings form', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      meOrganization: {
        id: 77,
        uuid: 'org-77',
        name: 'Organizacao Teste',
        slug: 'organizacao-teste',
        logo_url: null,
        branding: {
          primary_color: '#7c3aed',
          secondary_color: '#3b82f6',
          custom_domain: 'eventovivo.test',
        },
      },
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/organizacao/i);
    fireEvent.change(await screen.findByDisplayValue('Organizacao Teste'), {
      target: { value: 'Organizacao Atualizada' },
    });
    fireEvent.change(screen.getByDisplayValue('organizacao-teste'), {
      target: { value: 'organizacao-atualizada' },
    });
    fireEvent.change(screen.getByPlaceholderText(/eventos.suaempresa.com/i), {
      target: { value: 'eventos.organizacao.com' },
    });

    fireEvent.click(screen.getAllByRole('button', { name: /^salvar$/i })[0]);

    await waitFor(() => {
      expect(updateCurrentOrganizationMock).toHaveBeenCalledWith({
        name: 'Organizacao Atualizada',
        slug: 'organizacao-atualizada',
        custom_domain: 'eventos.organizacao.com',
      });
      expect(refreshSession).toHaveBeenCalled();
    });
  });

  it('persists branding data from the settings form', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/branding/i);
    const colorInputs = await screen.findAllByDisplayValue(/#(7c3aed|3b82f6)/i);
    fireEvent.change(colorInputs[0], { target: { value: '#111111' } });
    fireEvent.change(colorInputs[1], { target: { value: '#222222' } });

    fireEvent.click(screen.getAllByRole('button', { name: /^salvar$/i })[0]);

    await waitFor(() => {
      expect(updateCurrentOrganizationBrandingMock).toHaveBeenCalledWith({
        primary_color: '#111111',
        secondary_color: '#222222',
      });
      expect(refreshSession).toHaveBeenCalled();
    });
  });

  it('uploads the organization logo from the branding settings section', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      meOrganization: {
        id: 77,
        uuid: 'org-77',
        name: 'Organizacao Teste',
        slug: 'organizacao-teste',
        logo_url: 'https://cdn.eventovivo.test/logo.webp',
        branding: {
          primary_color: '#7c3aed',
          secondary_color: '#3b82f6',
        },
      },
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/branding/i);

    const file = new File(['logo'], 'logo.png', { type: 'image/png' });
    fireEvent.change(await screen.findByLabelText(/logo da organizacao/i), {
      target: { files: [file] },
    });
    fireEvent.click(screen.getByRole('button', { name: /enviar logo/i }));

    await waitFor(() => {
      expect(uploadCurrentOrganizationLogoMock).toHaveBeenCalledWith(file);
      expect(refreshSession).toHaveBeenCalled();
    });
  });

  it('persists user preferences from the settings page', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/preferencias/i);

    fireEvent.click(screen.getByRole('switch', { name: /notificacoes por e-mail/i }));
    fireEvent.click(screen.getByRole('switch', { name: /notificacoes push/i }));
    fireEvent.click(screen.getByRole('switch', { name: /modo compacto/i }));
    fireEvent.click(screen.getByRole('button', { name: /^salvar$/i }));

    await waitFor(() => {
      expect(updateCurrentUserPreferencesMock).toHaveBeenCalledWith({
        email_notifications: false,
        push_notifications: true,
        compact_mode: true,
      });
      expect(refreshSession).toHaveBeenCalled();
    });
  });

  it('invites a team member from settings and removes an existing non-owner member', async () => {
    renderPage();

    activateTab(/equipe/i);
    fireEvent.click(await screen.findByRole('button', { name: /convidar/i }));

    fireEvent.change(await screen.findByPlaceholderText(/nome do membro/i), {
      target: { value: 'Novo Membro' },
    });
    fireEvent.change(screen.getByPlaceholderText(/membro@organizacao.com/i), {
      target: { value: 'novo-membro@organizacao.com' },
    });
    fireEvent.click(screen.getByRole('button', { name: /adicionar membro/i }));

    await waitFor(() => {
      expect(inviteCurrentOrganizationTeamMemberMock).toHaveBeenCalledWith(expect.objectContaining({
        user: expect.objectContaining({
          name: 'Novo Membro',
          email: 'novo-membro@organizacao.com',
        }),
        role_key: 'partner-manager',
      }));
    });

    expect(screen.queryByRole('button', { name: /remover maria team/i })).not.toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: /remover carlos gestor/i }));

    await waitFor(() => {
      expect(removeCurrentOrganizationTeamMemberMock).toHaveBeenCalledWith(11);
    });
  });
});

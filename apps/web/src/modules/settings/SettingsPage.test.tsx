import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import SettingsPage from './SettingsPage';

const useAuthMock = vi.fn();
const listCurrentOrganizationTeamMock = vi.fn();
const listCurrentOrganizationTeamInvitationsMock = vi.fn();
const updateCurrentOrganizationMock = vi.fn();
const updateCurrentOrganizationBrandingMock = vi.fn();
const uploadCurrentOrganizationLogoMock = vi.fn();
const uploadCurrentOrganizationBrandingAssetMock = vi.fn();
const inviteCurrentOrganizationTeamMemberMock = vi.fn();
const resendCurrentOrganizationTeamInvitationMock = vi.fn();
const revokeCurrentOrganizationTeamInvitationMock = vi.fn();
const removeCurrentOrganizationTeamMemberMock = vi.fn();
const transferCurrentOrganizationOwnershipMock = vi.fn();
const updateCurrentUserPreferencesMock = vi.fn();
const getMediaIntelligenceGlobalSettingsMock = vi.fn();
const updateMediaIntelligenceGlobalSettingsMock = vi.fn();
const clipboardWriteTextMock = vi.fn();

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
    listCurrentOrganizationTeamInvitations: (...args: unknown[]) => listCurrentOrganizationTeamInvitationsMock(...args),
    updateCurrentOrganization: (...args: unknown[]) => updateCurrentOrganizationMock(...args),
    updateCurrentOrganizationBranding: (...args: unknown[]) => updateCurrentOrganizationBrandingMock(...args),
    uploadCurrentOrganizationLogo: (...args: unknown[]) => uploadCurrentOrganizationLogoMock(...args),
    uploadCurrentOrganizationBrandingAsset: (...args: unknown[]) => uploadCurrentOrganizationBrandingAssetMock(...args),
    inviteCurrentOrganizationTeamMember: (...args: unknown[]) => inviteCurrentOrganizationTeamMemberMock(...args),
    resendCurrentOrganizationTeamInvitation: (...args: unknown[]) => resendCurrentOrganizationTeamInvitationMock(...args),
    revokeCurrentOrganizationTeamInvitation: (...args: unknown[]) => revokeCurrentOrganizationTeamInvitationMock(...args),
    removeCurrentOrganizationTeamMember: (...args: unknown[]) => removeCurrentOrganizationTeamMemberMock(...args),
    transferCurrentOrganizationOwnership: (...args: unknown[]) => transferCurrentOrganizationOwnershipMock(...args),
    updateCurrentUserPreferences: (...args: unknown[]) => updateCurrentUserPreferencesMock(...args),
    getMediaIntelligenceGlobalSettings: (...args: unknown[]) => getMediaIntelligenceGlobalSettingsMock(...args),
    updateMediaIntelligenceGlobalSettings: (...args: unknown[]) => updateMediaIntelligenceGlobalSettingsMock(...args),
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
        <TooltipProvider>
          <SettingsPage />
        </TooltipProvider>
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
        custom_domain: null,
        expanded_assets: false,
        watermark: false,
      },
    },
    meEntitlements: {
      version: 1,
      organization_type: 'partner',
      modules: {
        live_gallery: true,
        wall: false,
        play: false,
        hub: true,
        whatsapp_ingestion: true,
        analytics_advanced: false,
      },
      limits: {
        max_active_events: null,
        retention_days: null,
      },
      branding: {
        white_label: false,
        custom_domain: false,
        expanded_assets: false,
        watermark: false,
      },
      source_summary: [],
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

    Object.defineProperty(globalThis.navigator, 'clipboard', {
      configurable: true,
      value: {
        writeText: clipboardWriteTextMock,
      },
    });

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
            phone: '5511988880000',
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
            phone: '5511977771111',
          },
        },
      ],
    });
    listCurrentOrganizationTeamInvitationsMock.mockResolvedValue({
      success: true,
      data: [
        {
          id: 301,
          organization_id: 77,
          status: 'pending',
          role_key: 'event-operator',
          role_label: 'Operar eventos',
          role_description: 'Pode operar os eventos, acompanhar midias e atuar na execucao do dia.',
          existing_user_id: null,
          invitee: {
            name: 'DJ Bruno',
            email: null,
            phone: '5511998877665',
          },
          delivery_channel: 'manual',
          delivery_status: 'manual_link',
          delivery_error: null,
          invitation_url: 'https://eventovivo.test/convites/equipe/token-123',
          token_expires_at: '2026-04-20T12:00:00Z',
          last_sent_at: null,
          accepted_at: null,
          revoked_at: null,
          created_at: '2026-04-10T12:00:00Z',
          updated_at: '2026-04-10T12:00:00Z',
        },
      ],
    });
    updateCurrentOrganizationMock.mockResolvedValue({ success: true, data: {} });
    updateCurrentOrganizationBrandingMock.mockResolvedValue({ success: true, data: {} });
    uploadCurrentOrganizationLogoMock.mockResolvedValue({ success: true, data: {} });
    uploadCurrentOrganizationBrandingAssetMock.mockResolvedValue({ success: true, data: {} });
    inviteCurrentOrganizationTeamMemberMock.mockResolvedValue({
      id: 999,
      delivery_status: 'manual_link',
      invitation_url: 'https://eventovivo.test/convites/equipe/token-new',
    });
    resendCurrentOrganizationTeamInvitationMock.mockResolvedValue({
      id: 301,
      delivery_status: 'queued',
    });
    revokeCurrentOrganizationTeamInvitationMock.mockResolvedValue({
      id: 301,
      status: 'revoked',
    });
    removeCurrentOrganizationTeamMemberMock.mockResolvedValue({ success: true });
    transferCurrentOrganizationOwnershipMock.mockResolvedValue({ success: true, data: {} });
    updateCurrentUserPreferencesMock.mockResolvedValue({ success: true, data: {} });
    getMediaIntelligenceGlobalSettingsMock.mockResolvedValue({
      id: 1,
      reply_text_prompt: 'Frase curta, com emoji e baseada na foto.',
      reply_text_fixed_templates: [],
      created_at: null,
      updated_at: null,
    });
    updateMediaIntelligenceGlobalSettingsMock.mockResolvedValue({ success: true, data: {} });
    clipboardWriteTextMock.mockResolvedValue(undefined);
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

  it('loads active team members and pending invitations from the API', async () => {
    renderPage();

    await waitFor(() => {
      expect(listCurrentOrganizationTeamMock).toHaveBeenCalledTimes(1);
      expect(listCurrentOrganizationTeamInvitationsMock).toHaveBeenCalledTimes(1);
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

    expect(screen.queryByRole('tab', { name: /^ia$/i })).not.toBeInTheDocument();
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

    expect(screen.queryByRole('tab', { name: /^ia$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /permiss/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /integrac/i })).not.toBeInTheDocument();
  });

  it('does not query team data when the session cannot manage team access', () => {
    useAuthMock.mockReturnValue(buildAuthMock({
      meUser: {
        id: 1,
        name: 'Leitura',
        email: 'viewer@eventovivo.test',
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
    expect(listCurrentOrganizationTeamInvitationsMock).not.toHaveBeenCalled();
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
      meEntitlements: {
        ...buildAuthMock().meEntitlements,
        branding: {
          white_label: true,
          custom_domain: true,
          expanded_assets: true,
          watermark: true,
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

  it('shows premium branding assets as locked when the plan does not include white-label', async () => {
    renderPage();

    activateTab(/branding/i);

    expect(await screen.findByText(/ativos premium da marca/i)).toBeInTheDocument();
    expect(screen.getByText(/depende do plano/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/enviar capa padrao/i)).toBeDisabled();
    expect(screen.getByLabelText(/enviar logo para fundo escuro/i)).toBeDisabled();
  });

  it('uploads expanded branding assets when the entitlement is available', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      meEntitlements: {
        ...buildAuthMock().meEntitlements,
        branding: {
          white_label: true,
          custom_domain: true,
          expanded_assets: true,
          watermark: true,
        },
      },
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/branding/i);

    const file = new File(['cover'], 'cover.png', { type: 'image/png' });
    fireEvent.change(await screen.findByLabelText(/enviar capa padrao/i), {
      target: { files: [file] },
    });
    fireEvent.click(screen.getAllByRole('button', { name: /enviar ativo/i })[0]);

    await waitFor(() => {
      expect(uploadCurrentOrganizationBrandingAssetMock).toHaveBeenCalledWith('cover', file);
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

  it('requires name whatsapp and perfil before creating an invitation', async () => {
    renderPage();

    activateTab(/equipe/i);
    fireEvent.click(await screen.findByRole('button', { name: /convidar pessoa/i }));

    const submitButton = screen.getByRole('button', { name: /criar convite/i });

    expect(submitButton).toBeDisabled();

    fireEvent.change(await screen.findByPlaceholderText(/nome do membro/i), {
      target: { value: 'Novo Membro' },
    });
    fireEvent.change(screen.getByPlaceholderText(/5511999999999/i), {
      target: { value: '11999998888' },
    });

    expect(submitButton).toBeDisabled();

    fireEvent.change(screen.getByLabelText(/perfil \*/i), {
      target: { value: 'partner-manager' },
    });

    expect(submitButton).toBeEnabled();
  });

  it('uses simple invitation presets and never exposes owner in the generic add flow', async () => {
    renderPage();

    activateTab(/equipe/i);
    fireEvent.click(await screen.findByRole('button', { name: /convidar pessoa/i }));

    expect(screen.getByRole('option', { name: /gerente \/ secretaria/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /operar eventos/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /financeiro/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /acompanhar em leitura/i })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: /proprietario|conta principal/i })).not.toBeInTheDocument();
  });

  it('creates an invitation with explicit WhatsApp delivery preference', async () => {
    renderPage();

    activateTab(/equipe/i);
    fireEvent.click(await screen.findByRole('button', { name: /convidar pessoa/i }));

    fireEvent.change(await screen.findByPlaceholderText(/nome do membro/i), {
      target: { value: 'Novo Membro' },
    });
    fireEvent.change(screen.getByPlaceholderText(/5511999999999/i), {
      target: { value: '11999998888' },
    });
    fireEvent.change(screen.getByLabelText(/perfil \*/i), {
      target: { value: 'partner-manager' },
    });

    fireEvent.click(screen.getByRole('checkbox', { name: /enviar convite pelo whatsapp/i }));
    fireEvent.click(screen.getByRole('button', { name: /criar convite/i }));

    await waitFor(() => {
      expect(inviteCurrentOrganizationTeamMemberMock).toHaveBeenCalledWith({
        user: {
          name: 'Novo Membro',
          email: undefined,
          phone: '11999998888',
        },
        role_key: 'partner-manager',
        send_via_whatsapp: false,
      });
    });
  });

  it('renders pending invitations separately, allows manual copy and supports resend and revoke', async () => {
    renderPage();

    activateTab(/equipe/i);

    expect(await screen.findByText('Membros ativos')).toBeInTheDocument();
    expect(screen.getByText('Convites pendentes')).toBeInTheDocument();
    expect(await screen.findByText('Maria Team')).toBeInTheDocument();
    expect(screen.getByText('Carlos Gestor')).toBeInTheDocument();
    expect(screen.getByText('DJ Bruno')).toBeInTheDocument();
    expect(screen.getByText(/link pronto para copiar/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /copiar link/i }));

    await waitFor(() => {
      expect(clipboardWriteTextMock).toHaveBeenCalledWith('https://eventovivo.test/convites/equipe/token-123');
    });

    fireEvent.click(screen.getByRole('button', { name: /reenviar whatsapp/i }));

    await waitFor(() => {
      expect(resendCurrentOrganizationTeamInvitationMock).toHaveBeenCalledWith(301, true);
    });

    fireEvent.click(screen.getAllByRole('button', { name: /revogar convite/i })[0]);
    fireEvent.click((await screen.findAllByRole('button', { name: /^revogar convite$/i })).at(-1)!);

    await waitFor(() => {
      expect(revokeCurrentOrganizationTeamInvitationMock).toHaveBeenCalledWith(301);
    });
  });

  it('confirms before removing an active non-owner member', async () => {
    renderPage();

    activateTab(/equipe/i);

    expect(screen.queryByRole('button', { name: /remover maria team/i })).not.toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: /remover carlos gestor/i }));
    fireEvent.click(await screen.findByRole('button', { name: /confirmar remocao/i }));

    await waitFor(() => {
      expect(removeCurrentOrganizationTeamMemberMock).toHaveBeenCalledWith(11);
    });
  });

  it('uses a dedicated confirmation flow before transferring organization ownership', async () => {
    const refreshSession = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue(buildAuthMock({
      refreshSession,
      can: (permission: string) =>
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    }));

    renderPage();

    activateTab(/equipe/i);

    fireEvent.click(await screen.findByRole('button', { name: /transferir titularidade para carlos gestor/i }));
    fireEvent.click(await screen.findByRole('button', { name: /confirmar transferencia/i }));

    await waitFor(() => {
      expect(transferCurrentOrganizationOwnershipMock).toHaveBeenCalledWith({ member_id: 11 });
      expect(refreshSession).toHaveBeenCalled();
    });
  });
});

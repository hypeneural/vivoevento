import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import SettingsPage from './SettingsPage';

const useAuthMock = vi.fn();
const listCurrentOrganizationTeamMock = vi.fn();
const listCurrentOrganizationTeamInvitationsMock = vi.fn();
const inviteCurrentOrganizationTeamMemberMock = vi.fn();

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
    updateCurrentOrganization: vi.fn(),
    updateCurrentOrganizationBranding: vi.fn(),
    uploadCurrentOrganizationLogo: vi.fn(),
    inviteCurrentOrganizationTeamMember: (...args: unknown[]) => inviteCurrentOrganizationTeamMemberMock(...args),
    resendCurrentOrganizationTeamInvitation: vi.fn(),
    revokeCurrentOrganizationTeamInvitation: vi.fn(),
    removeCurrentOrganizationTeamMember: vi.fn(),
    updateCurrentUserPreferences: vi.fn(),
    getMediaIntelligenceGlobalSettings: vi.fn().mockResolvedValue({
      id: 1,
      reply_text_prompt: '',
      reply_text_fixed_templates: [],
      created_at: null,
      updated_at: null,
    }),
    updateMediaIntelligenceGlobalSettings: vi.fn(),
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

function activateTeamTab() {
  const tab = screen.getByRole('tab', { name: /equipe/i });
  fireEvent.mouseDown(tab);
  fireEvent.click(tab);
}

describe('Settings team invitation contracts', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
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
        ['team.manage', 'branding.manage', 'settings.manage'].includes(permission),
    });

    listCurrentOrganizationTeamMock.mockResolvedValue({
      success: true,
      data: [
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
          role_key: 'viewer',
          role_label: 'Acompanhar em leitura',
          role_description: 'Pode acompanhar informacoes liberadas sem alterar a configuracao da conta.',
          existing_user_id: null,
          invitee: {
            name: 'Noiva Julia',
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

    inviteCurrentOrganizationTeamMemberMock.mockResolvedValue({
      id: 999,
      delivery_status: 'queued',
      invitation_url: 'https://eventovivo.test/convites/equipe/token-new',
    });
  });

  it('shows a send via WhatsApp option and sends the explicit delivery preference to the API', async () => {
    renderPage();

    activateTeamTab();
    fireEvent.click(await screen.findByRole('button', { name: /convidar pessoa/i }));

    fireEvent.change(await screen.findByPlaceholderText(/nome do membro/i), {
      target: { value: 'Secretaria Nova' },
    });
    fireEvent.change(screen.getByPlaceholderText(/5511999999999/i), {
      target: { value: '11988887777' },
    });
    fireEvent.change(screen.getByLabelText(/perfil \*/i), {
      target: { value: 'partner-manager' },
    });
    fireEvent.click(screen.getByRole('checkbox', { name: /enviar convite pelo whatsapp/i }));
    fireEvent.click(screen.getByRole('button', { name: /criar convite/i }));

    await waitFor(() => {
      expect(inviteCurrentOrganizationTeamMemberMock).toHaveBeenCalledWith(expect.objectContaining({
        send_via_whatsapp: false,
      }));
    });
  });

  it('renders pending invitations separately from active team members and exposes the manual link fallback', async () => {
    renderPage();

    activateTeamTab();

    expect(await screen.findByText('Carlos Gestor')).toBeInTheDocument();
    expect(screen.getByText('Noiva Julia')).toBeInTheDocument();
    expect(screen.getByText(/link pronto para copiar/i)).toBeInTheDocument();
    expect(screen.getByText('https://eventovivo.test/convites/equipe/token-123')).toBeInTheDocument();
  });

  it('keeps owner transfer outside the generic add member flow and uses human friendly presets', async () => {
    renderPage();

    activateTeamTab();
    fireEvent.click(await screen.findByRole('button', { name: /convidar pessoa/i }));

    expect(screen.getByRole('option', { name: /gerente \/ secretaria/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /operar eventos/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /financeiro/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /acompanhar em leitura/i })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: /proprietario|conta principal/i })).not.toBeInTheDocument();
  });
});

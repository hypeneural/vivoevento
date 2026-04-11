import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventAccessPage from './EventAccessPage';

const getEventDetailMock = vi.fn();
const listEventAccessInvitationsMock = vi.fn();
const listEventAccessMembersMock = vi.fn();
const getAccessPresetsMock = vi.fn();
const createEventAccessInvitationMock = vi.fn();
const resendEventAccessInvitationMock = vi.fn();
const revokeEventAccessInvitationMock = vi.fn();
const removeEventAccessMemberMock = vi.fn();
const toastMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
}));

vi.mock('./api', () => ({
  eventAccessApi: {
    listMembers: (...args: unknown[]) => listEventAccessMembersMock(...args),
    listInvitations: (...args: unknown[]) => listEventAccessInvitationsMock(...args),
    getPresets: (...args: unknown[]) => getAccessPresetsMock(...args),
    createInvitation: (...args: unknown[]) => createEventAccessInvitationMock(...args),
    resendInvitation: (...args: unknown[]) => resendEventAccessInvitationMock(...args),
    revokeInvitation: (...args: unknown[]) => revokeEventAccessInvitationMock(...args),
    removeMember: (...args: unknown[]) => removeEventAccessMemberMock(...args),
  },
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

function renderPage(initialEntry = '/events/42/access') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/events/:id/access" element={<EventAccessPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const basePresets = {
  event: [
    {
      key: 'event.operator',
      scope: 'event',
      persisted_role: 'operator',
      label: 'Operar evento',
      description: 'Opera telão e jogos, além de moderar mídias.',
      capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
    },
    {
      key: 'event.media-viewer',
      scope: 'event',
      persisted_role: 'viewer',
      label: 'Ver mídias',
      description: 'Acompanha apenas as mídias deste evento.',
      capabilities: ['overview', 'media'],
    },
  ],
  organization: [],
};

describe('EventAccessPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    getEventDetailMock.mockResolvedValue({
      id: 42,
      title: 'Casamento Ana e Pedro',
      organization_name: 'Cerimonial Aurora',
      status: 'active',
      starts_at: '2026-06-20T18:00:00Z',
    });

    listEventAccessMembersMock.mockResolvedValue([
      {
        id: 7,
        user: {
          id: 99,
          name: 'DJ Bruno',
          email: 'dj-bruno@eventovivo.test',
          phone: '5511998877665',
        },
        role: 'operator',
        role_label: 'Operar evento',
        capabilities: ['overview', 'media', 'moderation', 'wall', 'play'],
      },
    ]);

    listEventAccessInvitationsMock.mockResolvedValue([
      {
        id: 13,
        status: 'pending',
        preset_key: 'event.media-viewer',
        persisted_role: 'viewer',
        role_label: 'Ver mídias',
        capabilities: ['overview', 'media'],
        invitee: {
          name: 'Noiva Ana',
          email: 'ana@eventovivo.test',
          phone: '5511998877666',
        },
        delivery_channel: 'whatsapp',
        delivery_status: 'queued',
        invitation_url: 'https://app.eventovivo.test/convites/eventos/token-13',
        token_expires_at: '2026-06-18T12:00:00Z',
        last_sent_at: '2026-06-11T12:00:00Z',
        revoked_at: null,
      },
    ]);

    getAccessPresetsMock.mockResolvedValue(basePresets);
    createEventAccessInvitationMock.mockResolvedValue({
      id: 14,
      status: 'pending',
    });
    resendEventAccessInvitationMock.mockResolvedValue({
      id: 13,
      status: 'pending',
      delivery_status: 'queued',
    });
    revokeEventAccessInvitationMock.mockResolvedValue({
      id: 13,
      status: 'revoked',
    });
    removeEventAccessMemberMock.mockResolvedValue(null);
  });

  it('renders the event access summary with active members and pending invitations', async () => {
    renderPage();

    expect(await screen.findByText('Acessos do evento')).toBeInTheDocument();
    expect(screen.getByText('Casamento Ana e Pedro')).toBeInTheDocument();
    expect(screen.getByText('DJ Bruno')).toBeInTheDocument();
    expect(screen.getByText('Noiva Ana')).toBeInTheDocument();
    expect(screen.getAllByText('Operar evento')).not.toHaveLength(0);
    expect(screen.getAllByText('Ver mídias')).not.toHaveLength(0);
  });

  it('creates a pending event invitation from the admin dialog and can request WhatsApp delivery', async () => {
    renderPage();

    expect(await screen.findByText('Acessos do evento')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /convidar acesso/i }));

    fireEvent.change(screen.getByLabelText(/nome/i), {
      target: { value: 'Casal Ana e Pedro' },
    });
    fireEvent.change(screen.getByLabelText(/^whatsapp$/i), {
      target: { value: '(11) 99887-7677' },
    });
    fireEvent.change(screen.getByLabelText(/perfil de acesso/i), {
      target: { value: 'event.media-viewer' },
    });
    fireEvent.click(screen.getByLabelText(/enviar convite pelo whatsapp/i));
    fireEvent.click(screen.getByRole('button', { name: /enviar convite/i }));

    await waitFor(() => {
      expect(createEventAccessInvitationMock).toHaveBeenCalledWith('42', {
        invitee: {
          name: 'Casal Ana e Pedro',
          email: '',
          phone: '(11) 99887-7677',
        },
        preset_key: 'event.media-viewer',
        send_via_whatsapp: true,
      });
    });
  });

  it('allows resending, revoking pending invitations and removing active members from the same page', async () => {
    renderPage();

    expect(await screen.findByText('Noiva Ana')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /reenviar whatsapp/i }));

    await waitFor(() => {
      expect(resendEventAccessInvitationMock).toHaveBeenCalledWith('42', 13, {
        send_via_whatsapp: true,
      });
    });

    fireEvent.click(screen.getByRole('button', { name: /revogar convite/i }));
    fireEvent.click(await screen.findByRole('button', { name: /confirmar revogação/i }));

    await waitFor(() => {
      expect(revokeEventAccessInvitationMock).toHaveBeenCalledWith('42', 13);
    });

    fireEvent.click(screen.getByRole('button', { name: /remover dj bruno/i }));
    fireEvent.click(await screen.findByRole('button', { name: /confirmar remoção/i }));

    await waitFor(() => {
      expect(removeEventAccessMemberMock).toHaveBeenCalledWith('42', 7);
    });
  });
});

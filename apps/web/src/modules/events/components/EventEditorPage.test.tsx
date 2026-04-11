import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { EventEditorPage } from './EventEditorPage';

const useAuthMock = vi.fn();
const showEventMock = vi.fn();
const listClientsMock = vi.fn();
const telegramOperationalStatusMock = vi.fn();
const uploadBrandingAssetMock = vi.fn();
const whatsappListMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

vi.mock('../services/events.service', () => ({
  eventsService: {
    show: (...args: unknown[]) => showEventMock(...args),
    listClients: (...args: unknown[]) => listClientsMock(...args),
    telegramOperationalStatus: (...args: unknown[]) => telegramOperationalStatusMock(...args),
    uploadBrandingAsset: (...args: unknown[]) => uploadBrandingAssetMock(...args),
    update: vi.fn(),
    create: vi.fn(),
  },
}));

vi.mock('@/modules/whatsapp/api', () => ({
  whatsappService: {
    list: (...args: unknown[]) => whatsappListMock(...args),
  },
}));

function buildEventDetail() {
  return {
    id: 42,
    uuid: 'event-42',
    organization_id: 7,
    client_id: null,
    title: 'Casamento Ana e Pedro',
    slug: 'casamento-ana-pedro',
    upload_slug: 'envio-casamento-ana-pedro',
    event_type: 'wedding',
    status: 'active',
    visibility: 'public',
    moderation_mode: 'manual',
    commercial_mode: 'subscription_covered',
    starts_at: '2026-05-10T18:00:00.000Z',
    ends_at: '2026-05-11T02:00:00.000Z',
    location_name: 'Villa Aurora',
    description: 'Evento com parceiros e convidados.',
    cover_image_path: null,
    cover_image_url: null,
    logo_path: null,
    logo_url: null,
    qr_code_path: null,
    primary_color: null,
    secondary_color: null,
    inherit_branding: true,
    effective_branding: {
      logo_path: 'organizations/branding/logo-light.webp',
      logo_url: 'https://cdn.example.com/organizations/logo-light.webp',
      cover_image_path: 'organizations/branding/cover.webp',
      cover_image_url: 'https://cdn.example.com/organizations/cover.webp',
      primary_color: '#112233',
      secondary_color: '#445566',
      source: 'organization',
      inherits_from_organization: true,
    },
    public_url: 'https://example.com/e/casamento-ana-pedro',
    upload_url: 'https://example.com/upload/envio-casamento-ana-pedro',
    upload_api_url: 'https://example.com/api/upload/envio-casamento-ana-pedro',
    retention_days: 30,
    current_entitlements: null,
    created_by: 99,
    created_at: '2026-04-10T12:00:00.000Z',
    updated_at: '2026-04-10T13:00:00.000Z',
    organization_name: 'Studio Aurora',
    organization_slug: 'studio-aurora',
    client_name: null,
    content_moderation: null,
    face_search: {
      enabled: false,
      allow_public_selfie_search: false,
      selfie_retention_hours: 24,
      status: 'disabled',
    },
    media_intelligence: null,
    enabled_modules: ['live', 'hub'],
    module_count: 2,
    wall: null,
    client: null,
    modules: [],
    channels: [],
    banners: [],
    team_members: [],
    media_count: 0,
    module_flags: {
      live: true,
      wall: false,
      play: false,
      hub: true,
    },
    menu: [
      {
        key: 'overview',
        label: 'Visao geral',
        visible: true,
      },
    ],
    stats: {
      media_total: 12,
      media_pending: 2,
      media_approved: 8,
      media_published: 7,
      active_modules: 2,
    },
    intake_defaults: null,
    intake_channels: null,
    intake_blacklist: null,
    public_links: {},
    public_identifiers: {},
    play: null,
    hub: {
      id: 4,
      is_enabled: true,
      headline: null,
      subheadline: null,
      show_gallery_button: true,
      show_upload_button: true,
      show_wall_button: false,
      show_play_button: false,
    },
  } as any;
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/events/42/edit']}>
        <Routes>
          <Route path="/events/:id/edit" element={<EventEditorPage mode="edit" />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('EventEditorPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      meOrganization: {
        id: 7,
        uuid: 'org-7',
        name: 'Studio Aurora',
        slug: 'studio-aurora',
        logo_url: 'https://cdn.example.com/organizations/logo-light.webp',
        branding: {
          logo_path: 'organizations/branding/logo-light.webp',
          logo_url: 'https://cdn.example.com/organizations/logo-light.webp',
          cover_path: 'organizations/branding/cover.webp',
          cover_url: 'https://cdn.example.com/organizations/cover.webp',
          primary_color: '#112233',
          secondary_color: '#445566',
        },
      },
      can: () => true,
    });

    showEventMock.mockResolvedValue(buildEventDetail());
    listClientsMock.mockResolvedValue([]);
    telegramOperationalStatusMock.mockResolvedValue(null);
    whatsappListMock.mockResolvedValue({ data: [] });
    uploadBrandingAssetMock.mockResolvedValue({
      kind: 'logo',
      path: 'events/branding/logo.webp',
      url: 'https://cdn.example.com/events/logo.webp',
    });
  });

  it('renders the inherited branding preview and updates the copy when inheritance is turned off', async () => {
    renderPage();

    expect(await screen.findByText(/preview do visual aplicado/i)).toBeInTheDocument();
    expect(screen.getAllByText('Organizacao').length).toBeGreaterThan(0);
    expect(screen.getByText(/visual aproveitando a organizacao/i)).toBeInTheDocument();

    const inheritanceCard = screen.getByText(/usar visual da organizacao/i).closest('div.rounded-3xl');
    expect(inheritanceCard).not.toBeNull();

    const inheritanceSwitch = within(inheritanceCard as HTMLElement).getByRole('switch');
    fireEvent.click(inheritanceSwitch);

    await waitFor(() => {
      expect(screen.getByText(/com esta opcao desligada, o evento usa apenas o que voce preencher aqui/i)).toBeInTheDocument();
      expect(screen.getByText(/visual proprio do evento/i)).toBeInTheDocument();
    });
  });
});

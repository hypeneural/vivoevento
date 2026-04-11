import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventDetailPage from './EventDetailPage';

const getEventDetailMock = vi.fn();
const getEventCommercialStatusMock = vi.fn();
const listEventMediaMock = vi.fn();
const listEventPublicLinkQrEditorStatesMock = vi.fn();

vi.mock('./api', () => ({
  deleteEventIntakeBlacklistEntry: vi.fn(),
  getEventCommercialStatus: (...args: unknown[]) => getEventCommercialStatusMock(...args),
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
  listEventMedia: (...args: unknown[]) => listEventMediaMock(...args),
  regenerateEventPublicIdentifiers: vi.fn(),
  upsertEventIntakeBlacklistEntry: vi.fn(),
  updateEventPublicIdentifiers: vi.fn(),
}));

vi.mock('./qr/api', () => ({
  getEventPublicLinkQrEditorQueryKey: (eventId: string | number, linkKey: string) => ['event-public-link-qr-editor', String(eventId), linkKey],
  getEventPublicLinkQrListQueryKey: (eventId: string | number) => ['event-public-link-qr-list', String(eventId)],
  listEventPublicLinkQrEditorStates: (...args: unknown[]) => listEventPublicLinkQrEditorStatesMock(...args),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

vi.mock('@/shared/hooks/usePermissions', () => ({
  usePermissions: () => ({
    can: () => true,
  }),
}));

function renderEventDetailPage(initialEntry = '/events/42') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter
        initialEntries={[initialEntry]}
        future={{ v7_relativeSplatPath: true, v7_startTransition: true }}
      >
        <Routes>
          <Route path="/events/:id" element={<EventDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function buildEventDetailMock() {
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
    public_links: {
      gallery: {
        key: 'gallery',
        label: 'Galeria',
        enabled: true,
        identifier_type: 'slug',
        identifier: 'casamento-ana-pedro',
        url: 'https://example.com/gallery/casamento-ana-pedro',
        api_url: null,
        qr_value: null,
      },
      upload: {
        key: 'upload',
        label: 'Upload',
        enabled: true,
        identifier_type: 'upload_slug',
        identifier: 'envio-casamento-ana-pedro',
        url: 'https://example.com/upload/envio-casamento-ana-pedro',
        api_url: null,
        qr_value: null,
      },
      wall: {
        key: 'wall',
        label: 'Telao',
        enabled: false,
        identifier_type: 'wall_code',
        identifier: null,
        url: null,
        api_url: null,
        qr_value: null,
      },
      hub: {
        key: 'hub',
        label: 'Hub',
        enabled: true,
        identifier_type: 'slug',
        identifier: 'casamento-ana-pedro',
        url: 'https://example.com/e/casamento-ana-pedro',
        api_url: null,
        qr_value: null,
      },
      play: {
        key: 'play',
        label: 'Jogos',
        enabled: false,
        identifier_type: 'slug',
        identifier: null,
        url: null,
        api_url: null,
        qr_value: null,
      },
      find_me: {
        key: 'find_me',
        label: 'Encontrar minhas fotos',
        enabled: false,
        identifier_type: 'slug',
        identifier: null,
        url: null,
        api_url: null,
        qr_value: null,
      },
    },
    public_identifiers: {
      slug: {
        value: 'casamento-ana-pedro',
        editable: true,
        regenerates: ['gallery', 'hub', 'find_me'],
      },
      upload_slug: {
        value: 'envio-casamento-ana-pedro',
        editable: true,
        regenerates: ['upload'],
      },
      wall_code: {
        value: null,
        editable: true,
        regenerates: ['wall'],
      },
    },
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
  };
}

describe('EventDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('keeps the loading screen stable before the event payload exists', async () => {
    getEventDetailMock.mockReturnValue(new Promise(() => {}));
    getEventCommercialStatusMock.mockReturnValue(new Promise(() => {}));
    listEventPublicLinkQrEditorStatesMock.mockResolvedValue([]);

    const { container } = renderEventDetailPage();

    await waitFor(() => {
      expect(getEventDetailMock).toHaveBeenCalledWith('42');
    });

    expect(container.querySelector('.animate-spin')).toBeInTheDocument();
  });

  it('renders the effective branding preview and source summary', async () => {
    getEventDetailMock.mockResolvedValue(buildEventDetailMock());
    getEventCommercialStatusMock.mockResolvedValue(null);
    listEventPublicLinkQrEditorStatesMock.mockResolvedValue([]);
    listEventMediaMock.mockResolvedValue({
      data: [],
      meta: { page: 1, per_page: 24, total: 0, last_page: 1, request_id: 'req-1' },
    });

    renderEventDetailPage();

    expect(await screen.findByText('Visual aplicado')).toBeInTheDocument();
    expect(screen.getAllByText('Organizacao').length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Este evento aproveita automaticamente a identidade visual da Studio Aurora/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText('Usando visual da organizacao').length).toBeGreaterThan(0);
    expect(screen.getByText('#112233')).toBeInTheDocument();
    expect(screen.getByText('#445566')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /organizar pessoas/i })).toHaveAttribute('href', '/events/42/people');
  });
});

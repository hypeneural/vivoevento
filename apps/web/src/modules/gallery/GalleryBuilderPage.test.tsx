import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  createGalleryAiProposalsFixture,
  createGalleryBuilderPresetFixture,
  createGalleryBuilderRevisionFixture,
  createGalleryBuilderSettingsFixture,
  galleryContractCatalog,
} from './gallery-builder';
import GalleryBuilderPage from './GalleryBuilderPage';

const getEventGallerySettingsMock = vi.fn();
const listGalleryPresetsMock = vi.fn();
const listEventGalleryRevisionsMock = vi.fn();
const updateEventGallerySettingsMock = vi.fn();
const autosaveEventGalleryDraftMock = vi.fn();
const publishEventGalleryDraftMock = vi.fn();
const restoreEventGalleryRevisionMock = vi.fn();
const createEventGalleryPreviewLinkMock = vi.fn();
const runEventGalleryAiProposalsMock = vi.fn();
const getEventDetailMock = vi.fn();
const listEventMediaMock = vi.fn();

vi.mock('./api', () => ({
  getEventGallerySettings: (...args: unknown[]) => getEventGallerySettingsMock(...args),
  listGalleryPresets: (...args: unknown[]) => listGalleryPresetsMock(...args),
  listEventGalleryRevisions: (...args: unknown[]) => listEventGalleryRevisionsMock(...args),
  updateEventGallerySettings: (...args: unknown[]) => updateEventGallerySettingsMock(...args),
  autosaveEventGalleryDraft: (...args: unknown[]) => autosaveEventGalleryDraftMock(...args),
  publishEventGalleryDraft: (...args: unknown[]) => publishEventGalleryDraftMock(...args),
  restoreEventGalleryRevision: (...args: unknown[]) => restoreEventGalleryRevisionMock(...args),
  createEventGalleryPreviewLink: (...args: unknown[]) => createEventGalleryPreviewLinkMock(...args),
  runEventGalleryAiProposals: (...args: unknown[]) => runEventGalleryAiProposalsMock(...args),
}));

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
  listEventMedia: (...args: unknown[]) => listEventMediaMock(...args),
}));

vi.mock('./components/GalleryPreviewFrame', () => ({
  GalleryPreviewFrame: ({ viewport }: { viewport: string }) => (
    <div data-testid="gallery-preview-frame" data-viewport={viewport}>
      Preview mockado
    </div>
  ),
}));

function renderPage(initialEntry = '/events/42/gallery/builder') {
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
          <Route path="/events/:id/gallery/builder" element={<GalleryBuilderPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('GalleryBuilderPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    const settings = createGalleryBuilderSettingsFixture();

    getEventGallerySettingsMock.mockResolvedValue({
      event: {
        id: 42,
        title: 'Casamento Ana e Leo',
        slug: 'casamento-ana-leo',
      },
      settings,
      mobile_budget: galleryContractCatalog.mobileBudget,
      responsive_source_contract: {
        sizes: galleryContractCatalog.publicResponsiveSizes,
        required_variant_fields: ['variant_key', 'src', 'width', 'height', 'mime_type'],
        target_widths: [320, 480, 768, 1024, 1440],
      },
    });
    listGalleryPresetsMock.mockResolvedValue([createGalleryBuilderPresetFixture()]);
    listEventGalleryRevisionsMock.mockResolvedValue([
      createGalleryBuilderRevisionFixture(),
      createGalleryBuilderRevisionFixture({
        id: 202,
        version_number: 6,
        kind: 'publish',
      }),
    ]);
    updateEventGallerySettingsMock.mockResolvedValue({ settings });
    autosaveEventGalleryDraftMock.mockResolvedValue({
      settings,
      revision: createGalleryBuilderRevisionFixture(),
    });
    publishEventGalleryDraftMock.mockResolvedValue({
      settings: {
        ...settings,
        published_version: 7,
      },
      revision: createGalleryBuilderRevisionFixture({
        id: 204,
        kind: 'publish',
      }),
    });
    restoreEventGalleryRevisionMock.mockResolvedValue({
      settings,
      revision: createGalleryBuilderRevisionFixture({
        id: 205,
        kind: 'restore',
      }),
    });
    createEventGalleryPreviewLinkMock.mockResolvedValue({
      token: 'preview-token',
      preview_url: 'https://eventovivo.test/api/v1/public/gallery-previews/preview-token',
      expires_at: '2026-04-19T12:00:00Z',
      revision: createGalleryBuilderRevisionFixture(),
    });
    runEventGalleryAiProposalsMock.mockResolvedValue(createGalleryAiProposalsFixture());
    getEventDetailMock.mockResolvedValue({
      id: 42,
      title: 'Casamento Ana e Leo',
      slug: 'casamento-ana-leo',
      event_type: 'wedding',
      status: 'active',
      location_name: 'Sao Paulo',
      description: null,
      starts_at: null,
      ends_at: null,
      organization_id: 10,
      client_id: null,
      uuid: 'evt-1',
      upload_slug: 'upload',
      visibility: 'public',
      moderation_mode: 'manual',
      cover_image_path: null,
      cover_image_url: null,
      logo_path: null,
      logo_url: null,
      primary_color: '#be185d',
      secondary_color: '#f59e0b',
      inherit_branding: false,
      effective_branding: null,
      public_url: null,
      upload_url: null,
      created_at: null,
      organization_name: 'Evento Vivo',
      client_name: null,
      enabled_modules: ['live'],
      media_count: 12,
      wall: null,
      qr_code_path: null,
      upload_api_url: null,
      retention_days: 30,
      created_by: 9,
      updated_at: null,
      organization_slug: 'evento-vivo',
      module_count: 1,
    });
    listEventMediaMock.mockResolvedValue({
      data: [
        {
          id: 1,
          event_id: 42,
          media_type: 'image',
          channel: 'upload',
          status: 'published',
          processing_status: null,
          moderation_status: 'approved',
          publication_status: 'published',
          thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
          preview_url: 'https://cdn.eventovivo.test/gallery.webp',
          original_url: 'https://cdn.eventovivo.test/original.jpg',
          caption: 'Noiva entrando',
          sender_name: 'Convidado',
          created_at: null,
          published_at: null,
          is_featured: false,
          width: 1200,
          height: 800,
          orientation: 'landscape',
          responsive_sources: null,
        },
      ],
      meta: {
        page: 1,
        per_page: 24,
        total: 1,
        last_page: 1,
      },
    });
  });

  it('renders the builder with quick mode, central preview and revision context', async () => {
    renderPage();

    expect(await screen.findByRole('heading', { name: 'Gallery Builder' })).toBeInTheDocument();
    expect(screen.getAllByText('Casamento / Romantico / Historia').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Draft v7').length).toBeGreaterThan(0);
    expect(screen.getByTestId('gallery-preview-frame')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /modo rapido/i })).toHaveAttribute('aria-pressed', 'true');
  });

  it('switches to professional mode and exposes preset tooling', async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole('heading', { name: 'Gallery Builder' });
    await user.click(screen.getByRole('button', { name: /modo profissional/i }));

    expect(screen.getByRole('button', { name: /modo profissional/i })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText(/Presets da organizacao/i)).toBeInTheDocument();
    expect(screen.getByText(/Tema e paleta/i)).toBeInTheDocument();
  });

  it('generates a shareable preview link from the revision panel flow', async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole('heading', { name: 'Gallery Builder' });
    await user.click(screen.getByRole('button', { name: /gerar preview compartilhavel/i }));

    await waitFor(() => {
      expect(createEventGalleryPreviewLinkMock).toHaveBeenCalledWith('42');
    });

    expect(await screen.findByDisplayValue('https://eventovivo.test/api/v1/public/gallery-previews/preview-token')).toBeInTheDocument();
  });

  it('runs ai proposals and applies a palette-only variation with preview guard enabled', async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole('heading', { name: 'Gallery Builder' });

    await user.type(screen.getByLabelText('Pedido da IA'), 'quero uma galeria romantica em tons rose');
    await user.click(screen.getByRole('button', { name: /gerar 3 variacoes seguras/i }));

    await waitFor(() => {
      expect(runEventGalleryAiProposalsMock).toHaveBeenCalledWith('42', expect.objectContaining({
        prompt_text: 'quero uma galeria romantica em tons rose',
        target_layer: 'mixed',
      }));
    });

    await user.click(screen.getAllByRole('button', { name: /so paleta/i })[0]);

    await waitFor(() => {
      expect(updateEventGallerySettingsMock).toHaveBeenCalled();
      expect(autosaveEventGalleryDraftMock).toHaveBeenCalledWith('42');
    });

    expect(screen.getByRole('button', { name: 'Publicar' })).toBeDisabled();
    expect(screen.getByText(/Gere um preview compartilhavel apos aplicar uma variacao de IA./i)).toBeInTheDocument();
  });
});

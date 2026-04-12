import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  createGalleryAiProposalsFixture,
  createGalleryBuilderOperationalFeedbackFixture,
  createGalleryBuilderPresetFixture,
  createGalleryBuilderRevisionFixture,
  createGalleryBuilderSettingsFixture,
  createGalleryOptimizedRendererTriggerFixture,
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
const trackEventGalleryBuilderTelemetryMock = vi.fn();
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
  trackEventGalleryBuilderTelemetry: (...args: unknown[]) => trackEventGalleryBuilderTelemetryMock(...args),
}));

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
  listEventMedia: (...args: unknown[]) => listEventMediaMock(...args),
}));

vi.mock('./components/GalleryPreviewFrame', () => ({
  GalleryPreviewFrame: () => (
    <div data-testid="gallery-preview-frame" role="region" aria-label="Preview da galeria">
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

describe('gallery builder accessibility', () => {
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
      optimized_renderer_trigger: createGalleryOptimizedRendererTriggerFixture(),
      operational_feedback: createGalleryBuilderOperationalFeedbackFixture({
        current_preset_origin: null,
        last_ai_application: null,
        last_publish: null,
        last_restore: null,
      }),
    });
    listGalleryPresetsMock.mockResolvedValue([createGalleryBuilderPresetFixture()]);
    listEventGalleryRevisionsMock.mockResolvedValue([createGalleryBuilderRevisionFixture()]);
    updateEventGallerySettingsMock.mockResolvedValue({ settings });
    autosaveEventGalleryDraftMock.mockResolvedValue({
      settings,
      revision: createGalleryBuilderRevisionFixture(),
    });
    publishEventGalleryDraftMock.mockResolvedValue({
      settings,
      revision: createGalleryBuilderRevisionFixture({
        id: 301,
        kind: 'publish',
      }),
    });
    restoreEventGalleryRevisionMock.mockResolvedValue({
      settings,
      revision: createGalleryBuilderRevisionFixture({
        id: 302,
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
    trackEventGalleryBuilderTelemetryMock.mockResolvedValue({
      current_preset_origin: null,
      operational_feedback: createGalleryBuilderOperationalFeedbackFixture(),
    });
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
      media_count: 0,
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
      data: [],
      meta: {
        page: 1,
        per_page: 24,
        total: 0,
        last_page: 1,
      },
    });
  });

  it('renders named regions and live feedback channels for the builder', async () => {
    renderPage();

    expect(await screen.findByRole('heading', { name: 'Gallery Builder' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent(/Builder pronto para edicao/i);
    expect(screen.getByRole('region', { name: /comandos do preview da galeria/i })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: /historico de revisoes e preview compartilhavel/i })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: /assistente de IA da galeria/i })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: /^Preview da galeria$/i })).toBeInTheDocument();
  });

  it('announces a publish-blocking alert after applying an AI variation', async () => {
    const user = userEvent.setup();

    renderPage();

    await screen.findByRole('heading', { name: 'Gallery Builder' });
    await user.type(screen.getByLabelText('Pedido da IA'), 'quero uma galeria romantica em tons rose');
    await user.click(screen.getByRole('button', { name: /gerar 3 variacoes seguras/i }));
    await user.click(screen.getAllByRole('button', { name: /aplicar so paleta na variacao/i })[0]);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/Preview obrigatorio antes de publicar/i);
    });
  }, 10000);
});

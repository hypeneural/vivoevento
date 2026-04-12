import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
  createGalleryBuilderSettingsFixture,
  galleryContractCatalog,
} from './gallery-builder';
import GalleryBuilderPage from './GalleryBuilderPage';

const getEventGallerySettingsMock = vi.fn();
const listGalleryPresetsMock = vi.fn();
const listEventGalleryRevisionsMock = vi.fn();
const getEventDetailMock = vi.fn();
const listEventMediaMock = vi.fn();

vi.mock('./api', () => ({
  getEventGallerySettings: (...args: unknown[]) => getEventGallerySettingsMock(...args),
  listGalleryPresets: (...args: unknown[]) => listGalleryPresetsMock(...args),
  listEventGalleryRevisions: (...args: unknown[]) => listEventGalleryRevisionsMock(...args),
  updateEventGallerySettings: vi.fn(),
  autosaveEventGalleryDraft: vi.fn(),
  publishEventGalleryDraft: vi.fn(),
  restoreEventGalleryRevision: vi.fn(),
  createEventGalleryPreviewLink: vi.fn(),
}));

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
  listEventMedia: (...args: unknown[]) => listEventMediaMock(...args),
}));

vi.mock('./components/GalleryPreviewFrame', () => ({
  GalleryPreviewFrame: () => <div>Preview mockado</div>,
}));

function renderPage(initialEntry = '/events/88/gallery/builder') {
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

describe('gallery builder route', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    getEventGallerySettingsMock.mockResolvedValue({
      event: { id: 88, title: 'Evento 88', slug: 'evento-88' },
      settings: createGalleryBuilderSettingsFixture({ event_id: 88 }),
      mobile_budget: galleryContractCatalog.mobileBudget,
      responsive_source_contract: {
        sizes: galleryContractCatalog.publicResponsiveSizes,
        required_variant_fields: ['variant_key', 'src', 'width', 'height', 'mime_type'],
        target_widths: [320, 480, 768, 1024, 1440],
      },
    });
    listGalleryPresetsMock.mockResolvedValue([]);
    listEventGalleryRevisionsMock.mockResolvedValue([]);
    getEventDetailMock.mockResolvedValue({
      id: 88,
      title: 'Evento 88',
      slug: 'evento-88',
      event_type: 'wedding',
      status: 'active',
      location_name: null,
      description: null,
      starts_at: null,
      ends_at: null,
      organization_id: 10,
      client_id: null,
      uuid: 'evt-88',
      upload_slug: 'upload-88',
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

  it('reads the event id from /events/:id/gallery/builder', async () => {
    renderPage();

    await waitFor(() => {
      expect(getEventGallerySettingsMock).toHaveBeenCalledWith('88');
    });
  });
});

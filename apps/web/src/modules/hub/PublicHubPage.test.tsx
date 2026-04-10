import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicHubPage from './PublicHubPage';

const getPublicHubMock = vi.fn();

vi.mock('./api', () => ({
  getPublicHub: (...args: unknown[]) => getPublicHubMock(...args),
}));

vi.mock('./HubRenderer', () => ({
  HubRenderer: ({ event }: { event: { title: string } }) => <div>Hub mockado: {event.title}</div>,
}));

function renderPage(initialEntry = '/e/casamento') {
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
          <Route path="/e/:slug" element={<PublicHubPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicHubPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows the public facial-search CTA when the event allows guests to find their photos', async () => {
    getPublicHubMock.mockResolvedValue({
      event: {
        id: 10,
        title: 'Casamento',
        slug: 'casamento',
        starts_at: null,
        location_name: null,
        description: null,
        cover_image_path: null,
        cover_image_url: null,
        logo_path: null,
        logo_url: null,
        primary_color: '#111827',
        secondary_color: '#22c55e',
        public_url: 'https://eventovivo.test/e/casamento',
      },
      hub: {
        headline: 'Casamento',
        subheadline: null,
        welcome_text: null,
        hero_image_url: null,
        button_style: {
          background_color: '#111827',
          text_color: '#ffffff',
          outline_color: '#22c55e',
        },
        builder_config: {
          version: 1,
          layout_key: 'classic-cover',
          theme_key: 'midnight',
          theme_tokens: {
            page_background: '#020617',
            page_accent: '#22c55e',
            surface_background: '#0f172a',
            surface_border: '#1e293b',
            text_primary: '#ffffff',
            text_secondary: '#cbd5e1',
            hero_overlay_color: '#020617',
          },
          block_order: ['hero', 'cta_list'],
          blocks: {
            hero: { enabled: true, show_logo: true, show_badge: true, show_meta_cards: true, height: 'md', overlay_opacity: 52 },
            meta_cards: { enabled: true, show_date: true, show_location: true, style: 'glass' },
            welcome: { enabled: false, style: 'card' },
            countdown: { enabled: false, style: 'cards', target_mode: 'event_start', target_at: null, title: 'Contagem', completed_message: 'Ja comecou', hide_after_start: false },
            info_grid: { enabled: false, title: null, style: 'cards', columns: 2, items: [] },
            cta_list: { enabled: true, style: 'solid', size: 'md', icon_position: 'left' },
            social_strip: { enabled: false, style: 'icons', size: 'md', items: [] },
            sponsor_strip: { enabled: false, title: null, style: 'logos', items: [] },
          },
        },
        buttons: [],
      },
      face_search: {
        public_search_enabled: true,
        find_me_url: 'https://eventovivo.test/e/casamento/find-me',
        gallery_url: 'https://eventovivo.test/e/casamento/gallery',
      },
    });

    renderPage();

    expect(await screen.findByText('Hub mockado: Casamento')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /encontrar minhas fotos/i })).toHaveAttribute(
      'href',
      'https://eventovivo.test/e/casamento/find-me',
    );
  });

  it('keeps the hub without the extra cta when public facial search is not enabled', async () => {
    getPublicHubMock.mockResolvedValue({
      event: {
        id: 10,
        title: 'Casamento',
        slug: 'casamento',
        starts_at: null,
        location_name: null,
        description: null,
        cover_image_path: null,
        cover_image_url: null,
        logo_path: null,
        logo_url: null,
        primary_color: '#111827',
        secondary_color: '#22c55e',
        public_url: 'https://eventovivo.test/e/casamento',
      },
      hub: {
        headline: 'Casamento',
        subheadline: null,
        welcome_text: null,
        hero_image_url: null,
        button_style: {
          background_color: '#111827',
          text_color: '#ffffff',
          outline_color: '#22c55e',
        },
        builder_config: {
          version: 1,
          layout_key: 'classic-cover',
          theme_key: 'midnight',
          theme_tokens: {
            page_background: '#020617',
            page_accent: '#22c55e',
            surface_background: '#0f172a',
            surface_border: '#1e293b',
            text_primary: '#ffffff',
            text_secondary: '#cbd5e1',
            hero_overlay_color: '#020617',
          },
          block_order: ['hero', 'cta_list'],
          blocks: {
            hero: { enabled: true, show_logo: true, show_badge: true, show_meta_cards: true, height: 'md', overlay_opacity: 52 },
            meta_cards: { enabled: true, show_date: true, show_location: true, style: 'glass' },
            welcome: { enabled: false, style: 'card' },
            countdown: { enabled: false, style: 'cards', target_mode: 'event_start', target_at: null, title: 'Contagem', completed_message: 'Ja comecou', hide_after_start: false },
            info_grid: { enabled: false, title: null, style: 'cards', columns: 2, items: [] },
            cta_list: { enabled: true, style: 'solid', size: 'md', icon_position: 'left' },
            social_strip: { enabled: false, style: 'icons', size: 'md', items: [] },
            sponsor_strip: { enabled: false, title: null, style: 'logos', items: [] },
          },
        },
        buttons: [],
      },
      face_search: {
        public_search_enabled: false,
        find_me_url: null,
        gallery_url: 'https://eventovivo.test/e/casamento/gallery',
      },
    });

    renderPage();

    expect(await screen.findByText('Hub mockado: Casamento')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /encontrar minhas fotos/i })).not.toBeInTheDocument();
  });
});

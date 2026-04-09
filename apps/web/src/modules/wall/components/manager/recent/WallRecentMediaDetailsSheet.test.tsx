import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { WallRecentMediaDetailsSheet } from './WallRecentMediaDetailsSheet';

const useIsMobileMock = vi.fn();

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

const recentItem = {
  id: 'recent-carla',
  previewUrl: 'https://cdn.example.com/recent-carla.jpg',
  senderName: 'Carla',
  senderKey: 'whatsapp:5511999990001',
  source: 'whatsapp',
  createdAt: '2026-04-02T20:58:00Z',
  approvedAt: '2026-04-02T20:58:20Z',
  displayedAt: null,
  status: 'queued' as const,
  isFeatured: true,
  isVideo: true,
  durationSeconds: 34,
  videoPolicyLabel: 'Video longo com politica especial',
  videoAdmission: {
    state: 'eligible_with_fallback' as const,
    reasons: ['variant_missing', 'poster_missing'],
    has_minimum_metadata: true,
    supported_format: true,
    preferred_variant_available: false,
    preferred_variant_key: null,
    poster_available: false,
    poster_variant_key: null,
    asset_source: 'original' as const,
    duration_limit_seconds: 30,
  },
  servedVariantKey: null,
  previewVariantKey: null,
  isReplay: false,
};

describe('WallRecentMediaDetailsSheet', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('usa sheet lateral no desktop', async () => {
    useIsMobileMock.mockReturnValue(false);

    render(
      <WallRecentMediaDetailsSheet
        open
        item={recentItem}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-recent-media-details-sheet')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da midia recente/i)).toBeInTheDocument();
    expect(screen.getByText(/Carla/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Video 34s/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Video longo com politica especial/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Elegivel com fallback/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Variante otimizada de wall ainda indisponivel/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Poster de seguranca ainda indisponivel/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Arquivo original/i)).toBeInTheDocument();
  });

  it('usa drawer no mobile', async () => {
    useIsMobileMock.mockReturnValue(true);

    render(
      <WallRecentMediaDetailsSheet
        open
        item={recentItem}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-recent-media-details-drawer')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da midia recente/i)).toBeInTheDocument();
  });
});

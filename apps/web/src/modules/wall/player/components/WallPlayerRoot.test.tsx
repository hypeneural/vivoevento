import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { WallPlayerRoot } from './WallPlayerRoot';

const useWallPlayerMock = vi.fn();
const usePerformanceModeMock = vi.fn();
const useAdEngineMock = vi.fn();
const useSideThumbnailsMock = vi.fn();

vi.mock('../hooks/useWallPlayer', () => ({
  useWallPlayer: (...args: unknown[]) => useWallPlayerMock(...args),
}));

vi.mock('../hooks/usePerformanceMode', () => ({
  usePerformanceMode: (...args: unknown[]) => usePerformanceModeMock(...args),
}));

vi.mock('../hooks/useAdEngine', () => ({
  useAdEngine: (...args: unknown[]) => useAdEngineMock(...args),
}));

vi.mock('../hooks/useSideThumbnails', () => ({
  useSideThumbnails: (...args: unknown[]) => useSideThumbnailsMock(...args),
}));

vi.mock('./AdOverlay', () => ({
  default: ({ ad }: { ad: { id: number } }) => <div>ad-overlay-{ad.id}</div>,
}));

vi.mock('./BrandingOverlay', () => ({
  default: () => <div data-testid="branding-overlay" />,
}));

vi.mock('./ConnectionOverlay', () => ({
  default: () => null,
}));

vi.mock('./ExpiredScreen', () => ({
  default: ({ title }: { title?: string }) => <div>{title ?? 'expired-screen'}</div>,
}));

vi.mock('./FeaturedBadge', () => ({
  default: () => null,
}));

vi.mock('./IdleScreen', () => ({
  default: () => <div>idle-screen</div>,
}));

vi.mock('./LayoutRenderer', () => ({
  default: ({ media }: { media: { id: string } }) => <div>layout-renderer-{media.id}</div>,
}));

vi.mock('./NewPhotoToast', () => ({
  NewPhotoToast: () => null,
  useNewPhotoToast: () => ({ visible: false, message: '' }),
}));

vi.mock('./PlayerShell', () => ({
  default: ({ children }: { children: ReactNode }) => <div>{children}</div>,
}));

vi.mock('./SideThumbnails', () => ({
  default: () => null,
}));

describe('WallPlayerRoot', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    usePerformanceModeMock.mockReturnValue({
      reducedEffects: false,
      modeLabel: 'Padrao',
    });

    useSideThumbnailsMock.mockReturnValue({
      enabled: false,
      leftItems: [],
      rightItems: [],
    });

    useWallPlayerMock.mockReturnValue({
      state: {
        status: 'playing',
        settings: {
          layout: 'fullscreen',
          transition_effect: 'fade',
          interval_ms: 8000,
          queue_limit: 20,
          selection_mode: 'balanced',
          event_phase: 'flow',
          selection_policy: {
            max_eligible_items_per_sender: 4,
            max_replays_per_item: 2,
            low_volume_max_items: 6,
            medium_volume_max_items: 12,
            replay_interval_low_minutes: 8,
            replay_interval_medium_minutes: 12,
            replay_interval_high_minutes: 20,
            sender_cooldown_seconds: 60,
            sender_window_limit: 3,
            sender_window_minutes: 10,
            avoid_same_sender_if_alternative_exists: true,
            avoid_same_duplicate_cluster_if_alternative_exists: true,
          },
          background_url: null,
          partner_logo_url: null,
          show_qr: true,
          show_branding: true,
          show_neon: false,
          neon_text: null,
          neon_color: '#ffffff',
          show_sender_credit: false,
          show_side_thumbnails: false,
          accepted_orientation: 'all',
          ad_mode: 'by_photos',
          ad_frequency: 2,
          ad_interval_minutes: 3,
          instructions_text: null,
        },
        items: [
          {
            id: 'media_1',
            url: 'https://cdn.example.com/media-1.jpg',
            type: 'image',
            sender_name: 'Maria',
            sender_key: 'sender-maria',
            senderKey: 'sender-maria',
            source_type: 'whatsapp',
            caption: 'Legenda',
            duplicate_cluster_key: null,
            duplicateClusterKey: null,
            is_featured: false,
            created_at: '2026-04-07T10:00:00Z',
            assetStatus: 'ready',
            playedAt: null,
            playCount: 0,
            lastError: null,
            orientation: 'horizontal',
            width: 1600,
            height: 900,
          },
        ],
        currentItemId: 'media_1',
      },
      currentItem: {
        id: 'media_1',
        url: 'https://cdn.example.com/media-1.jpg',
        type: 'image',
        sender_name: 'Maria',
        sender_key: 'sender-maria',
        senderKey: 'sender-maria',
        source_type: 'whatsapp',
        caption: 'Legenda',
        duplicate_cluster_key: null,
        duplicateClusterKey: null,
        is_featured: false,
        created_at: '2026-04-07T10:00:00Z',
        assetStatus: 'ready',
        playedAt: null,
        playCount: 0,
        lastError: null,
        orientation: 'horizontal',
        width: 1600,
        height: 900,
      },
      isSyncing: false,
      errorMessage: null,
      connectionStatus: 'connected',
      lastSyncAt: '2026-04-07T10:00:00Z',
      ads: [
        {
          id: 1,
          url: 'https://cdn.example.com/ad-1.jpg',
          media_type: 'image',
          duration_seconds: 10,
          position: 0,
        },
      ],
    });
  });

  it('renders the layout when no ad is active', () => {
    useAdEngineMock.mockReturnValue({
      currentAd: null,
      onAdFinished: vi.fn(),
      updateAds: vi.fn(),
    });

    render(<WallPlayerRoot code="ABCD1234" />);

    expect(screen.getByText('layout-renderer-media_1')).toBeInTheDocument();
    expect(screen.queryByText('ad-overlay-1')).not.toBeInTheDocument();
  });

  it('renders the ad overlay when an ad is active', () => {
    useAdEngineMock.mockReturnValue({
      currentAd: {
        id: 1,
        url: 'https://cdn.example.com/ad-1.jpg',
        media_type: 'image',
        duration_seconds: 10,
        position: 0,
      },
      onAdFinished: vi.fn(),
      updateAds: vi.fn(),
    });

    render(<WallPlayerRoot code="ABCD1234" />);

    expect(screen.getByText('ad-overlay-1')).toBeInTheDocument();
    expect(screen.queryByText('layout-renderer-media_1')).not.toBeInTheDocument();
  });
});

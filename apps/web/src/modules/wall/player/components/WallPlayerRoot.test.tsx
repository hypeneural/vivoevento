import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { WallPlayerRoot } from './WallPlayerRoot';

const useWallPlayerMock = vi.fn();
const usePerformanceModeMock = vi.fn();
const useSideThumbnailsMock = vi.fn();
const motionConfigMock = vi.fn();

vi.mock('framer-motion', async () => {
  const actual = await vi.importActual<typeof import('framer-motion')>('framer-motion');

  return {
    ...actual,
    MotionConfig: ({ children, ...props }: { children: ReactNode; reducedMotion?: string; transition?: object }) => {
      motionConfigMock(props);

      return <div data-testid="motion-config">{children}</div>;
    },
  };
});

vi.mock('../hooks/useWallPlayer', () => ({
  useWallPlayer: (...args: unknown[]) => useWallPlayerMock(...args),
}));

vi.mock('../hooks/usePerformanceMode', () => ({
  usePerformanceMode: (...args: unknown[]) => usePerformanceModeMock(...args),
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

function makeBasePlayerState() {
  return {
    status: 'playing' as const,
    settings: {
      layout: 'fullscreen' as const,
      transition_effect: 'fade' as const,
      interval_ms: 8000,
      queue_limit: 20,
      selection_mode: 'balanced' as const,
      event_phase: 'flow' as const,
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
      accepted_orientation: 'all' as const,
      video_enabled: true,
      video_playback_mode: 'play_to_end_if_short_else_cap' as const,
      video_max_seconds: 15,
      video_resume_mode: 'resume_if_same_item_else_restart' as const,
      video_audio_policy: 'muted' as const,
      video_multi_layout_policy: 'disallow' as const,
      video_preferred_variant: 'wall_video_720p' as const,
      ad_mode: 'by_photos' as const,
      ad_frequency: 2,
      ad_interval_minutes: 3,
      instructions_text: null,
    },
    items: [
      {
        id: 'media_1',
        url: 'https://cdn.example.com/media-1.jpg',
        type: 'image' as const,
        sender_name: 'Maria',
        sender_key: 'sender-maria',
        senderKey: 'sender-maria',
        source_type: 'whatsapp',
        caption: 'Legenda',
        duplicate_cluster_key: null,
        duplicateClusterKey: null,
        is_featured: false,
        created_at: '2026-04-07T10:00:00Z',
        assetStatus: 'ready' as const,
        playedAt: null,
        playCount: 0,
        lastError: null,
        orientation: 'horizontal' as const,
        width: 1600,
        height: 900,
      },
    ],
    ads: [
      {
        id: 1,
        url: 'https://cdn.example.com/ad-1.jpg',
        media_type: 'image' as const,
        duration_seconds: 10,
        position: 0,
      },
    ],
    currentAd: null,
    adBaseItemId: null,
    adScheduler: {
      mode: 'by_photos' as const,
      frequency: 2,
      photosSinceLastAd: 0,
      lastAdPlayedAt: null,
      lastAdIndex: -1,
      skipNextAdCheck: false,
    },
    currentItemId: 'media_1',
  };
}

function makeBasePlayerReturn() {
  return {
    state: makeBasePlayerState(),
    currentItem: {
      id: 'media_1',
      url: 'https://cdn.example.com/media-1.jpg',
      type: 'image' as const,
      sender_name: 'Maria',
      sender_key: 'sender-maria',
      senderKey: 'sender-maria',
      source_type: 'whatsapp',
      caption: 'Legenda',
      duplicate_cluster_key: null,
      duplicateClusterKey: null,
      is_featured: false,
      created_at: '2026-04-07T10:00:00Z',
      assetStatus: 'ready' as const,
      playedAt: null,
      playCount: 0,
      lastError: null,
      orientation: 'horizontal' as const,
      width: 1600,
      height: 900,
    },
    isSyncing: false,
    errorMessage: null,
    connectionStatus: 'connected' as const,
    lastSyncAt: '2026-04-07T10:00:00Z',
    handleAdFinished: vi.fn(),
  };
}

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

    useWallPlayerMock.mockReturnValue(makeBasePlayerReturn());
  });

  it('renders the layout when no ad is active', () => {
    render(<WallPlayerRoot code="ABCD1234" />);

    expect(screen.getByTestId('motion-config')).toBeInTheDocument();
    expect(screen.getByText('layout-renderer-media_1')).toBeInTheDocument();
    expect(screen.queryByText('ad-overlay-1')).not.toBeInTheDocument();
  });

  it('renders the ad overlay when an ad is active', () => {
    const base = makeBasePlayerReturn();

    useWallPlayerMock.mockReturnValue({
      ...base,
      state: {
        ...base.state,
        currentAd: {
          id: 1,
          url: 'https://cdn.example.com/ad-1.jpg',
          media_type: 'image',
          duration_seconds: 10,
          position: 0,
        },
        adBaseItemId: 'media_1',
        adScheduler: {
          ...base.state.adScheduler,
          photosSinceLastAd: 2,
          lastAdIndex: 0,
        },
      },
    });

    render(<WallPlayerRoot code="ABCD1234" />);

    expect(screen.getByText('ad-overlay-1')).toBeInTheDocument();
    expect(screen.queryByText('layout-renderer-media_1')).not.toBeInTheDocument();
  });

  it('wraps the player in MotionConfig and forces reduced motion when performance mode is active', () => {
    usePerformanceModeMock.mockReturnValue({
      reducedEffects: true,
      modeLabel: 'Performance',
    });

    render(<WallPlayerRoot code="ABCD1234" />);

    expect(motionConfigMock).toHaveBeenCalledWith(expect.objectContaining({
      reducedMotion: 'always',
      transition: expect.objectContaining({
        duration: 0,
      }),
    }));
  });
});

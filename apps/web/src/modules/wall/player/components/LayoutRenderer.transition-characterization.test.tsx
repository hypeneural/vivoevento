import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { LayoutRenderer } from './LayoutRenderer';
import type { WallRuntimeItem, WallSettings } from '../types';

const resolveLayoutTransitionMock = vi.fn();
const animatePresenceSpy = vi.fn();
const motionDivSpy = vi.fn();
const useWallBoardMock = vi.fn();
const usePuzzleBoardMock = vi.fn();

vi.mock('framer-motion', () => ({
  AnimatePresence: ({
    children,
    mode,
  }: {
    children: ReactNode;
    mode?: string;
  }) => {
    animatePresenceSpy({ mode: mode ?? '' });

    return (
      <div data-testid="animate-presence" data-mode={mode ?? ''}>
        {children}
      </div>
    );
  },
  motion: {
    div: ({
      children,
      ...props
    }: {
      children: ReactNode;
      [key: string]: unknown;
    }) => {
      motionDivSpy(props);

      return <div data-testid="motion-div">{children}</div>;
    },
  },
}));

vi.mock('../engine/motion', () => ({
  resolveLayoutTransition: (...args: unknown[]) => resolveLayoutTransitionMock(...args),
}));

vi.mock('../engine/layoutStrategy', () => ({
  resolveRenderableLayout: (layout: unknown) => layout,
}));

vi.mock('../runtime-capabilities', () => ({
  resolveWallRuntimeBudget: () => ({
    maxBoardPieces: 9,
    maxStrongAnimations: 2,
  }),
}));

vi.mock('../themes/registry', () => ({
  getWallLayoutDefinition: (layout: string) => {
    if (layout === 'carousel') {
      return {
        id: 'carousel',
        kind: 'board',
        renderer: ({ slots = [] }: { slots?: unknown[] }) => (
          <div data-testid="board-layout">{slots.length}</div>
        ),
        motion: {
          enter: { ease: 'easeOut' },
          visualDuration: 0.42,
        },
        version: 'test-version',
      };
    }

    return {
      id: layout,
      kind: 'single',
      renderer: ({ media }: { media: { id: string } }) => (
        <div data-testid="single-layout">{media.id}</div>
      ),
      motion: {
        enter: { ease: 'easeOut' },
        visualDuration: 0.42,
      },
      version: 'test-version',
    };
  },
}));

vi.mock('../themes/board/types', () => ({
  createBoardInstanceKey: () => 'board-instance',
  createLinearAdjacencyMap: () => new Map(),
}));

vi.mock('../themes/board/useWallBoard', () => ({
  useWallBoard: (...args: unknown[]) => useWallBoardMock(...args),
}));

vi.mock('../themes/puzzle/usePuzzleBoard', () => ({
  usePuzzleBoard: (...args: unknown[]) => usePuzzleBoardMock(...args),
}));

function makeItem(id: string): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    sender_name: `Sender ${id}`,
    sender_key: `sender-${id}`,
    senderKey: `sender-${id}`,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    created_at: '2026-04-11T12:00:00Z',
    assetStatus: 'ready',
    playedAt: null,
    playCount: 0,
    lastError: null,
    width: 1200,
    height: 800,
    orientation: 'horizontal',
  };
}

function makeSettings(layout: WallSettings['layout']): WallSettings {
  return {
    interval_ms: 8000,
    queue_limit: 50,
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
    theme_config: {
      preset: 'standard',
      anchor_mode: 'none',
      burst_intensity: 'normal',
      hero_enabled: true,
      video_behavior: 'fallback_single_item',
    },
    layout,
    transition_effect: 'slide',
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
    video_enabled: true,
    video_playback_mode: 'play_to_end_if_short_else_cap',
    video_max_seconds: 15,
    video_resume_mode: 'resume_if_same_item_else_restart',
    video_audio_policy: 'muted',
    video_multi_layout_policy: 'disallow',
    video_preferred_variant: 'wall_video_720p',
    ad_mode: 'disabled',
    ad_frequency: 5,
    ad_interval_minutes: 3,
    instructions_text: null,
  };
}

describe('LayoutRenderer transition characterization', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    resolveLayoutTransitionMock.mockReturnValue({
      effect: 'slide',
      variants: {
        initial: { opacity: 0, x: 60 },
        animate: { opacity: 1, x: 0 },
        exit: { opacity: 0, x: -60 },
      },
      transition: {
        duration: 0.42,
        ease: 'easeOut',
      },
    });

    useWallBoardMock.mockReturnValue({
      slots: [makeItem('slot-a'), makeItem('slot-b'), null],
      activeSlot: 0,
      activeSlotIndexes: [0],
    });

    usePuzzleBoardMock.mockReturnValue({
      slots: [],
      activeSlot: 0,
      activeSlotIndexes: [],
    });
  });

  it('uses AnimatePresence wait and the resolved transition only for single-item layouts', () => {
    render(
      <LayoutRenderer
        media={makeItem('hero')}
        settings={makeSettings('fullscreen')}
        activeTransitionEffect="flip"
        reducedMotion={false}
        allItems={[makeItem('hero')]}
      />,
    );

    expect(resolveLayoutTransitionMock).toHaveBeenCalledWith(
      'flip',
      expect.objectContaining({
        visualDuration: 0.42,
      }),
      false,
      'premium',
      false,
    );
    expect(screen.getByTestId('animate-presence')).toHaveAttribute('data-mode', 'wait');
    expect(screen.getByTestId('single-layout')).toHaveTextContent('hero');
    expect(motionDivSpy).toHaveBeenCalledWith(expect.objectContaining({
      initial: { opacity: 0, x: 60 },
      animate: { opacity: 1, x: 0 },
      exit: { opacity: 0, x: -60 },
      transition: {
        duration: 0.42,
        ease: 'easeOut',
      },
      className: 'absolute inset-0',
    }));
    expect(animatePresenceSpy).toHaveBeenCalledWith({ mode: 'wait' });
  });

  it('bypasses resolveLayoutTransition for board layouts and renders the board path directly', () => {
    render(
      <LayoutRenderer
        media={makeItem('hero')}
        settings={makeSettings('carousel')}
        reducedMotion={false}
        allItems={[makeItem('hero'), makeItem('next')]}
      />,
    );

    expect(resolveLayoutTransitionMock).not.toHaveBeenCalled();
    expect(screen.getByTestId('board-layout')).toHaveTextContent('3');
    expect(screen.queryByTestId('animate-presence')).not.toBeInTheDocument();
    expect(motionDivSpy).not.toHaveBeenCalled();
  });
});

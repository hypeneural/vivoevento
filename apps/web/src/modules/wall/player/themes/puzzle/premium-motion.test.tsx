import { createRef, type ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('framer-motion', async () => {
  const actual = await vi.importActual<typeof import('framer-motion')>('framer-motion');

  return {
    ...actual,
    AnimatePresence: ({
      children,
      mode,
    }: {
      children: ReactNode;
      mode?: string;
    }) => (
      <div data-testid="puzzle-animate-presence" data-mode={mode ?? ''}>
        {children}
      </div>
    ),
    LayoutGroup: ({
      children,
      id,
    }: {
      children: ReactNode;
      id?: string;
    }) => (
      <div data-testid="puzzle-layout-group" data-layout-group-id={id ?? ''}>
        {children}
      </div>
    ),
  };
});

import type { WallRuntimeItem, WallSettings } from '../../types';
import PuzzleLayout from './PuzzleLayout';
import PuzzlePiece from './PuzzlePiece';

function makeItem(id: string, overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
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
    assetStatus: 'ready',
    playCount: 0,
    width: 1200,
    height: 900,
    orientation: 'horizontal',
    ...overrides,
  };
}

function makeSettings(themeConfig?: Partial<WallSettings['theme_config']>): WallSettings {
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
      ...themeConfig,
    },
    layout: 'puzzle',
    transition_effect: 'fade',
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

describe('Puzzle premium motion', () => {
  it('usa popLayout e agrupa shared layout do board', () => {
    render(
      <PuzzleLayout
        media={makeItem('current')}
        settings={makeSettings()}
        slots={[
          makeItem('a', { is_featured: true }),
          makeItem('b'),
          makeItem('c'),
          makeItem('d'),
          makeItem('e'),
          makeItem('f'),
          makeItem('g'),
          makeItem('h'),
          makeItem('i'),
        ]}
        activeSlotIndexes={[0, 1]}
        maxStrongAnimations={2}
      />,
    );

    expect(screen.getByTestId('puzzle-animate-presence')).toHaveAttribute('data-mode', 'popLayout');
    expect(screen.getByTestId('puzzle-layout-group')).toHaveAttribute(
      'data-layout-group-id',
      'puzzle-board-standard',
    );
    expect(screen.getAllByTestId(/puzzle-piece-/).some((node) => node.getAttribute('data-featured-hero') === 'true')).toBe(true);
  });

  it('encaminha ref para a surface da peca quando o board usa popLayout', () => {
    const ref = createRef<HTMLElement>();

    render(
      <PuzzlePiece
        ref={ref}
        pieceIndex={0}
        pieceVariant="puzzle-a"
        media={makeItem('hero', { is_featured: true })}
        isHero
      />,
    );

    expect(ref.current?.tagName).toBe('ARTICLE');
    expect(ref.current).toHaveAttribute('data-featured-hero', 'true');
  });
});

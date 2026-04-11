import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { PuzzleLayout } from './PuzzleLayout';
import type { WallRuntimeItem, WallSettings } from '../../types';
import { applyStageGeometryToWallSettings, resolveWallStageGeometry } from '../../hooks/useStageGeometry';

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
    assetStatus: 'ready',
    playCount: 0,
    width: 1200,
    height: 900,
    orientation: 'horizontal',
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

describe('PuzzleLayout', () => {
  it('renders 9 puzzle pieces in the standard preset with deduplicated shape defs', () => {
    render(
      <PuzzleLayout
        media={makeItem('current')}
        settings={makeSettings()}
        slots={['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'].map((id) => makeItem(id))}
        activeSlotIndexes={[0, 1]}
        maxStrongAnimations={2}
      />,
    );

    expect(screen.getAllByTestId(/puzzle-piece-/)).toHaveLength(9);
    const clipPaths = document.querySelectorAll('clipPath');
    expect(clipPaths.length).toBeLessThan(9);
  });

  it('renders 6 puzzle pieces in the compact preset and caps strong animations', () => {
    render(
      <PuzzleLayout
        media={makeItem('current')}
        settings={makeSettings({ preset: 'compact' })}
        slots={['a', 'b', 'c', 'd', 'e', 'f'].map((id) => makeItem(id))}
        activeSlotIndexes={[0, 1, 2]}
        maxStrongAnimations={1}
      />,
    );

    expect(screen.getAllByTestId(/puzzle-piece-/)).toHaveLength(6);
    expect(
      screen.getAllByTestId(/puzzle-piece-/)
        .filter((piece) => piece.getAttribute('data-strong-animation') === 'true'),
    ).toHaveLength(1);
  });

  it('honors the stage-aware downgrade and renders the compact preset when safe area pressure shrinks the board', () => {
    const stageAwareSettings = applyStageGeometryToWallSettings(
      makeSettings({ preset: 'standard' }),
      resolveWallStageGeometry({
        width: 1024,
        height: 576,
        showQr: true,
        showBranding: true,
        showSenderCredit: true,
        preferredPreset: 'standard',
      }),
    );

    render(
      <PuzzleLayout
        media={makeItem('current')}
        settings={stageAwareSettings}
        slots={['a', 'b', 'c', 'd', 'e', 'f'].map((id) => makeItem(id))}
        activeSlotIndexes={[0, 1]}
        maxStrongAnimations={1}
      />,
    );

    expect(screen.getByTestId('puzzle-board')).toHaveAttribute('data-preset', 'compact');
    expect(screen.getByTestId('puzzle-board')).toHaveAttribute('data-piece-count', '6');
  });
});

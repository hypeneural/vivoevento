import { renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { usePuzzleBoard } from './usePuzzleBoard';
import type { WallRuntimeItem, WallSettings } from '../../types';

function makeItem(id: string, type: 'image' | 'video' = 'image'): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.${type === 'image' ? 'jpg' : 'mp4'}`,
    preview_url: type === 'video' ? `https://cdn.example.com/${id}-poster.jpg` : undefined,
    type,
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

describe('usePuzzleBoard', () => {
  it('uses 9 pieces in the standard preset and filters video items out of the board pool', () => {
    const items = [
      makeItem('a'),
      makeItem('b', 'video'),
      makeItem('c'),
      makeItem('d'),
      makeItem('e'),
      makeItem('f'),
      makeItem('g'),
      makeItem('h'),
      makeItem('i'),
      makeItem('j'),
    ];

    const { result } = renderHook(() => usePuzzleBoard(items, {
      settings: makeSettings(),
      boardInstanceKey: 'event:1|layout:puzzle|preset:standard|theme:2026-04-10|tier:premium|rm:0',
      advanceTrigger: 0,
      maxBoardPieces: 9,
      reducedMotion: false,
    }));

    expect(result.current.pieceCount).toBe(9);
    expect(result.current.slots).toHaveLength(9);
    expect(result.current.slots.every((item) => item == null || item.type === 'image')).toBe(true);
  });

  it('uses 6 pieces in the compact preset and exposes the anchor index when enabled', () => {
    const items = ['a', 'b', 'c', 'd', 'e', 'f', 'g']
      .map((id) => makeItem(id));

    const { result } = renderHook(() => usePuzzleBoard(items, {
      settings: makeSettings({
        preset: 'compact',
        anchor_mode: 'event_brand',
      }),
      boardInstanceKey: 'event:1|layout:puzzle|preset:compact|theme:2026-04-10|tier:performance|rm:1',
      advanceTrigger: 0,
      maxBoardPieces: 6,
      reducedMotion: true,
    }));

    expect(result.current.pieceCount).toBe(6);
    expect(result.current.anchorEnabled).toBe(true);
    expect(result.current.anchorIndex).toBe(2);
    expect(result.current.slots).toHaveLength(5);
  });

  it('preserves the current board slots on incremental queue updates without resetting the board', () => {
    const initialItems = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']
      .map((id) => makeItem(id));
    const boardInstanceKey = 'event:1|layout:puzzle|preset:standard|theme:2026-04-10|tier:premium|rm:0';

    const { result, rerender } = renderHook(
      ({ items }) => usePuzzleBoard(items, {
        settings: makeSettings(),
        boardInstanceKey,
        advanceTrigger: 0,
        maxBoardPieces: 9,
        reducedMotion: false,
      }),
      {
        initialProps: {
          items: initialItems,
        },
      },
    );

    const initialSlotIds = result.current.slots.map((item) => item?.id ?? null);
    const initialBoardStep = result.current.boardStep;

    rerender({
      items: [...initialItems, makeItem('j')],
    });

    expect(result.current.slots.map((item) => item?.id ?? null)).toEqual(initialSlotIds);
    expect(result.current.boardStep).toBe(initialBoardStep);
  });
});

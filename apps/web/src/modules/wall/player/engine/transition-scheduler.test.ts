import { describe, expect, it } from 'vitest';

import {
  DEFAULT_RANDOM_TRANSITION_POOL,
  resolveWallRuntimeTransitionEffect,
} from './transition-scheduler';
import type { WallRuntimeItem, WallSettings } from '../types';

function makeItem(overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
  return {
    id: 'media_1',
    url: 'https://cdn.example.com/media-1.jpg',
    type: 'image',
    sender_name: 'Maria',
    sender_key: 'sender-maria',
    senderKey: 'sender-maria',
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
    ...overrides,
  };
}

function makeSettings(overrides: Partial<WallSettings> = {}): WallSettings {
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
    layout: 'fullscreen',
    transition_effect: 'fade',
    transition_mode: 'fixed',
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
    ...overrides,
  };
}

describe('transition scheduler', () => {
  it('uses the configured transition effect exactly when mode is fixed', () => {
    const effect = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 1,
      settings: makeSettings({
        transition_effect: 'flip',
        transition_mode: 'fixed',
      }),
      currentItem: makeItem(),
      lastTransitionEffect: 'zoom',
      transitionAdvanceCount: 4,
    });

    expect(effect).toBe('flip');
  });

  it('picks from the default safe random pool deterministically for single-item layouts', () => {
    const input = {
      code: 'ABCD1234',
      eventId: 99,
      settings: makeSettings({
        layout: 'fullscreen',
        transition_mode: 'random',
      }),
      currentItem: makeItem({ id: 'hero-1' }),
      lastTransitionEffect: null,
      transitionAdvanceCount: 0,
    } as const;

    const first = resolveWallRuntimeTransitionEffect(input);
    const second = resolveWallRuntimeTransitionEffect(input);

    expect(DEFAULT_RANDOM_TRANSITION_POOL).toContain(first);
    expect(first).toBe(second);
    expect(first).not.toBe('none');
  });

  it('respects a custom transition_pool when random mode is active', () => {
    const input = {
      code: 'ABCD1234',
      eventId: 77,
      settings: makeSettings({
        layout: 'fullscreen',
        transition_mode: 'random',
        transition_pool: ['cross-zoom', 'swipe-up'],
      }),
      currentItem: makeItem({ id: 'hero-custom-pool' }),
      lastTransitionEffect: null,
      transitionAdvanceCount: 0,
    } as const;

    const first = resolveWallRuntimeTransitionEffect(input);
    const second = resolveWallRuntimeTransitionEffect(input);

    expect(['cross-zoom', 'swipe-up']).toContain(first);
    expect(first).toBe(second);
  });

  it('avoids repeating the previous transition effect when another random option exists', () => {
    const effect = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 99,
      settings: makeSettings({
        layout: 'fullscreen',
        transition_mode: 'random',
      }),
      currentItem: makeItem({ id: 'hero-2' }),
      lastTransitionEffect: 'fade',
      transitionAdvanceCount: 1,
    });

    expect(DEFAULT_RANDOM_TRANSITION_POOL).toContain(effect);
    expect(effect).not.toBe('fade');
  });

  it('avoids repeating the previous transition effect inside a custom transition_pool', () => {
    const effect = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 55,
      settings: makeSettings({
        layout: 'fullscreen',
        transition_mode: 'random',
        transition_pool: ['slide', 'swipe-up'],
      }),
      currentItem: makeItem({ id: 'hero-custom-repeat' }),
      lastTransitionEffect: 'slide',
      transitionAdvanceCount: 1,
    });

    expect(effect).toBe('swipe-up');
  });

  it('falls back to the default safe pool when the custom transition_pool is empty or invalid', () => {
    const fromEmptyPool = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 88,
      settings: makeSettings({
        layout: 'fullscreen',
        transition_mode: 'random',
        transition_pool: [],
      }),
      currentItem: makeItem({ id: 'hero-empty-pool' }),
      lastTransitionEffect: null,
      transitionAdvanceCount: 2,
    });

    const fromInvalidPool = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 88,
      settings: {
        ...makeSettings({
          layout: 'fullscreen',
          transition_mode: 'random',
        }),
        transition_pool: ['none', 'blur-fade'] as WallSettings['transition_pool'],
      },
      currentItem: makeItem({ id: 'hero-invalid-pool' }),
      lastTransitionEffect: null,
      transitionAdvanceCount: 2,
    });

    expect(DEFAULT_RANDOM_TRANSITION_POOL).toContain(fromEmptyPool);
    expect(DEFAULT_RANDOM_TRANSITION_POOL).toContain(fromInvalidPool);
  });

  it('ignores random mode for board layouts and falls back to the configured base effect', () => {
    const effect = resolveWallRuntimeTransitionEffect({
      code: 'ABCD1234',
      eventId: 99,
      settings: makeSettings({
        layout: 'grid',
        transition_effect: 'slide',
        transition_mode: 'random',
      }),
      currentItem: makeItem(),
      lastTransitionEffect: 'zoom',
      transitionAdvanceCount: 3,
    });

    expect(effect).toBe('slide');
  });
});

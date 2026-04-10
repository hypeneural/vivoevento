import { describe, expect, it } from 'vitest';

import { fallbackOptions, PUZZLE_LAYOUT_FALLBACK_OPTION } from './manager-config';
import {
  applyWallLayoutCapabilities,
  areWallSettingsEqual,
  cloneWallSettings,
  prepareWallSettingsPayload,
  resolveManagedWallSettings,
} from './wall-settings';
import type { ApiWallSettings } from '@/lib/api-types';

const baseSettings: ApiWallSettings = {
  interval_ms: 8000,
  queue_limit: 100,
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
  public_upload_video_enabled: true,
  private_inbound_video_enabled: true,
  video_playback_mode: 'play_to_end_if_short_else_cap',
  video_max_seconds: 30,
  video_resume_mode: 'resume_if_same_item_else_restart',
  video_audio_policy: 'muted',
  video_multi_layout_policy: 'disallow',
  video_preferred_variant: 'wall_video_720p',
  ad_mode: 'disabled',
  ad_frequency: 5,
  ad_interval_minutes: 3,
  instructions_text: null,
  theme_config: {
    preset: 'standard',
    anchor_mode: 'event_brand',
    burst_intensity: 'normal',
    hero_enabled: true,
    video_behavior: 'fallback_single_item',
  },
};

describe('wall settings theme config helpers', () => {
  it('clones and prepares theme_config without dropping puzzle fields', () => {
    expect(cloneWallSettings(baseSettings).theme_config).toEqual(baseSettings.theme_config);
    expect(prepareWallSettingsPayload(baseSettings).theme_config).toEqual(baseSettings.theme_config);
  });

  it('compares normalized theme_config to avoid dirty state loops', () => {
    expect(areWallSettingsEqual(baseSettings, {
      ...baseSettings,
      theme_config: {
        video_behavior: 'fallback_single_item',
        hero_enabled: true,
        burst_intensity: 'normal',
        anchor_mode: 'event_brand',
        preset: 'standard',
      },
    })).toBe(true);

    expect(areWallSettingsEqual(baseSettings, {
      ...baseSettings,
      theme_config: {
        ...baseSettings.theme_config,
        preset: 'compact',
      },
    })).toBe(false);
  });

  it('keeps fallback options capability-aware without bypassing the puzzle rollout gate', () => {
    const fullscreen = fallbackOptions.layouts.find((layout) => layout.value === 'fullscreen');

    expect(fullscreen?.capabilities).toMatchObject({
      supports_video_playback: true,
      supports_video_poster_only: false,
      supports_multi_video: false,
      max_simultaneous_videos: 1,
      fallback_video_layout: null,
      supports_side_thumbnails: true,
      supports_floating_caption: true,
      supports_theme_config: false,
    });
    expect(fallbackOptions.layouts.some((layout) => layout.value === 'puzzle')).toBe(false);
  });

  it('applies puzzle defaults and capability locks without losing theme_config stability', () => {
    const resolved = applyWallLayoutCapabilities({
      ...baseSettings,
      show_side_thumbnails: true,
      video_multi_layout_policy: 'all',
      theme_config: {},
    }, PUZZLE_LAYOUT_FALLBACK_OPTION);

    expect(resolved.show_side_thumbnails).toBe(false);
    expect(resolved.video_multi_layout_policy).toBe('disallow');
    expect(resolved.theme_config).toEqual({
      preset: 'standard',
      anchor_mode: 'event_brand',
      burst_intensity: 'normal',
      hero_enabled: true,
      video_behavior: 'fallback_single_item',
    });
  });

  it('resolves managed wall settings with a synthetic puzzle option when the rollout fallback list is active', () => {
    const resolved = resolveManagedWallSettings({
      ...baseSettings,
      show_side_thumbnails: true,
      video_multi_layout_policy: 'one',
      theme_config: {},
    }, fallbackOptions.layouts);

    expect(resolved.layout).toBe('puzzle');
    expect(resolved.show_side_thumbnails).toBe(false);
    expect(resolved.video_multi_layout_policy).toBe('disallow');
    expect(resolved.theme_config.preset).toBe('standard');
  });
});

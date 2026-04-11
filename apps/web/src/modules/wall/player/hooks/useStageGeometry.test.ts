import { describe, expect, it } from 'vitest';

import type { WallSettings } from '../types';
import {
  applyStageGeometryToWallSettings,
  resolveWallStageGeometry,
} from './useStageGeometry';

function makeSettings(layout: WallSettings['layout'] = 'puzzle'): WallSettings {
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
      anchor_mode: 'event_brand',
      burst_intensity: 'normal',
      hero_enabled: true,
      video_behavior: 'fallback_single_item',
    },
    layout,
    transition_effect: 'fade',
    background_url: null,
    partner_logo_url: null,
    show_qr: true,
    show_branding: true,
    show_neon: false,
    neon_text: null,
    neon_color: '#ffffff',
    show_sender_credit: true,
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

describe('useStageGeometry helpers', () => {
  it('keeps the standard puzzle preset when the stage still has enough useful area', () => {
    const geometry = resolveWallStageGeometry({
      width: 1365,
      height: 768,
      showQr: true,
      showBranding: true,
      showSenderCredit: true,
      preferredPreset: 'standard',
    });

    expect(geometry.effectivePreset).toBe('standard');
    expect(geometry.downgraded).toBe(false);
    expect(geometry.usableWidth).toBeGreaterThan(1080);
    expect(geometry.usableHeight).toBeGreaterThan(620);
  });

  it('downgrades the puzzle to compact when the stage is too small for the standard board', () => {
    const geometry = resolveWallStageGeometry({
      width: 1024,
      height: 576,
      showQr: true,
      showBranding: true,
      showSenderCredit: true,
      preferredPreset: 'standard',
    });

    expect(geometry.effectivePreset).toBe('compact');
    expect(geometry.downgraded).toBe(true);
    expect(geometry.downgradeReason).toBe('small_stage');
  });

  it('applies the stage-aware downgrade only to puzzle settings', () => {
    const compactGeometry = resolveWallStageGeometry({
      width: 1024,
      height: 576,
      showQr: true,
      showBranding: true,
      showSenderCredit: true,
      preferredPreset: 'standard',
    });

    const puzzleSettings = applyStageGeometryToWallSettings(makeSettings('puzzle'), compactGeometry);
    const gridSettings = applyStageGeometryToWallSettings(makeSettings('grid'), compactGeometry);

    expect(puzzleSettings.theme_config.preset).toBe('compact');
    expect(gridSettings.theme_config.preset).toBe('standard');
  });
});

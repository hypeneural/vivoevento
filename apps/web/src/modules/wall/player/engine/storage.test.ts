import { beforeEach, describe, expect, it } from 'vitest';

import { readWallRuntimeStorage, writeWallRuntimeStorage } from './storage';
import type { WallPlayerState } from '../types';

function makeState(): WallPlayerState {
  return {
    code: 'ABCD1234',
    status: 'playing',
    event: {
      id: 1,
      title: 'Evento',
      slug: 'evento',
      wall_code: 'ABCD1234',
      status: 'live',
    },
    settings: {
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
      theme_config: {
        preset: 'standard',
        anchor_mode: 'none',
        burst_intensity: 'normal',
        hero_enabled: true,
        video_behavior: 'fallback_single_item',
      },
      layout: 'fullscreen',
      transition_effect: 'fade',
      transition_mode: 'random',
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
    },
    items: [
      {
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
      },
    ],
    ads: [],
    currentAd: null,
    adBaseItemId: null,
    adScheduler: {
      mode: 'disabled',
      frequency: 5,
      photosSinceLastAd: 0,
      lastAdPlayedAt: null,
      lastAdIndex: -1,
      skipNextAdCheck: false,
    },
    senderStats: {},
    currentIndex: 0,
    currentItemId: 'media_1',
    currentItemStartedAt: '2026-04-11T12:00:00Z',
    activeTransitionEffect: 'zoom',
    lastTransitionEffect: 'fade',
    transitionAdvanceCount: 3,
    videoPlayback: {
      itemId: null,
      phase: 'idle',
      currentTime: 0,
      durationSeconds: null,
      readyState: 0,
      exitReason: null,
      failureReason: null,
      stallCount: 0,
      posterVisible: false,
      firstFrameReady: false,
      playbackReady: false,
      playingConfirmed: false,
      startupDegraded: false,
      playbackStartedAt: null,
      lastItemId: null,
      lastExitReason: null,
      lastFailureReason: null,
    },
  };
}

describe('wall runtime storage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('persists the active transition runtime state for continuity after reload', () => {
    writeWallRuntimeStorage('ABCD1234', makeState());

    expect(readWallRuntimeStorage('ABCD1234')).toEqual(expect.objectContaining({
      currentItemId: 'media_1',
      currentItemStartedAt: '2026-04-11T12:00:00Z',
      activeTransitionEffect: 'zoom',
      lastTransitionEffect: 'fade',
      transitionAdvanceCount: 3,
    }));
  });

  it('still accepts legacy persisted payloads that do not have transition runtime fields', () => {
    window.localStorage.setItem('eventovivo:wall:runtime:ABCD1234', JSON.stringify({
      version: 4,
      currentItemId: 'media_1',
      currentItemStartedAt: '2026-04-11T12:00:00Z',
      senderStats: {},
      items: [],
    }));

    expect(readWallRuntimeStorage('ABCD1234')).toEqual(expect.objectContaining({
      currentItemId: 'media_1',
      currentItemStartedAt: '2026-04-11T12:00:00Z',
    }));
  });
});

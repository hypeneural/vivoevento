import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { LayoutRenderer } from './LayoutRenderer';
import type { MediaSurfaceVideoControlProps } from './MediaSurface';
import type { WallRuntimeItem, WallSettings } from '../types';

function makeVideo(id: string): WallRuntimeItem {
  return {
    id,
    url: `https://cdn.example.com/${id}.mp4`,
    preview_url: `https://cdn.example.com/${id}.jpg`,
    type: 'video',
    sender_name: `Sender ${id}`,
    sender_key: `sender-${id}`,
    senderKey: `sender-${id}`,
    source_type: 'public_upload',
    caption: null,
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    created_at: '2026-04-09T12:00:00Z',
    assetStatus: 'ready',
    playedAt: null,
    playCount: 0,
    lastError: null,
    width: 1280,
    height: 720,
    orientation: 'horizontal',
    duration_seconds: 12,
    has_audio: false,
  };
}

function makeImage(id: string): WallRuntimeItem {
  return {
    ...makeVideo(id),
    url: `https://cdn.example.com/${id}.jpg`,
    type: 'image',
    preview_url: undefined,
    duration_seconds: undefined,
    has_audio: undefined,
  };
}

function makeSettings(): WallSettings {
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
    layout: 'grid',
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
    video_multi_layout_policy: 'all',
    video_preferred_variant: 'wall_video_720p',
    ad_mode: 'disabled',
    ad_frequency: 5,
    ad_interval_minutes: 3,
    instructions_text: null,
  };
}

function makeVideoControl(): MediaSurfaceVideoControlProps {
  return {
    playerStatus: 'booting',
    startupDeadlineMs: 1200,
    stallBudgetMs: 3000,
    resumeMode: 'resume_if_same_item_else_restart',
    onStarting: () => undefined,
    onFirstFrame: () => undefined,
    onPlaybackReady: () => undefined,
    onPlaying: () => undefined,
    onProgress: () => undefined,
    onWaiting: () => undefined,
    onStalled: () => undefined,
    onEnded: () => undefined,
    onFailure: () => undefined,
  };
}

describe('LayoutRenderer video multi-layout characterization', () => {
  it('mounts multiple autoplay videos in grid mode when multi-layout video policy is all', () => {
    const items = [makeVideo('video-1'), makeVideo('video-2'), makeVideo('video-3')];

    const { container } = render(
      <LayoutRenderer
        media={items[0]}
        settings={makeSettings()}
        allItems={items}
      />,
    );

    const videos = container.querySelectorAll('video');
    const posterImages = container.querySelectorAll('img');

    expect(videos).toHaveLength(3);
    expect(Array.from(videos).every((video) => video.autoplay && video.muted)).toBe(true);
    expect(posterImages).toHaveLength(0);
  });

  it('falls back to a single controlled video surface when multi-layout video policy is disallow', () => {
    const items = [makeVideo('video-1'), makeVideo('video-2'), makeVideo('video-3')];
    const settings = {
      ...makeSettings(),
      video_multi_layout_policy: 'disallow' as const,
    };

    const { container } = render(
      <LayoutRenderer
        media={items[0]}
        settings={settings}
        allItems={items}
        videoControl={makeVideoControl()}
      />,
    );

    const videos = container.querySelectorAll('video');
    const posterImages = container.querySelectorAll('img');

    expect(videos).toHaveLength(1);
    expect(videos[0].getAttribute('poster')).toBe(items[0].preview_url);
    expect(posterImages.length).toBeGreaterThanOrEqual(1);
  });

  it('falls back to a single controlled video surface when puzzle receives a video as current media', () => {
    const items = [makeVideo('video-1'), makeVideo('video-2'), makeVideo('video-3')];
    const settings = {
      ...makeSettings(),
      layout: 'puzzle' as const,
      theme_config: {
        preset: 'standard' as const,
        anchor_mode: 'none' as const,
        burst_intensity: 'normal' as const,
        hero_enabled: true,
        video_behavior: 'fallback_single_item' as const,
      },
    };

    const { container } = render(
      <LayoutRenderer
        media={items[0]}
        settings={settings}
        allItems={items}
        videoControl={makeVideoControl()}
        performanceTier="premium"
      />,
    );

    const videos = container.querySelectorAll('video');

    expect(videos).toHaveLength(1);
    expect(videos[0].getAttribute('poster')).toBe(items[0].preview_url);
  });

  it('never mounts video tags inside puzzle board slots when the queue is mixed', () => {
    const items = [
      makeImage('image-1'),
      makeVideo('video-1'),
      makeImage('image-2'),
      makeImage('image-3'),
      makeImage('image-4'),
      makeImage('image-5'),
      makeImage('image-6'),
      makeImage('image-7'),
      makeImage('image-8'),
    ];
    const settings = {
      ...makeSettings(),
      layout: 'puzzle' as const,
      theme_config: {
        preset: 'standard' as const,
        anchor_mode: 'none' as const,
        burst_intensity: 'normal' as const,
        hero_enabled: true,
        video_behavior: 'fallback_single_item' as const,
      },
    };

    const { container } = render(
      <LayoutRenderer
        media={items[0]}
        settings={settings}
        allItems={items}
        performanceTier="premium"
      />,
    );

    expect(container.querySelectorAll('video')).toHaveLength(0);
    expect(container.querySelectorAll('[data-testid^=\"puzzle-piece-\"]')).toHaveLength(9);
  });
});

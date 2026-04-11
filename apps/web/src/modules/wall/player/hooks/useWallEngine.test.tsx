import { act, renderHook } from '@testing-library/react';
import { useWallEngine } from './useWallEngine';
import type { WallBootData, WallMediaItem } from '../types';

function makeMedia(overrides: Partial<WallMediaItem> = {}): WallMediaItem {
  return {
    id: 'media_1',
    url: 'https://cdn.example.com/media-1.mp4',
    type: 'video',
    sender_name: 'Maria',
    sender_key: 'whatsapp-5511999999999',
    caption: 'Legenda',
    is_featured: false,
    created_at: '2026-04-01T10:00:00Z',
    ...overrides,
  };
}

function makeSnapshot(status: WallBootData['event']['status'], files: WallMediaItem[] = []): WallBootData {
  return {
    event: {
      id: 10,
      title: 'Evento Vivo',
      slug: 'evento-vivo',
      wall_code: 'ABCD1234',
      status,
    },
    files,
    settings: {
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
      layout: 'auto',
      transition_effect: 'fade',
      background_url: null,
      partner_logo_url: null,
      show_qr: true,
      show_branding: true,
      show_neon: false,
      neon_text: null,
      neon_color: '#ffffff',
      show_sender_credit: false,
      show_side_thumbnails: true,
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
  };
}

describe('useWallEngine', () => {
  beforeEach(() => {
    vi.useRealTimers();
    window.localStorage.clear();
  });

  it('maps disabled snapshot status to stopped player state', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('disabled'));
    });

    expect(result.current.state.status).toBe('stopped');
    expect(result.current.state.event?.status).toBe('disabled');
  });

  it('maps a live snapshot with files to playing state', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [makeMedia()]));
    });

    expect(result.current.state.status).toBe('playing');
    expect(result.current.state.items).toHaveLength(1);
    expect(result.current.currentItem?.id).toBe('media_1');
    expect(result.current.state.activeTransitionEffect).toBe('fade');
    expect(result.current.state.transitionAdvanceCount).toBe(0);
  });

  it('keeps the active transition stable for the same slide even if settings change while mode is random', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
          makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
        ]),
        settings: {
          ...makeSnapshot('live').settings,
          layout: 'fullscreen',
          transition_effect: 'fade',
          transition_mode: 'random',
        },
      });
    });

    const firstEffect = result.current.state.activeTransitionEffect;
    expect(firstEffect).toBeTruthy();

    act(() => {
      result.current.applySettings({
        ...makeSnapshot('live').settings,
        layout: 'fullscreen',
        transition_effect: 'flip',
        transition_mode: 'random',
      });
    });

    expect(result.current.state.currentItemId).toBe('media_b');
    expect(result.current.state.activeTransitionEffect).toBe(firstEffect);
    expect(result.current.state.transitionAdvanceCount).toBe(0);
  });

  it('resolves a new deterministic random effect only when the slideshow advances', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
          makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
          makeMedia({ id: 'media_c', sender_name: 'Carla', sender_key: 'sender-carla', type: 'image', created_at: '2026-04-01T10:03:00Z' }),
        ]),
        settings: {
          ...makeSnapshot('live').settings,
          layout: 'fullscreen',
          transition_effect: 'fade',
          transition_mode: 'random',
        },
      });
    });

    const firstItemId = result.current.currentItem?.id;
    const firstEffect = result.current.state.activeTransitionEffect;

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.id).not.toBe(firstItemId);
    expect(result.current.state.activeTransitionEffect).toBeTruthy();
    expect(result.current.state.activeTransitionEffect).not.toBe(firstEffect);
    expect(result.current.state.lastTransitionEffect).toBe(firstEffect);
    expect(result.current.state.transitionAdvanceCount).toBe(1);
  });

  it('ignores random mode for board layouts and keeps the configured base transition effect', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
          makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
        ]),
        settings: {
          ...makeSnapshot('live').settings,
          layout: 'grid',
          transition_effect: 'slide',
          transition_mode: 'random',
        },
      });
    });

    expect(result.current.state.activeTransitionEffect).toBe('slide');
    expect(result.current.state.transitionAdvanceCount).toBe(0);
  });

  it('moves from playing to stopped when realtime status becomes disabled', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [makeMedia()]));
    });

    act(() => {
      result.current.handleStatusChanged({
        status: 'disabled',
        reason: 'disabled_by_admin',
        updated_at: '2026-04-01T10:10:00Z',
      });
    });

    expect(result.current.state.status).toBe('stopped');
  });

  it('avoids repeating the same sender when another sender is available', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_a_1', sender_name: 'Maria', sender_key: 'sender-maria', type: 'image' }),
        makeMedia({ id: 'media_a_2', sender_name: 'Maria', sender_key: 'sender-maria', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_b_1', sender_name: 'Joao', sender_key: 'sender-joao', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
      ]));
    });

    expect(result.current.currentItem?.sender_name).toBe('Joao');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.sender_name).toBe('Maria');
  });

  it('falls back to a stable sender identity when sender_key is missing', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_maria_1', sender_name: 'Maria Silva', sender_key: null, type: 'image' }),
        makeMedia({ id: 'media_maria_2', sender_name: 'Maria Silva', sender_key: null, type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_pedro_1', sender_name: 'Pedro Lima', sender_key: null, type: 'image', created_at: '2026-04-01T10:02:00Z' }),
      ]));
    });

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.sender_name).toBe('Maria Silva');
  });

  it('preserves the current item on reconnect when the snapshot queue changes but the item still exists', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
      ]));
    });

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.id).toBe('media_a');

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_c', sender_name: 'Carla', sender_key: 'sender-carla', type: 'image', created_at: '2026-04-01T10:03:00Z' }),
      ]));
    });

    expect(result.current.currentItem?.id).toBe('media_a');
  });

  it('switches to the next fair item when the current media is deleted during playback', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
        makeMedia({ id: 'media_c', sender_name: 'Carla', sender_key: 'sender-carla', type: 'image', created_at: '2026-04-01T10:03:00Z' }),
      ]));
    });

    expect(result.current.currentItem?.id).toBe('media_c');

    act(() => {
      result.current.handleMediaDeleted({ id: 'media_c' });
    });

    expect(result.current.currentItem?.id).toBe('media_b');
    expect(result.current.state.items.some((item) => item.id === 'media_c')).toBe(false);
  });

  it('replays the queue after the first full round is completed', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
        makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
      ]));
    });

    expect(result.current.currentItem?.id).toBe('media_b');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.id).toBe('media_a');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.id).toBe('media_b');
    expect(result.current.state.status).toBe('playing');
  });

  it('allows consecutive playback from the same sender when the custom policy disables the guard', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({ id: 'media_a_1', sender_name: 'Maria', sender_key: 'sender-maria', type: 'image' }),
          makeMedia({ id: 'media_a_2', sender_name: 'Maria', sender_key: 'sender-maria', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
          makeMedia({ id: 'media_b_1', sender_name: 'Joao', sender_key: 'sender-joao', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
        ]),
        settings: {
          ...makeSnapshot('live').settings,
          selection_mode: 'custom',
          event_phase: 'flow',
          selection_policy: {
            max_eligible_items_per_sender: 4,
            max_replays_per_item: 2,
            low_volume_max_items: 6,
            medium_volume_max_items: 12,
            replay_interval_low_minutes: 8,
            replay_interval_medium_minutes: 12,
            replay_interval_high_minutes: 20,
            sender_cooldown_seconds: 0,
            sender_window_limit: 4,
            sender_window_minutes: 10,
            avoid_same_sender_if_alternative_exists: false,
            avoid_same_duplicate_cluster_if_alternative_exists: true,
          },
        },
      });
    });

    expect(result.current.currentItem?.sender_name).toBe('Joao');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.sender_name).toBe('Maria');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.sender_name).toBe('Maria');
  });

  it('intercepts the slideshow advance with an ad and only advances the photo after ad-finished', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({ id: 'media_a', sender_name: 'Ana', sender_key: 'sender-ana', type: 'image', created_at: '2026-04-01T10:01:00Z' }),
          makeMedia({ id: 'media_b', sender_name: 'Bruno', sender_key: 'sender-bruno', type: 'image', created_at: '2026-04-01T10:02:00Z' }),
        ]),
        ads: [
          {
            id: 11,
            url: 'https://cdn.example.com/ad-11.jpg',
            media_type: 'image',
            duration_seconds: 10,
            position: 0,
          },
        ],
        settings: {
          ...makeSnapshot('live').settings,
          ad_mode: 'by_photos',
          ad_frequency: 1,
          ad_interval_minutes: 3,
        },
      });
    });

    expect(result.current.currentItem?.id).toBe('media_b');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect((result.current.state as any).currentAd).toEqual(expect.objectContaining({
      id: 11,
    }));
    expect(result.current.currentItem?.id).toBe('media_b');

    act(() => {
      vi.advanceTimersByTime(16_000);
    });

    expect(result.current.currentItem?.id).toBe('media_b');

    act(() => {
      (result.current as any).handleAdFinished();
    });

    expect((result.current.state as any).currentAd).toBeNull();
    expect(result.current.currentItem?.id).toBe('media_a');
  });

  it('does not advance a current video just because interval_ms elapsed', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({
          id: 'video_a',
          sender_name: 'Ana',
          sender_key: 'sender-ana',
          type: 'video',
          duration_seconds: 60,
          created_at: '2026-04-01T10:03:00Z',
        }),
        makeMedia({
          id: 'image_b',
          sender_name: 'Bruno',
          sender_key: 'sender-bruno',
          type: 'image',
          created_at: '2026-04-01T10:02:00Z',
        }),
      ]));
    });

    expect(result.current.currentItem?.id).toBe('video_a');

    act(() => {
      vi.advanceTimersByTime(8_000);
    });

    expect(result.current.currentItem?.id).toBe('video_a');
  });

  it('advances a video by ended instead of fixed interval', () => {
    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({
          id: 'video_a',
          sender_name: 'Ana',
          sender_key: 'sender-ana',
          type: 'video',
          duration_seconds: 12,
          created_at: '2026-04-01T10:03:00Z',
        }),
        makeMedia({
          id: 'image_b',
          sender_name: 'Bruno',
          sender_key: 'sender-bruno',
          type: 'image',
          created_at: '2026-04-01T10:02:00Z',
        }),
      ]));
    });

    act(() => {
      result.current.handleVideoEnded({
        itemId: 'video_a',
        currentTime: 12,
        durationSeconds: 12,
        readyState: 4,
      });
    });

    expect(result.current.currentItem?.id).toBe('image_b');
    expect(result.current.state.videoPlayback.lastExitReason).toBe('ended');
  });

  it('caps long videos after the configured baseline budget', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot(makeSnapshot('live', [
        makeMedia({
          id: 'video_a',
          sender_name: 'Ana',
          sender_key: 'sender-ana',
          type: 'video',
          duration_seconds: 60,
          created_at: '2026-04-01T10:03:00Z',
        }),
        makeMedia({
          id: 'image_b',
          sender_name: 'Bruno',
          sender_key: 'sender-bruno',
          type: 'image',
          created_at: '2026-04-01T10:02:00Z',
        }),
      ]));
    });

    act(() => {
      result.current.handleVideoPlaying({
        itemId: 'video_a',
        currentTime: 0,
        durationSeconds: 60,
        readyState: 4,
      });
    });

    act(() => {
      vi.advanceTimersByTime(15_000);
    });

    expect(result.current.currentItem?.id).toBe('image_b');
    expect(result.current.state.videoPlayback.lastExitReason).toBe('cap_reached');
  });

  it('does not cap long videos when the wall policy is play_to_end', () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useWallEngine('ABCD1234'));

    act(() => {
      result.current.applySnapshot({
        ...makeSnapshot('live', [
          makeMedia({
            id: 'video_a',
            sender_name: 'Ana',
            sender_key: 'sender-ana',
            type: 'video',
            duration_seconds: 60,
            created_at: '2026-04-01T10:03:00Z',
          }),
          makeMedia({
            id: 'image_b',
            sender_name: 'Bruno',
            sender_key: 'sender-bruno',
            type: 'image',
            created_at: '2026-04-01T10:02:00Z',
          }),
        ]),
        settings: {
          ...makeSnapshot('live').settings,
          video_playback_mode: 'play_to_end',
          video_max_seconds: 15,
        },
      });
    });

    act(() => {
      result.current.handleVideoPlaying({
        itemId: 'video_a',
        currentTime: 0,
        durationSeconds: 60,
        readyState: 4,
      });
    });

    act(() => {
      vi.advanceTimersByTime(20_000);
    });

    expect(result.current.currentItem?.id).toBe('video_a');
    expect(result.current.state.videoPlayback.lastExitReason).not.toBe('cap_reached');
  });
});

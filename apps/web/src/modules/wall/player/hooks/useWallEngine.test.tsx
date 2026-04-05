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
});

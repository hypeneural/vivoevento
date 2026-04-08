import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useWallPlayer } from './useWallPlayer';
import { resolveWallPersistentStorage } from '../runtime-capabilities';

const getWallBootMock = vi.fn();
const sendWallHeartbeatMock = vi.fn();
const useWallRealtimeMock = vi.fn();
const clearWallAssetCachesMock = vi.fn();
const getWallCacheDiagnosticsMock = vi.fn();

const engineMock = {
  state: {
    code: 'ABCD1234',
    status: 'playing' as const,
    event: null,
    settings: null,
    items: [
      {
        id: 'media_1',
        url: 'https://cdn.example.com/media-1.jpg',
        type: 'image' as const,
        sender_name: 'Maria',
        sender_key: 'sender-maria',
        senderKey: 'sender-maria',
        source_type: 'whatsapp',
        caption: 'Legenda',
        duplicate_cluster_key: null,
        duplicateClusterKey: null,
        is_featured: false,
        created_at: '2026-04-02T09:55:00Z',
        assetStatus: 'ready' as const,
        playedAt: null,
        playCount: 0,
        lastError: null,
        orientation: null,
        width: 1200,
        height: 900,
      },
    ],
    senderStats: {},
    currentIndex: 0,
    currentItemId: 'media_1',
  },
  currentItem: {
    id: 'media_1',
    senderKey: 'sender-maria',
  },
  errorMessage: null,
  applySnapshot: vi.fn(),
  applySettings: vi.fn(),
  handleStatusChanged: vi.fn(),
  handleNewMedia: vi.fn(),
  handleMediaUpdated: vi.fn(),
  handleMediaDeleted: vi.fn(),
  handleAdsUpdated: vi.fn(),
  handleAdFinished: vi.fn(),
  markExpired: vi.fn(),
  markSyncError: vi.fn(),
  resetAssetStatuses: vi.fn(),
  resetRuntime: vi.fn(),
};

vi.mock('../api', () => ({
  getWallBoot: (...args: unknown[]) => getWallBootMock(...args),
  sendWallHeartbeat: (...args: unknown[]) => sendWallHeartbeatMock(...args),
  WallUnavailableError: class WallUnavailableError extends Error {},
}));

vi.mock('../engine/cache', () => ({
  clearWallAssetCaches: (...args: unknown[]) => clearWallAssetCachesMock(...args),
  getWallCacheDiagnostics: (...args: unknown[]) => getWallCacheDiagnosticsMock(...args),
}));

vi.mock('./useWallEngine', () => ({
  useWallEngine: () => engineMock,
}));

vi.mock('./useWallRealtime', () => ({
  useWallRealtime: (...args: unknown[]) => useWallRealtimeMock(...args),
}));

async function flushAsyncWork() {
  await act(async () => {
    await Promise.resolve();
    await Promise.resolve();
  });
}

describe('useWallPlayer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    window.localStorage.clear();
    engineMock.state.items = [
      {
        ...engineMock.state.items[0],
        assetStatus: 'ready',
      },
    ];

    getWallBootMock.mockResolvedValue({
      event: {
        id: 1,
        title: 'Evento',
        slug: 'evento',
        wall_code: 'ABCD1234',
        status: 'live',
      },
      files: [],
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
      ads: [
        {
          id: 1,
          url: 'https://cdn.example.com/ad-1.jpg',
          media_type: 'image',
          duration_seconds: 10,
          position: 0,
        },
      ],
    });
    sendWallHeartbeatMock.mockResolvedValue(undefined);
    clearWallAssetCachesMock.mockResolvedValue(undefined);
    getWallCacheDiagnosticsMock.mockResolvedValue({
      cacheEnabled: true,
      usageBytes: 262144,
      quotaBytes: 2097152,
      hitCount: 6,
      missCount: 2,
      staleFallbackCount: 1,
      hitRate: 75,
    });
    useWallRealtimeMock.mockReturnValue({ connectionStatus: 'connected' });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('sends heartbeat payload after boot and on the interval', async () => {
    renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    expect(getWallBootMock).toHaveBeenCalledTimes(1);
    expect(engineMock.applySnapshot).toHaveBeenCalledTimes(1);

    await flushAsyncWork();

    expect(
      sendWallHeartbeatMock.mock.calls.some(([, payload]) => payload.last_sync_at != null),
    ).toBe(true);

    const syncedPayload = sendWallHeartbeatMock.mock.calls.find(([, payload]) => payload.last_sync_at != null)?.[1];

    expect(syncedPayload).toEqual(expect.objectContaining({
      player_instance_id: expect.any(String),
      runtime_status: 'playing',
      connection_status: 'connected',
      current_item_id: 'media_1',
      current_sender_key: 'sender-maria',
      ready_count: 1,
      loading_count: 0,
      error_count: 0,
      stale_count: 0,
      cache_enabled: true,
      persistent_storage: resolveWallPersistentStorage(),
      cache_usage_bytes: 262144,
      cache_quota_bytes: 2097152,
      cache_hit_count: 6,
      cache_miss_count: 2,
      cache_stale_fallback_count: 1,
    }));

    const initialCallCount = sendWallHeartbeatMock.mock.calls.length;

    await act(async () => {
      vi.advanceTimersByTime(20_000);
      await Promise.resolve();
    });

    expect(sendWallHeartbeatMock.mock.calls.length).toBeGreaterThan(initialCallCount);
  });

  it('reports stale assets separately in the heartbeat payload', async () => {
    engineMock.state.items = [
      {
        ...engineMock.state.items[0],
        assetStatus: 'stale',
      },
    ];

    renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    const payload = sendWallHeartbeatMock.mock.calls.at(-1)?.[1];

    expect(payload).toEqual(expect.objectContaining({
      ready_count: 0,
      stale_count: 1,
      loading_count: 0,
      error_count: 0,
    }));
  });

  it('handles a clear-cache player command from realtime', async () => {
    renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    const config = useWallRealtimeMock.mock.calls[0]?.[0] as {
      onPlayerCommand?: (payload: { command: string; reason?: string | null; issued_at?: string }) => void;
    };

    act(() => {
      config.onPlayerCommand?.({
        command: 'clear-cache',
        reason: 'manager_clear_cache',
        issued_at: '2026-04-02T10:00:00Z',
      });
    });

    await flushAsyncWork();

    expect(clearWallAssetCachesMock).toHaveBeenCalled();
    expect(engineMock.resetAssetStatuses).toHaveBeenCalled();
  });

  it('routes boot and realtime ad payloads into the wall engine', async () => {
    renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    expect(engineMock.applySnapshot).toHaveBeenCalledWith(expect.objectContaining({
      ads: [
        {
          id: 1,
          url: 'https://cdn.example.com/ad-1.jpg',
          media_type: 'image',
          duration_seconds: 10,
          position: 0,
        },
      ],
    }));

    const config = useWallRealtimeMock.mock.calls[0]?.[0] as {
      onAdsUpdated?: (payload: {
        ads: Array<{
          id: number;
          url: string;
          media_type: 'image' | 'video';
          duration_seconds: number;
          position: number;
        }>;
      }) => void;
    };

    act(() => {
      config.onAdsUpdated?.({
        ads: [
          {
            id: 2,
            url: 'https://cdn.example.com/ad-2.mp4',
            media_type: 'video',
            duration_seconds: 0,
            position: 0,
          },
        ],
        });
    });

    expect(engineMock.handleAdsUpdated).toHaveBeenCalledWith([
      {
        id: 2,
        url: 'https://cdn.example.com/ad-2.mp4',
        media_type: 'video',
        duration_seconds: 0,
        position: 0,
      },
    ]);
  });
});

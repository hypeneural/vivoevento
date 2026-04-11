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
    ads: [],
    currentAd: null,
    adBaseItemId: null,
    adScheduler: {
      mode: 'disabled' as const,
      frequency: 5,
      photosSinceLastAd: 0,
      lastAdPlayedAt: null,
      lastAdIndex: -1,
      skipNextAdCheck: false,
    },
    senderStats: {},
    currentIndex: 0,
    currentItemId: 'media_1',
    currentItemStartedAt: '2026-04-09T03:10:05.000Z',
    videoPlayback: {
      itemId: null,
      phase: 'idle' as const,
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
  },
  currentItem: {
    id: 'media_1',
    senderKey: 'sender-maria',
    type: 'image' as const,
  },
  currentItemStartedAt: '2026-04-09T03:10:05.000Z',
  errorMessage: null,
  applySnapshot: vi.fn(),
  applySettings: vi.fn(),
  handleStatusChanged: vi.fn(),
  handleNewMedia: vi.fn(),
  handleMediaUpdated: vi.fn(),
  handleMediaDeleted: vi.fn(),
  handleAdsUpdated: vi.fn(),
  handleAdFinished: vi.fn(),
  handleVideoStarting: vi.fn(),
  handleVideoFirstFrame: vi.fn(),
  handleVideoPlaybackReady: vi.fn(),
  handleVideoPlaying: vi.fn(),
  handleVideoProgress: vi.fn(),
  handleVideoWaiting: vi.fn(),
  handleVideoStalled: vi.fn(),
  handleVideoEnded: vi.fn(),
  handleVideoFailure: vi.fn(),
  markExpired: vi.fn(),
  markSyncError: vi.fn(),
  resetAssetStatuses: vi.fn(),
  resetRuntime: vi.fn(),
  videoRuntimeConfig: {
    startupDeadlineMs: 1200,
    stallBudgetMs: 2500,
    resumeMode: 'resume_if_same_item_else_restart' as const,
  },
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
    engineMock.state.ads = [];
    engineMock.state.currentAd = null;
    engineMock.state.adBaseItemId = null;
    engineMock.state.currentItemId = 'media_1';
    engineMock.state.currentItemStartedAt = '2026-04-09T03:10:05.000Z';
    engineMock.currentItem = {
      id: 'media_1',
      senderKey: 'sender-maria',
      type: 'image',
    };
    engineMock.currentItemStartedAt = '2026-04-09T03:10:05.000Z';
    engineMock.state.adScheduler = {
      mode: 'disabled',
      frequency: 5,
      photosSinceLastAd: 0,
      lastAdPlayedAt: null,
      lastAdIndex: -1,
      skipNextAdCheck: false,
    };
    engineMock.state.videoPlayback = {
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
    };

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
        show_side_thumbnails: true,
        accepted_orientation: 'all' as const,
        video_enabled: true,
        video_playback_mode: 'play_to_end_if_short_else_cap' as const,
        video_max_seconds: 15,
        video_resume_mode: 'resume_if_same_item_else_restart' as const,
        video_audio_policy: 'muted' as const,
        video_multi_layout_policy: 'disallow' as const,
        video_preferred_variant: 'wall_video_720p' as const,
        ad_mode: 'disabled' as const,
        ad_frequency: 5,
        ad_interval_minutes: 3,
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
      current_item_started_at: '2026-04-09T03:10:05.000Z',
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

  it('envia heartbeat imediato com o horario autoritativo quando a midia atual muda', async () => {
    const { rerender } = renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    sendWallHeartbeatMock.mockClear();

    engineMock.state.currentItemId = 'media_2';
    engineMock.state.currentItemStartedAt = '2026-04-09T03:10:12.000Z';
    engineMock.currentItem = {
      id: 'media_2',
      senderKey: 'sender-pedro',
    };
    engineMock.currentItemStartedAt = '2026-04-09T03:10:12.000Z';

    rerender();
    await flushAsyncWork();

    expect(sendWallHeartbeatMock).toHaveBeenCalled();
    expect(sendWallHeartbeatMock.mock.calls.at(-1)?.[1]).toEqual(expect.objectContaining({
      current_item_id: 'media_2',
      current_item_started_at: '2026-04-09T03:10:12.000Z',
    }));
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

  it('includes board runtime telemetry counters in the heartbeat payload', async () => {
    const { result } = renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    act(() => {
      result.current.setBoardRuntimeTelemetry({
        boardPieceCount: 6,
        boardBurstCount: 4,
        boardBudgetDowngradeCount: 2,
        decodeBacklogCount: 1,
        boardResetCount: 3,
        boardBudgetDowngradeReason: 'runtime_budget',
      });
    });

    await flushAsyncWork();

    const payload = sendWallHeartbeatMock.mock.calls.at(-1)?.[1];

    expect(payload).toEqual(expect.objectContaining({
      board_piece_count: 6,
      board_burst_count: 4,
      board_budget_downgrade_count: 2,
      decode_backlog_count: 1,
      board_reset_count: 3,
      board_budget_downgrade_reason: 'runtime_budget',
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

  it('applies realtime media updates without forcing a full boot refetch', async () => {
    renderHook(() => useWallPlayer('ABCD1234'));

    await flushAsyncWork();

    getWallBootMock.mockClear();

    const config = useWallRealtimeMock.mock.calls[0]?.[0] as {
      onNewMedia?: (payload: {
        id: string;
        url: string;
        type: 'image' | 'video';
        sender_name?: string | null;
        sender_key?: string | null;
        source_type?: string | null;
        caption?: string | null;
        duplicate_cluster_key?: string | null;
        is_featured?: boolean;
      }) => void;
    };

    act(() => {
      config.onNewMedia?.({
        id: 'media_2',
        url: 'https://cdn.example.com/media-2.jpg',
        type: 'image',
        sender_name: 'Paula',
        sender_key: 'sender-paula',
        source_type: 'whatsapp',
        caption: 'Nova foto',
        duplicate_cluster_key: null,
        is_featured: false,
      });
    });

    expect(engineMock.handleNewMedia).toHaveBeenCalledWith(expect.objectContaining({
      id: 'media_2',
      url: 'https://cdn.example.com/media-2.jpg',
    }));
    expect(getWallBootMock).not.toHaveBeenCalled();
  });
});

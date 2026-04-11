import { afterEach, describe, expect, it, vi } from 'vitest';
import { getWallBoot, getWallState, sendWallHeartbeat, WallUnavailableError } from './api';

describe('wall player api', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads the wall boot payload from the public boot endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
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
            queue_limit: 10,
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
            transition_mode: 'fixed',
            background_url: null,
            partner_logo_url: null,
            show_qr: true,
            show_branding: true,
            show_neon: false,
            neon_text: null,
            neon_color: null,
            show_sender_credit: false,
            instructions_text: null,
          },
        },
      }), { status: 200 }),
    );

    const payload = await getWallBoot('ABCD1234');

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(fetchSpy.mock.calls[0]?.[0]).toEqual(expect.stringContaining('/public/wall/ABCD1234/boot'));
    expect(fetchSpy.mock.calls[0]?.[1]).toEqual({
      headers: { Accept: 'application/json' },
    });
    expect(payload.event.status).toBe('live');
  });

  it('loads the wall state payload from the public state endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          status: 'disabled',
          is_live: false,
          wall_code: 'ABCD1234',
        },
      }), { status: 200 }),
    );

    const payload = await getWallState('ABCD1234');

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(fetchSpy.mock.calls[0]?.[0]).toEqual(expect.stringContaining('/public/wall/ABCD1234/state'));
    expect(fetchSpy.mock.calls[0]?.[1]).toEqual({
      headers: { Accept: 'application/json' },
    });
    expect(payload.status).toBe('disabled');
    expect(payload.is_live).toBe(false);
  });

  it('throws WallUnavailableError when the public endpoint returns 410', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 410 }));

    await expect(getWallBoot('ABCD1234')).rejects.toBeInstanceOf(WallUnavailableError);
  });

  it('posts heartbeat payload to the public wall endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ data: { acknowledged_at: '2026-04-02T10:00:00Z' } }), { status: 200 }),
    );

    await sendWallHeartbeat('ABCD1234', {
      player_instance_id: 'player-alpha',
      runtime_status: 'playing',
      connection_status: 'connected',
      current_item_id: 'media_1',
      current_sender_key: 'sender-maria',
      ready_count: 3,
      loading_count: 1,
      error_count: 0,
      stale_count: 0,
      cache_enabled: true,
      persistent_storage: 'localstorage',
      cache_usage_bytes: 128000,
      cache_quota_bytes: 1048576,
      cache_hit_count: 5,
      cache_miss_count: 1,
      cache_stale_fallback_count: 1,
      last_sync_at: '2026-04-02T09:59:55Z',
      last_fallback_reason: null,
    });

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/public/wall/ABCD1234/heartbeat'),
      expect.objectContaining({
        method: 'POST',
        keepalive: true,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }),
    );
  });
});

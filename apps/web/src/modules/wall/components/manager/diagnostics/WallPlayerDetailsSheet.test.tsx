import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiWallDiagnosticsPlayer } from '@/lib/api-types';

import { WallPlayerDetailsSheet } from './WallPlayerDetailsSheet';

const useIsMobileMock = vi.fn();

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

function makePlayer(overrides: Partial<ApiWallDiagnosticsPlayer> = {}): ApiWallDiagnosticsPlayer {
  return {
    player_instance_id: 'd7e1d73a-abc1-4ff1-9999-cfd4',
    health_status: 'healthy',
    is_online: true,
    runtime_status: 'playing',
    connection_status: 'connected',
    current_item_id: 'media_1',
    current_sender_key: 'whatsapp-554896553954',
    ready_count: 32,
    loading_count: 0,
    error_count: 0,
    stale_count: 0,
    cache_enabled: true,
    persistent_storage: 'indexeddb',
    cache_usage_bytes: 1363148,
    cache_quota_bytes: 10737418240,
    cache_hit_count: 24,
    cache_miss_count: 4,
    cache_stale_fallback_count: 0,
    cache_hit_rate: 86,
    last_sync_at: '2026-04-08T22:04:34Z',
    last_seen_at: '2026-04-08T22:04:41Z',
    last_fallback_reason: null,
    updated_at: '2026-04-08T22:04:41Z',
    ...overrides,
  };
}

describe('WallPlayerDetailsSheet', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('usa sheet lateral no desktop com copy operacional', async () => {
    useIsMobileMock.mockReturnValue(false);

    render(
      <WallPlayerDetailsSheet
        open
        player={makePlayer()}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-player-details-sheet')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da tela conectada/i)).toBeInTheDocument();
    expect(screen.getByText(/Tudo esta estavel nesta tela agora/i)).toBeInTheDocument();
    expect(screen.getByText(/Convidado via WhatsApp/i)).toBeInTheDocument();
  });

  it('usa drawer no mobile', async () => {
    useIsMobileMock.mockReturnValue(true);

    render(
      <WallPlayerDetailsSheet
        open
        player={makePlayer({ health_status: 'degraded', connection_status: 'reconnecting' })}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-player-details-drawer')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da tela conectada/i)).toBeInTheDocument();
  });
});

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { ApiWallDiagnosticsPlayer } from '@/lib/api-types';

import { WallPlayerRuntimeCard } from './WallPlayerRuntimeCard';

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

describe('WallPlayerRuntimeCard', () => {
  it('destaca player saudavel com tom verde', () => {
    render(<WallPlayerRuntimeCard player={makePlayer()} />);

    const card = screen.getByText(/Tela d7e1d73a\.\.\.cfd4/i).closest('[data-health-status]');

    expect(card).toHaveAttribute('data-health-status', 'healthy');
    expect(card?.className).toContain('border-emerald-500/30');
    expect(screen.getByText(/Saudavel/i).className).toContain('text-emerald-700');
  });

  it('destaca player com instabilidade em tom laranja', () => {
    render(<WallPlayerRuntimeCard player={makePlayer({
      health_status: 'degraded',
      connection_status: 'reconnecting',
      runtime_status: 'paused',
    })} />);

    const card = screen.getByText(/Tela d7e1d73a\.\.\.cfd4/i).closest('[data-health-status]');

    expect(card).toHaveAttribute('data-health-status', 'degraded');
    expect(card?.className).toContain('border-amber-500/30');
    expect(screen.getByText(/Com instabilidade/i).className).toContain('text-amber-700');
  });

  it('usa copy operacional em vez de rotulos tecnicos crus', () => {
    render(<WallPlayerRuntimeCard player={makePlayer()} />);

    expect(screen.getByText(/Remetente atual/i)).toBeInTheDocument();
    expect(screen.getByText(/Midias carregadas/i)).toBeInTheDocument();
    expect(screen.getByText(/Aproveitamento do cache/i)).toBeInTheDocument();
    expect(screen.getByText(/Espaco no navegador/i)).toBeInTheDocument();
    expect(screen.getByText(/Convidado via WhatsApp/i)).toBeInTheDocument();
  });
});

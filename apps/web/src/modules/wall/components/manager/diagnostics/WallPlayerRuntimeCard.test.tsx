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
    active_transition_effect: 'fade',
    transition_mode: 'fixed',
    transition_random_pick_count: 0,
    transition_fallback_count: 0,
    transition_last_fallback_reason: null,
    board_piece_count: 0,
    board_burst_count: 0,
    board_budget_downgrade_count: 0,
    decode_backlog_count: 0,
    board_reset_count: 0,
    board_budget_downgrade_reason: null,
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

    expect(screen.getByText(/Situacao atual/i)).toBeInTheDocument();
    expect(screen.getByText(/Conexao agora/i)).toBeInTheDocument();
    expect(screen.getByText(/Quem esta na tela/i)).toBeInTheDocument();
    expect(screen.getByText(/Fila pronta/i)).toBeInTheDocument();
    expect(screen.getByText(/Convidado via WhatsApp/i)).toBeInTheDocument();
    expect(screen.getByText(/Tudo esta estavel nesta tela agora/i)).toBeInTheDocument();
  });

  it('mostra counters do board e o motivo do downgrade quando o puzzle cai de 9 para 6 pecas', () => {
    render(<WallPlayerRuntimeCard player={makePlayer({
      board_piece_count: 6,
      board_burst_count: 8,
      board_budget_downgrade_count: 2,
      decode_backlog_count: 1,
      board_reset_count: 3,
      board_budget_downgrade_reason: 'small_stage',
    })} />);

    expect(screen.getByText(/Board 6 pecas/i)).toBeInTheDocument();
    expect(screen.getByText(/Bursts 8/i)).toBeInTheDocument();
    expect(screen.getByText(/Backlog decode 1/i)).toBeInTheDocument();
    expect(screen.getByText(/Reset board 3/i)).toBeInTheDocument();
    expect(screen.getByText(/Downgrade palco reduzido/i)).toBeInTheDocument();
  });

  it('mostra o modo rand e o efeito ativo que o runtime escolheu', () => {
    render(<WallPlayerRuntimeCard player={makePlayer({
      active_transition_effect: 'cross-zoom',
      transition_mode: 'random',
      transition_random_pick_count: 7,
    })} />);

    expect(screen.getByText(/Transicao Cross-zoom/i)).toBeInTheDocument();
    expect(screen.getByText(/Modo rand \| picks 7x/i)).toBeInTheDocument();
  });

  it('explicita quando a tela caiu para fallback seguro de transicao', () => {
    render(<WallPlayerRuntimeCard player={makePlayer({
      active_transition_effect: 'none',
      transition_fallback_count: 2,
      transition_last_fallback_reason: 'reduced_motion',
    })} />);

    expect(screen.getByText(/Fallback motion reduzido \| 2x/i)).toBeInTheDocument();
    expect(screen.getByText(/A tela esta rodando com motion reduzido/i)).toBeInTheDocument();
  });
});

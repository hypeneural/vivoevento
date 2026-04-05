import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import { fallbackOptions } from '../manager-config';
import EventWallManagerPage from './EventWallManagerPage';

const getEventDetailMock = vi.fn();
const getEventWallSettingsMock = vi.fn();
const getEventWallDiagnosticsMock = vi.fn();
const getWallOptionsMock = vi.fn();
const simulateEventWallMock = vi.fn();
const runEventWallPlayerCommandMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
}));

vi.mock('../api', () => ({
  getEventWallSettings: (...args: unknown[]) => getEventWallSettingsMock(...args),
  getEventWallDiagnostics: (...args: unknown[]) => getEventWallDiagnosticsMock(...args),
  getWallOptions: (...args: unknown[]) => getWallOptionsMock(...args),
  simulateEventWall: (...args: unknown[]) => simulateEventWallMock(...args),
  runEventWallPlayerCommand: (...args: unknown[]) => runEventWallPlayerCommandMock(...args),
  updateEventWallSettings: vi.fn(),
  runEventWallAction: vi.fn(),
}));

vi.mock('../hooks/useWallManagerRealtime', () => ({
  useWallManagerRealtime: () => 'connected',
  realtimeLabel: () => 'Atualizacao ao vivo ativa',
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <MemoryRouter initialEntries={['/events/1/wall']}>
          <Routes>
            <Route path="/events/:id/wall" element={<EventWallManagerPage />} />
          </Routes>
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('EventWallManagerPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    getEventDetailMock.mockResolvedValue({
      id: 1,
      title: 'Evento Vivo',
      status: 'active',
      starts_at: '2026-04-02T18:00:00Z',
      location_name: 'Salao Principal',
      module_flags: {
        wall: true,
      },
    });

    getEventWallSettingsMock.mockResolvedValue({
      id: 10,
      event_id: 1,
      wall_code: 'ABCD1234',
      is_enabled: true,
      status: 'live',
      status_label: 'Ao vivo',
      public_url: 'https://example.com/wall/ABCD1234',
      settings: {
        interval_ms: 8000,
        queue_limit: 50,
        selection_mode: 'balanced',
        event_phase: 'flow',
        selection_policy: fallbackOptions.selection_modes[0].selection_policy,
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
        instructions_text: 'Envie sua foto',
      },
      diagnostics_summary: {
        health_status: 'healthy',
        total_players: 1,
        online_players: 1,
        offline_players: 0,
        degraded_players: 0,
        ready_count: 12,
        loading_count: 1,
        error_count: 0,
        stale_count: 0,
        cache_enabled_players: 1,
        persistent_storage_players: 1,
        cache_hit_rate_avg: 86,
        cache_usage_bytes_max: 1048576,
        cache_quota_bytes_max: 8388608,
        cache_stale_fallback_count: 1,
        last_seen_at: '2026-04-02T21:00:00Z',
        updated_at: '2026-04-02T21:00:00Z',
      },
      expires_at: null,
      created_at: '2026-04-02T20:00:00Z',
      updated_at: '2026-04-02T21:00:00Z',
    });

    getWallOptionsMock.mockResolvedValue(fallbackOptions);

    getEventWallDiagnosticsMock.mockResolvedValue({
      summary: {
        health_status: 'healthy',
        total_players: 1,
        online_players: 1,
        offline_players: 0,
        degraded_players: 0,
        ready_count: 12,
        loading_count: 1,
        error_count: 0,
        stale_count: 0,
        cache_enabled_players: 1,
        persistent_storage_players: 1,
        cache_hit_rate_avg: 86,
        cache_usage_bytes_max: 1048576,
        cache_quota_bytes_max: 8388608,
        cache_stale_fallback_count: 1,
        last_seen_at: '2026-04-02T21:00:00Z',
        updated_at: '2026-04-02T21:00:00Z',
      },
      players: [
        {
          player_instance_id: 'player-alpha',
          health_status: 'healthy',
          is_online: true,
          runtime_status: 'playing',
          connection_status: 'connected',
          current_item_id: 'media_1',
          current_sender_key: 'sender-maria',
          ready_count: 12,
          loading_count: 1,
          error_count: 0,
          stale_count: 0,
          cache_enabled: true,
          persistent_storage: 'localstorage',
          cache_usage_bytes: 1048576,
          cache_quota_bytes: 8388608,
          cache_hit_count: 12,
          cache_miss_count: 2,
          cache_stale_fallback_count: 1,
          cache_hit_rate: 86,
          last_sync_at: '2026-04-02T20:59:55Z',
          last_seen_at: '2026-04-02T21:00:00Z',
          last_fallback_reason: null,
          updated_at: '2026-04-02T21:00:00Z',
        },
      ],
      updated_at: '2026-04-02T21:00:00Z',
    });

    simulateEventWallMock.mockResolvedValue({
      summary: {
        selection_mode: 'balanced',
        selection_mode_label: 'Equilibrado',
        event_phase: 'flow',
        event_phase_label: 'Fluxo',
        queue_items: 42,
        active_senders: 9,
        estimated_first_appearance_seconds: 55,
        monopolization_risk: 'low',
        freshness_intensity: 'medium',
        fairness_level: 'high',
      },
      sequence_preview: [
        {
          position: 1,
          eta_seconds: 0,
          item_id: 'media_1',
          sender_name: 'Ana',
          sender_key: 'sender-ana',
          duplicate_cluster_key: null,
          is_featured: false,
          is_replay: false,
          created_at: '2026-04-02T20:50:00Z',
        },
        {
          position: 2,
          eta_seconds: 8,
          item_id: 'media_2',
          sender_name: 'Pedro',
          sender_key: 'sender-pedro',
          duplicate_cluster_key: null,
          is_featured: false,
          is_replay: false,
          created_at: '2026-04-02T20:50:30Z',
        },
      ],
      explanation: [
        'Equilibrado: a simulacao usou a fila real atual do evento com o draft das configuracoes do wall.',
      ],
    });

    runEventWallPlayerCommandMock.mockResolvedValue({
      message: 'Comando enviado aos players do wall.',
      command: 'clear-cache',
      issued_at: '2026-04-02T21:00:00Z',
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders the simulation and diagnostics blocks with realtime wall data', async () => {
    renderPage();

    expect(await screen.findByText(/Diagnostico operacional/i)).toBeInTheDocument();
    expect(screen.getByText(/Simulacao do comportamento/i)).toBeInTheDocument();
    expect(screen.getByText(/Player player-alpha/i)).toBeInTheDocument();

    await waitFor(() => {
      expect(simulateEventWallMock).toHaveBeenCalled();
    });

    expect(await screen.findByText(/Ana/)).toBeInTheDocument();
    expect(await screen.findByText(/55s/)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getAllByText(/Saudavel/i).length).toBeGreaterThan(0);
    });
  });

  it('sends a player command from the diagnostics panel', async () => {
    renderPage();

    const clearCacheButton = await screen.findByRole('button', { name: /Limpar cache/i });
    clearCacheButton.click();

    await waitFor(() => {
      expect(runEventWallPlayerCommandMock).toHaveBeenCalledWith('1', 'clear-cache', 'manager_clear_cache');
    });
  });
});

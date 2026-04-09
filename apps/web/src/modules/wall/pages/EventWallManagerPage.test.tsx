import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import { fallbackOptions } from '../manager-config';
import EventWallManagerPage from './EventWallManagerPage';

const getEventDetailMock = vi.fn();
const getEventWallSettingsMock = vi.fn();
const getEventWallDiagnosticsMock = vi.fn();
const getEventWallInsightsMock = vi.fn();
const getEventWallLiveSnapshotMock = vi.fn();
const getEventWallAdsMock = vi.fn();
const getWallOptionsMock = vi.fn();
const simulateEventWallMock = vi.fn();
const runEventWallPlayerCommandMock = vi.fn();
const createEventWallAdMock = vi.fn();
const deleteEventWallAdMock = vi.fn();
const reorderEventWallAdsMock = vi.fn();
const updateEventWallSettingsMock = vi.fn();

vi.mock('@/modules/events/api', () => ({
  getEventDetail: (...args: unknown[]) => getEventDetailMock(...args),
}));

vi.mock('../api', () => ({
  getEventWallSettings: (...args: unknown[]) => getEventWallSettingsMock(...args),
  getEventWallDiagnostics: (...args: unknown[]) => getEventWallDiagnosticsMock(...args),
  getEventWallInsights: (...args: unknown[]) => getEventWallInsightsMock(...args),
  getEventWallLiveSnapshot: (...args: unknown[]) => getEventWallLiveSnapshotMock(...args),
  getEventWallAds: (...args: unknown[]) => getEventWallAdsMock(...args),
  getWallOptions: (...args: unknown[]) => getWallOptionsMock(...args),
  simulateEventWall: (...args: unknown[]) => simulateEventWallMock(...args),
  runEventWallPlayerCommand: (...args: unknown[]) => runEventWallPlayerCommandMock(...args),
  createEventWallAd: (...args: unknown[]) => createEventWallAdMock(...args),
  deleteEventWallAd: (...args: unknown[]) => deleteEventWallAdMock(...args),
  reorderEventWallAds: (...args: unknown[]) => reorderEventWallAdsMock(...args),
  updateEventWallSettings: (...args: unknown[]) => updateEventWallSettingsMock(...args),
  runEventWallAction: vi.fn(),
}));

vi.mock('../hooks/useWallRealtimeSync', () => ({
  useWallRealtimeSync: () => 'connected',
  realtimeLabel: () => 'Atualizacao ao vivo ativa',
}));

vi.mock('../hooks/useWallPollingFallback', () => ({
  useWallPollingFallback: () => ({
    isPollingFallbackActive: false,
    eventIntervalMs: false,
    settingsIntervalMs: false,
    insightsIntervalMs: false,
    liveSnapshotIntervalMs: false,
    diagnosticsIntervalMs: false,
  }),
}));

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => false,
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

    const wallSettingsResponse = {
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
        show_side_thumbnails: true,
        accepted_orientation: 'all',
        video_enabled: true,
        video_playback_mode: 'play_to_end_if_short_else_cap',
        video_max_seconds: 20,
        video_resume_mode: 'resume_if_same_item_else_restart',
        video_audio_policy: 'muted',
        video_multi_layout_policy: 'disallow',
        video_preferred_variant: 'wall_video_720p',
        ad_mode: 'by_photos' as const,
        ad_frequency: 5,
        ad_interval_minutes: 3,
        instructions_text: 'Envie sua foto',
      },
      diagnostics_summary: {
        health_status: 'healthy' as const,
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
      video_pipeline: {
        ffmpeg_bin: 'ffmpeg',
        ffprobe_bin: 'ffprobe',
        ffmpeg_available: false,
        ffprobe_available: false,
        ffmpeg_resolved_path: null,
        ffprobe_resolved_path: null,
        ready: false,
      },
      expires_at: null,
      created_at: '2026-04-02T20:00:00Z',
      updated_at: '2026-04-02T21:00:00Z',
    };

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

    getEventWallSettingsMock.mockResolvedValue(wallSettingsResponse);

    getWallOptionsMock.mockResolvedValue(fallbackOptions);
    getEventWallInsightsMock.mockResolvedValue({
      topContributor: {
        senderKey: 'whatsapp:5511999990001',
        displayName: 'Carla',
        maskedContact: '5511...01',
        source: 'whatsapp',
        mediaCount: 4,
        lastSentAt: '2026-04-02T20:58:00Z',
        avatarUrl: null,
      },
      totals: {
        received: 24,
        approved: 20,
        queued: 14,
        displayed: 9,
      },
      recentItems: [
        {
          id: 'recent-carla',
          previewUrl: 'https://cdn.example.com/recent-carla.jpg',
          senderName: 'Carla',
          senderKey: 'whatsapp:5511999990001',
          source: 'whatsapp',
          createdAt: '2026-04-02T20:58:00Z',
          approvedAt: '2026-04-02T20:58:20Z',
          displayedAt: null,
          status: 'queued',
          isFeatured: false,
          isReplay: false,
        },
        {
          id: 'recent-diego',
          previewUrl: 'https://cdn.example.com/recent-diego.jpg',
          senderName: 'Diego',
          senderKey: 'upload:diego',
          source: 'upload',
          createdAt: '2026-04-02T20:59:00Z',
          approvedAt: null,
          displayedAt: null,
          status: 'received',
          isFeatured: true,
          isReplay: false,
        },
      ],
      sourceMix: [
        { source: 'whatsapp', count: 18 },
        { source: 'upload', count: 6 },
      ],
      lastCaptureAt: '2026-04-02T20:59:00Z',
    });
    getEventWallLiveSnapshotMock.mockResolvedValue({
      wallStatus: 'live',
      wallStatusLabel: 'Ao vivo',
      layout: 'auto',
      transitionEffect: 'fade',
      currentPlayer: {
        playerInstanceId: 'player-alpha',
        healthStatus: 'healthy',
        runtimeStatus: 'playing',
        connectionStatus: 'connected',
        lastSeenAt: '2026-04-02T21:00:00Z',
      },
      currentItem: {
        id: 'media_99',
        previewUrl: 'https://cdn.example.com/live-now.jpg',
        senderName: 'Juliana Ribeiro',
        senderKey: 'whatsapp:5511999990009',
        source: 'whatsapp',
        caption: 'Entrada da pista',
        layoutHint: 'cinematic',
        isFeatured: false,
        createdAt: '2026-04-02T20:59:00Z',
      },
      nextItem: {
        id: 'media_100',
        previewUrl: 'https://cdn.example.com/live-next.jpg',
        senderName: 'Bruno Costa',
        senderKey: 'upload:bruno',
        source: 'upload',
        caption: 'Proxima entrada da pista',
        layoutHint: 'polaroid',
        isFeatured: false,
        isVideo: false,
        durationSeconds: null,
        createdAt: '2026-04-02T20:58:00Z',
      },
      advancedAt: '2026-04-02T20:59:52Z',
      updatedAt: '2026-04-02T21:00:00Z',
    });
    getEventWallAdsMock.mockResolvedValue([
      {
        id: 1,
        url: 'https://cdn.example.com/ad-1.jpg',
        media_type: 'image',
        duration_seconds: 10,
        position: 0,
      },
      {
        id: 2,
        url: 'https://cdn.example.com/ad-2.mp4',
        media_type: 'video',
        duration_seconds: 0,
        position: 1,
      },
    ]);

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
          current_media_type: 'video',
          current_video_phase: 'playing',
          current_video_exit_reason: 'ended',
          current_video_failure_reason: null,
          current_video_position_seconds: 6,
          current_video_duration_seconds: 18,
          current_video_ready_state: 4,
          current_video_stall_count: 0,
          current_video_poster_visible: false,
          current_video_first_frame_ready: true,
          current_video_playback_ready: true,
          current_video_playing_confirmed: true,
          current_video_startup_degraded: false,
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
          preview_url: 'https://cdn.example.com/upcoming-ana.jpg',
          sender_name: 'Ana',
          sender_key: 'sender-ana',
          source_type: 'upload',
          caption: 'Entrada principal',
          layout_hint: 'cinematic',
          duplicate_cluster_key: null,
          is_featured: false,
          is_replay: false,
          created_at: '2026-04-02T20:50:00Z',
        },
        {
          position: 2,
          eta_seconds: 8,
          item_id: 'media_2',
          preview_url: null,
          sender_name: 'Pedro',
          sender_key: 'sender-pedro',
          source_type: 'whatsapp',
          caption: null,
          layout_hint: 'fullscreen',
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
    createEventWallAdMock.mockResolvedValue({
      id: 3,
      url: 'https://cdn.example.com/ad-3.jpg',
      media_type: 'image',
      duration_seconds: 15,
      position: 2,
    });
    deleteEventWallAdMock.mockResolvedValue(undefined);
    reorderEventWallAdsMock.mockResolvedValue({ reordered: true });
    updateEventWallSettingsMock.mockImplementation(async (_eventId: string, payload: unknown) => ({
      ...wallSettingsResponse,
      settings: payload,
      updated_at: '2026-04-02T21:05:00Z',
    }));
    vi.spyOn(window, 'confirm').mockReturnValue(true);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('renders the simulation and diagnostics blocks with realtime wall data', async () => {
    renderPage();

    await waitFor(() => {
      expect(screen.getAllByText(/Quem mais enviou/i).length).toBeGreaterThan(0);
    });
    expect(screen.getByText(/Total de midias/i)).toBeInTheDocument();
    expect(screen.getByText(/Ultimas chegadas/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Canvas da previa do rascunho/i)).toBeInTheDocument();
    expect(await screen.findByText(/Diagnostico operacional/i)).toBeInTheDocument();
    expect(screen.getByText(/Videos curtos tocam ate o fim; acima disso o wall limita a 20s/i)).toBeInTheDocument();
    expect(screen.getByText(/Os binarios de video ainda nao estao resolvidos neste ambiente/i)).toBeInTheDocument();
    expect(screen.getByText(/Tela player-alpha/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Agora no telao/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Juliana Ribeiro/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Proxima no telao/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Bruno Costa/i).length).toBeGreaterThan(0);

    await waitFor(() => {
      expect(simulateEventWallMock).toHaveBeenCalled();
    });

    const aoVivoTab = screen.getByRole('tab', { name: /Ao vivo/i });

    await act(async () => {
      aoVivoTab.focus();
      fireEvent.keyDown(aoVivoTab, { key: 'ArrowRight' });
      await Promise.resolve();
    });

    expect(await screen.findByText(/Ana/)).toBeInTheDocument();
    expect(screen.getAllByText(/^Upload$/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Layout Cinematografico/i)).toBeInTheDocument();
    expect(screen.getByText(/Entrada principal/i)).toBeInTheDocument();
    expect(await screen.findByText(/55s/)).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getAllByText(/Saudavel/i).length).toBeGreaterThan(0);
    });
  }, 15000);

  it('mantem a selecao manual no palco mesmo quando o snapshot traz agora e proxima', async () => {
    renderPage();

    fireEvent.click(await screen.findByRole('button', {
      name: /Selecionar midia recente de Carla/i,
    }));

    await waitFor(() => {
      expect(screen.getAllByText(/Midia selecionada do topo/i).length).toBeGreaterThan(0);
    });

    expect(screen.getAllByText(/Carla/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Agora no telao/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Juliana Ribeiro/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Proxima no telao/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Bruno Costa/i).length).toBeGreaterThan(0);
  }, 15000);

  it('liga o trilho de ultimas chegadas ao palco atual quando uma midia e selecionada', async () => {
    renderPage();

    const recentButton = await screen.findByRole('button', {
      name: /Selecionar midia recente de Carla/i,
    });

    fireEvent.click(recentButton);

    await waitFor(() => {
      expect(screen.getAllByText(/Midia selecionada do topo/i).length).toBeGreaterThan(0);
    });
    expect(screen.getAllByText(/Carla/i).length).toBeGreaterThan(0);
  }, 15000);

  it('abre o detalhe lateral da midia recente selecionada', async () => {
    renderPage();

    fireEvent.click(await screen.findByRole('button', {
      name: /Selecionar midia recente de Carla/i,
    }));

    fireEvent.click(await screen.findByRole('button', {
      name: /Ver detalhes da midia selecionada/i,
    }));

    expect(await screen.findByText(/Detalhes da midia recente/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Carla/i).length).toBeGreaterThan(0);
  }, 15000);

  it('abre o detalhe operacional expandido do player', async () => {
    renderPage();

    fireEvent.click(await screen.findByRole('button', {
      name: /Ver detalhe da tela player-alpha/i,
    }));

    expect(await screen.findByText(/Detalhes da tela conectada/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Tudo esta estavel nesta tela agora/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Situacao atual/i).length).toBeGreaterThan(0);
  }, 15000);

  it('navega pelos comandos principais do toolbar com as setas', async () => {
    renderPage();

    const voltar = await screen.findByRole('link', { name: /Voltar/i });
    const abrirTelao = screen.getByRole('button', { name: /Abrir telao/i });
    const pausar = screen.getByRole('button', { name: /^Pausar$/i });

    voltar.focus();
    fireEvent.keyDown(voltar, { key: 'ArrowRight' });
    expect(abrirTelao).toHaveFocus();

    fireEvent.keyDown(abrirTelao, { key: 'ArrowRight' });
    expect(pausar).toHaveFocus();
  }, 15000);

  it('usa ativacao automatica nas tabs do palco', async () => {
    renderPage();

    const aoVivoTab = await screen.findByRole('tab', { name: /Ao vivo/i });

    await act(async () => {
      aoVivoTab.focus();
      fireEvent.keyDown(aoVivoTab, { key: 'ArrowRight' });
      await Promise.resolve();
    });

    const proximasTab = await screen.findByRole('tab', { name: /Proximas fotos/i });

    await waitFor(() => {
      expect(proximasTab).toHaveAttribute('aria-selected', 'true');
    });
  }, 15000);

  it('usa ativacao manual nas tabs do inspector', async () => {
    renderPage();

    const filaTab = await screen.findByRole('tab', { name: /Fila/i });

    await act(async () => {
      filaTab.focus();
      fireEvent.keyDown(filaTab, { key: 'ArrowRight' });
      await Promise.resolve();
    });

    const aparenciaTab = await screen.findByRole('tab', { name: /Aparencia/i });

    expect(aparenciaTab).toHaveFocus();
    expect(aparenciaTab).toHaveAttribute('aria-selected', 'false');
    expect(screen.getAllByText(/Comportamento base/i).length).toBeGreaterThan(0);

    await act(async () => {
      fireEvent.keyDown(aparenciaTab, { key: 'Enter' });
      await Promise.resolve();
    });

    await waitFor(() => {
      expect(aparenciaTab).toHaveAttribute('aria-selected', 'true');
    });
    expect(screen.getAllByText(/Estilo da exibicao/i).length).toBeGreaterThan(0);
  }, 15000);

  it('sends a player command from the diagnostics panel', async () => {
    renderPage();

    const clearCacheButton = await screen.findByRole('button', { name: /Limpar cache/i });
    clearCacheButton.click();

    await waitFor(() => {
      expect(runEventWallPlayerCommandMock).toHaveBeenCalledWith('1', 'clear-cache', 'manager_clear_cache');
    });
  }, 15000);

  it('renders the sponsor ads section and uploads a new creative', async () => {
    renderPage();

    const anunciosTab = await screen.findByRole('tab', { name: /Anuncios/i });
    fireEvent.click(anunciosTab);

    await waitFor(() => {
      expect(anunciosTab).toHaveAttribute('aria-selected', 'true');
    });

    expect(await screen.findByText(/Patrocinadores no telao/i)).toBeInTheDocument();
    expect(await screen.findByText(/Patrocinador 1/i)).toBeInTheDocument();

    const file = new File(['banner'], 'banner.jpg', { type: 'image/jpeg' });
    const fileInput = screen.getByLabelText(/Arquivo do patrocinador/i);

    fireEvent.change(fileInput, {
      target: {
        files: [file],
      },
    });

    fireEvent.click(screen.getByRole('button', { name: /Enviar anuncio/i }));

    await waitFor(() => {
      expect(createEventWallAdMock).toHaveBeenCalledWith('1', {
        file,
        durationSeconds: 10,
      });
    });
  }, 15000);

  it('reorders sponsor creatives from the wall manager', async () => {
    renderPage();

    const anunciosTab = await screen.findByRole('tab', { name: /Anuncios/i });
    fireEvent.click(anunciosTab);

    await waitFor(() => {
      expect(anunciosTab).toHaveAttribute('aria-selected', 'true');
    });

    expect(await screen.findByText(/Patrocinador 1/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Descer anuncio 1/i }));

    await waitFor(() => {
      expect(reorderEventWallAdsMock).toHaveBeenCalledWith('1', [2, 1]);
    });
  }, 15000);

  it('persists sponsor scheduling settings from the wall manager', async () => {
    renderPage();

    const anunciosTab = await screen.findByRole('tab', { name: /Anuncios/i });
    fireEvent.click(anunciosTab);

    await waitFor(() => {
      expect(anunciosTab).toHaveAttribute('aria-selected', 'true');
    });

    expect(await screen.findByText(/Patrocinador 1/i)).toBeInTheDocument();

    const frequencyField = screen.getByText(/Frequencia por fotos/i).parentElement?.querySelector('input');

    expect(frequencyField).not.toBeNull();
    fireEvent.change(frequencyField as HTMLInputElement, {
      target: {
        value: '7',
      },
    });

    const saveButton = screen
      .getAllByRole('button', { name: /Salvar alteracoes/i })
      .find((button) => button.querySelector('.lucide-save') !== null);

    expect(saveButton).toBeDefined();
    fireEvent.click(saveButton as HTMLButtonElement);

    await waitFor(() => {
      expect(updateEventWallSettingsMock).toHaveBeenCalledWith('1', expect.objectContaining({
        ad_mode: 'by_photos',
        ad_frequency: 7,
        ad_interval_minutes: 3,
      }));
    });
  }, 15000);

  it('removes sponsor creatives from the wall manager', async () => {
    renderPage();

    const anunciosTab = await screen.findByRole('tab', { name: /Anuncios/i });
    fireEvent.click(anunciosTab);

    await waitFor(() => {
      expect(anunciosTab).toHaveAttribute('aria-selected', 'true');
    });

    expect(await screen.findByText(/Patrocinador 1/i)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Remover anuncio 1/i }));

    await waitFor(() => {
      expect(deleteEventWallAdMock).toHaveBeenCalledWith('1', 1);
    });
  }, 15000);
});

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PublicGamePage from './PublicGamePage';
import type {
  PublicPlayEventManifest,
  PublicPlayGameResponse,
  StartPlaySessionResponse,
} from '@/lib/api-types';

const fetchPublicPlayManifestMock = vi.fn();
const fetchPublicPlayGameMock = vi.fn();
const startPublicPlaySessionMock = vi.fn();
const toastMock = vi.fn();

vi.mock('@/modules/play/api/playApi', () => ({
  fetchPublicPlayManifest: (...args: unknown[]) => fetchPublicPlayManifestMock(...args),
  fetchPublicPlayGame: (...args: unknown[]) => fetchPublicPlayGameMock(...args),
  startPublicPlaySession: (...args: unknown[]) => startPublicPlaySessionMock(...args),
  finishPublicPlaySession: vi.fn(),
  heartbeatPublicPlaySession: vi.fn(),
  resumePublicPlaySession: vi.fn(),
  storePublicPlayMoves: vi.fn(),
}));

vi.mock('@/modules/play/hooks/usePhaserGame', () => ({
  usePhaserGame: () => ({
    containerRef: { current: null },
    runtimeStatus: 'idle',
    runtimeError: null,
  }),
}));

vi.mock('@/modules/play/realtime/hooks/usePlayRealtime', () => ({
  usePlayRealtime: () => ({
    connectionStatus: 'idle',
  }),
}));

vi.mock('@/modules/play/utils/runtime-prefetch', () => ({
  schedulePlayIdleTask: () => undefined,
  warmPlayableGameRuntime: vi.fn(),
  warmRuntimeAssets: vi.fn(),
}));

vi.mock('@/modules/play/utils/session-storage', () => ({
  clearStoredPlaySession: vi.fn(),
  readStoredPlaySession: vi.fn(() => null),
  writeStoredPlaySession: vi.fn(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

function makeManifest(): PublicPlayEventManifest {
  return {
    event: {
      id: 5,
      title: 'Validacao de Videos WhatsApp - 10.04.2026',
      slug: 'validacao-videos-whatsapp-2026-04-10',
      cover_image_url: null,
      logo_url: null,
      primary_color: null,
      secondary_color: null,
    },
    settings: {
      is_enabled: true,
      ranking_enabled: true,
      auto_refresh_assets: true,
    },
    games: [],
    pwa: {
      installable: true,
      min_version: null,
    },
  };
}

function makeGameResponse(bootable: boolean, reason: string | null): PublicPlayGameResponse {
  return {
    game: {
      id: 9,
      uuid: 'game-uuid-9',
      event_id: 5,
      game_type_key: 'puzzle',
      game_type_name: 'Puzzle',
      title: 'quebra-cabeca-do-anderson',
      slug: 'quebra-cabeca-do-anderson',
      is_active: true,
      sort_order: 1,
      ranking_enabled: true,
      settings: {
        gridSize: '3x3',
      },
      readiness: {
        published: true,
        launchable: bootable,
        bootable,
        reason,
      },
      created_at: '2026-04-11T12:00:00Z',
      updated_at: '2026-04-11T12:00:00Z',
    },
    runtime: {
      assets: bootable ? [{
        id: 'asset-1',
        url: 'https://cdn.example.com/puzzle.webp',
        mimeType: 'image/webp',
        variantKey: 'wall',
        deliveryProfile: 'rich',
      }] : [],
      ranking: [],
      last_plays: [],
      analytics: {
        total_sessions: 0,
        finished_sessions: 0,
        abandoned_sessions: 0,
        active_sessions: 0,
        completion_rate: 0,
        unique_players: 0,
        total_moves: 0,
        average_score: null,
        average_time_ms: null,
        average_moves: null,
        best_score: null,
        last_finished_at: null,
      },
      realtime: {
        channel: 'play.game.game-uuid-9',
        events: {
          leaderboard_updated: 'play.leaderboard.updated',
        },
      },
    },
  };
}

function makeStartResponse(): StartPlaySessionResponse {
  return {
    sessionUuid: 'session-123',
    eventGameId: 9,
    gameKey: 'puzzle',
    sessionSeed: 'seed-123',
    resumeToken: 'resume-123',
    player: {
      identifier: 'player-123',
      name: 'Anderson',
    },
    settings: {
      gridSize: '3x3',
    },
    assets: [{
      id: 'asset-1',
      url: 'https://cdn.example.com/puzzle.webp',
      mimeType: 'image/webp',
      variantKey: 'wall',
      deliveryProfile: 'rich',
    }],
    analytics: {
      total_moves: 0,
      unique_move_types: 0,
      move_type_breakdown: {},
      last_move_number: null,
      first_move_at: null,
      last_move_at: null,
      elapsed_ms: null,
      activity_window_ms: 0,
      completed: false,
      score: null,
      time_ms: null,
      moves_reported: null,
      mistakes: null,
      accuracy: null,
    },
  };
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/e/validacao-videos-whatsapp-2026-04-10/play/quebra-cabeca-do-anderson']}>
        <Routes>
          <Route path="/e/:slug/play/:gameSlug" element={<PublicGamePage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PublicGamePage runtime readiness', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows an unavailable state and disables the CTA when bootable is false', async () => {
    fetchPublicPlayManifestMock.mockResolvedValue(makeManifest());
    fetchPublicPlayGameMock.mockResolvedValue(makeGameResponse(false, 'puzzle.no_image_available'));

    renderPage();

    expect(await screen.findByText('Este jogo ainda nao esta pronto para jogar.')).toBeInTheDocument();
    expect(screen.getAllByText('Falta uma imagem publicada valida para montar o quebra-cabeca.').length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: /jogo indisponivel/i })).toBeDisabled();
    expect(startPublicPlaySessionMock).not.toHaveBeenCalled();
  });

  it('does not call start mutation when the unavailable CTA is clicked', async () => {
    fetchPublicPlayManifestMock.mockResolvedValue(makeManifest());
    fetchPublicPlayGameMock.mockResolvedValue(makeGameResponse(false, 'puzzle.no_image_available'));

    renderPage();

    const button = await screen.findByRole('button', { name: /jogo indisponivel/i });
    expect(button).toBeDisabled();
    fireEvent.click(button);

    expect(startPublicPlaySessionMock).not.toHaveBeenCalled();
    expect(toastMock).not.toHaveBeenCalled();
  });

  it('keeps the CTA enabled and starts a session when bootable is true', async () => {
    fetchPublicPlayManifestMock.mockResolvedValue(makeManifest());
    fetchPublicPlayGameMock.mockResolvedValue(makeGameResponse(true, null));
    startPublicPlaySessionMock.mockResolvedValue(makeStartResponse());

    renderPage();

    const button = await screen.findByRole('button', { name: /iniciar partida/i });
    expect(button).toBeEnabled();

    fireEvent.click(button);

    await waitFor(() => {
      expect(startPublicPlaySessionMock).toHaveBeenCalledTimes(1);
    });
  });
});

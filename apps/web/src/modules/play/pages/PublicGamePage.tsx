import { Suspense, lazy, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { ArrowLeft, Loader2, Menu, Play } from 'lucide-react';
import type { PlayRealtimeLeaderboardPayload } from '@eventovivo/shared-types/play';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/use-toast';
import type {
  PlayGameAnalytics,
  PlayGameSession,
  PlayRankingEntry,
  PlaySessionAnalytics,
  StartPlaySessionResponse,
} from '@/lib/api-types';
import {
  fetchPublicPlayGame,
  fetchPublicPlayManifest,
  finishPublicPlaySession,
  heartbeatPublicPlaySession,
  resumePublicPlaySession,
  startPublicPlaySession,
  storePublicPlayMoves,
} from '@/modules/play/api/playApi';
import { PublicGameRuntimeStatus } from '@/modules/play/components/PublicGameRuntimeStatus';
import { SessionResumeBanner } from '@/modules/play/components/SessionResumeBanner';
import { usePhaserGame } from '@/modules/play/hooks/usePhaserGame';
import type { GameRuntimeMove } from '@/modules/play/phaser/core/runtimeTypes';
import { usePlayRealtime } from '@/modules/play/realtime/hooks/usePlayRealtime';
import type { NormalizedGameResult } from '@/modules/play/types';
import {
  schedulePlayIdleTask,
  warmPlayableGameRuntime,
  warmRuntimeAssets,
} from '@/modules/play/utils/runtime-prefetch';
import {
  parseMemoryRuntimeProgress,
  parsePuzzleRuntimeProgress,
  parseRuntimeLoadingProgress,
} from '@/modules/play/utils/runtime-progress';
import {
  buildInitialRuntimeProgress,
  buildRestoredRuntimeProgress,
} from '@/modules/play/utils/runtime-progress-state';
import { getPlayAssetQueryProfile } from '@/modules/play/utils/play-device-profile';
import {
  clearStoredPlaySession,
  readStoredPlaySession,
  writeStoredPlaySession,
  type StoredPlaySession,
} from '@/modules/play/utils/session-storage';

const PublicGameMenuSheet = lazy(async () => {
  const module = await import('@/modules/play/components/PublicGameMenuSheet');
  return { default: module.PublicGameMenuSheet };
});

const playerSchema = z.object({
  player_name: z.string().max(120).optional(),
});

type PlayerFormValues = z.infer<typeof playerSchema>;

function getPlayerIdentifier(slug: string, gameSlug: string) {
  const storageKey = `eventovivo:play:player:${slug}:${gameSlug}`;

  if (typeof window === 'undefined') {
    return `${slug}-${gameSlug}-anonymous`;
  }

  const stored = window.localStorage.getItem(storageKey);
  if (stored) {
    return stored;
  }

  const generated = window.crypto?.randomUUID?.() ?? `${slug}-${gameSlug}-${Math.random().toString(36).slice(2, 12)}`;
  window.localStorage.setItem(storageKey, generated);

  return generated;
}

function getStoredPlayerName(slug: string) {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.localStorage.getItem(`eventovivo:play:player-name:${slug}`) ?? '';
}

function persistPlayerName(slug: string, playerName?: string) {
  if (typeof window === 'undefined') {
    return;
  }

  if (!playerName) {
    window.localStorage.removeItem(`eventovivo:play:player-name:${slug}`);
    return;
  }

  window.localStorage.setItem(`eventovivo:play:player-name:${slug}`, playerName);
}

function formatElapsed(value: number | null | undefined) {
  if (!value) {
    return '0s';
  }

  const seconds = Math.round(value / 1000);
  const minutes = Math.floor(seconds / 60);
  const remaining = seconds % 60;

  if (minutes === 0) {
    return `${remaining}s`;
  }

  return `${minutes}m ${remaining}s`;
}

function toNormalizedGameResult(result: Record<string, unknown>): NormalizedGameResult | null {
  const score = Number(result.score ?? 0);
  const timeMs = Number(result.time_ms ?? 0);
  const moves = Number(result.moves ?? 0);

  if (!Number.isFinite(score) || !Number.isFinite(timeMs) || !Number.isFinite(moves)) {
    return null;
  }

  const mistakes = result.mistakes === undefined || result.mistakes === null ? undefined : Number(result.mistakes);
  const accuracy = result.accuracy === undefined || result.accuracy === null ? undefined : Number(result.accuracy);

  return {
    score,
    completed: Boolean(result.completed),
    timeMs,
    moves,
    mistakes: Number.isFinite(mistakes ?? NaN) ? mistakes : undefined,
    accuracy: Number.isFinite(accuracy ?? NaN) ? accuracy : undefined,
    metadata: (result.metadata as Record<string, unknown> | undefined) ?? undefined,
  };
}

function connectionLabel(status: string) {
  switch (status) {
    case 'connected':
      return 'Tempo real conectado';
    case 'connecting':
      return 'Conectando tempo real';
    case 'reconnecting':
      return 'Reconectando tempo real';
    case 'error':
      return 'Tempo real indisponivel';
    default:
      return 'Tempo real ocioso';
  }
}

function buildStoredSession(
  eventSlug: string,
  gameSlug: string,
  response: StartPlaySessionResponse,
): StoredPlaySession {
  return {
    eventSlug,
    gameSlug,
    sessionUuid: response.sessionUuid,
    resumeToken: response.resumeToken ?? response.session?.resumeToken ?? '',
    eventGameId: response.eventGameId,
    gameKey: response.gameKey,
    playerIdentifier: response.player.identifier,
    playerName: response.player.name,
    startedAt: response.startedAt ?? response.session?.startedAt ?? null,
    lastActivityAt: response.lastActivityAt ?? response.session?.lastActivityAt ?? null,
    expiresAt: response.expiresAt ?? response.session?.expiresAt ?? null,
    sessionSeed: response.sessionSeed ?? response.session?.seed ?? response.sessionUuid,
  };
}

export default function PublicGamePage() {
  const { slug, gameSlug } = useParams<{ slug: string; gameSlug: string }>();
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const [runtimePayload, setRuntimePayload] = useState<StartPlaySessionResponse | null>(null);
  const [latestResult, setLatestResult] = useState<NormalizedGameResult | null>(null);
  const [progress, setProgress] = useState<Record<string, unknown>>({});
  const [sessionAnalytics, setSessionAnalytics] = useState<PlaySessionAnalytics | null>(null);
  const [ranking, setRanking] = useState<PlayRankingEntry[]>([]);
  const [lastPlays, setLastPlays] = useState<PlayGameSession[]>([]);
  const [gameAnalytics, setGameAnalytics] = useState<PlayGameAnalytics | null>(null);
  const [resumeCandidate, setResumeCandidate] = useState<StoredPlaySession | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const assetProfile = useMemo(() => getPlayAssetQueryProfile(), []);

  const pendingMovesRef = useRef<Array<{
    move_number: number;
    move_type: string;
    payload?: Record<string, unknown>;
    occurred_at?: string;
  }>>([]);
  const moveNumberRef = useRef(0);
  const flushTimerRef = useRef<number | null>(null);
  const isFlushingRef = useRef(false);
  const gameCompletedRef = useRef(false);

  const manifestQuery = useQuery({
    queryKey: ['public-play-manifest', slug],
    enabled: !!slug,
    queryFn: () => fetchPublicPlayManifest(slug as string),
  });

  const gameQuery = useQuery({
    queryKey: ['public-play-game', slug, gameSlug, assetProfile.cacheKey],
    enabled: !!slug && !!gameSlug,
    queryFn: () => fetchPublicPlayGame(slug as string, gameSlug as string, assetProfile.params),
  });

  const playerForm = useForm<PlayerFormValues>({
    resolver: zodResolver(playerSchema),
    defaultValues: {
      player_name: slug ? getStoredPlayerName(slug) : '',
    },
  });

  useEffect(() => {
    if (slug) {
      playerForm.reset({ player_name: getStoredPlayerName(slug) });
    }
  }, [playerForm, slug]);

  useEffect(() => {
    if (!gameQuery.data) {
      return;
    }

    setRanking(gameQuery.data.runtime.ranking);
    setLastPlays(gameQuery.data.runtime.last_plays);
    setGameAnalytics(gameQuery.data.runtime.analytics);
  }, [gameQuery.data]);

  useEffect(() => {
    if (!slug || !gameSlug || !gameQuery.data) {
      return undefined;
    }

    return schedulePlayIdleTask(() => {
      warmRuntimeAssets(gameQuery.data.runtime.assets ?? [], {
        maxItems: gameQuery.data.game.game_type_key === 'puzzle' ? 1 : 2,
      });
    });
  }, [gameQuery.data, gameSlug, slug]);

  useEffect(() => {
    if (!slug || !gameSlug || runtimePayload) {
      return;
    }

    const stored = readStoredPlaySession(slug, gameSlug);

    if (stored?.expiresAt && new Date(stored.expiresAt).getTime() < Date.now()) {
      clearStoredPlaySession(slug, gameSlug);
      setResumeCandidate(null);
      return;
    }

    setResumeCandidate(stored);
  }, [gameSlug, runtimePayload, slug]);

  const playerIdentifier = useMemo(() => {
    if (!slug || !gameSlug) {
      return '';
    }

    return getPlayerIdentifier(slug, gameSlug);
  }, [gameSlug, slug]);

  const primeCurrentGameRuntime = useCallback((force = false) => {
    const gameTypeKey = gameQuery.data?.game.game_type_key;

    if (!gameTypeKey) {
      return;
    }

    void warmPlayableGameRuntime(gameTypeKey, 'intent', { force });
  }, [gameQuery.data?.game.game_type_key]);

  const primeGameMenu = useCallback(() => {
    void import('@/modules/play/components/PublicGameMenuSheet');
  }, []);

  const clearFlushTimer = useCallback(() => {
    if (flushTimerRef.current !== null) {
      window.clearTimeout(flushTimerRef.current);
      flushTimerRef.current = null;
    }
  }, []);

  const flushMoves = useCallback(async (options?: { throwOnError?: boolean }) => {
    if (!runtimePayload || isFlushingRef.current || pendingMovesRef.current.length === 0) {
      return;
    }

    clearFlushTimer();

    const batch = [...pendingMovesRef.current];
    pendingMovesRef.current = [];
    isFlushingRef.current = true;

    try {
      const response = await storePublicPlayMoves(runtimePayload.sessionUuid, { moves: batch });
      setSessionAnalytics(response.analytics);
    } catch (error) {
      pendingMovesRef.current = [...batch, ...pendingMovesRef.current];
      toast({
        title: 'Falha ao sincronizar movimentos',
        description: error instanceof Error ? error.message : 'Nao foi possivel sincronizar a trilha da partida.',
        variant: 'destructive',
      });

      if (options?.throwOnError) {
        throw error;
      }
    } finally {
      isFlushingRef.current = false;

      if (pendingMovesRef.current.length > 0) {
        flushTimerRef.current = window.setTimeout(() => {
          void flushMoves();
        }, 250);
      }
    }
  }, [clearFlushTimer, runtimePayload, toast]);

  const scheduleFlush = useCallback((delay = 350) => {
    clearFlushTimer();
    flushTimerRef.current = window.setTimeout(() => {
      void flushMoves();
    }, delay);
  }, [clearFlushTimer, flushMoves]);

  const handleRealtimeLeaderboardUpdated = useCallback((payload: PlayRealtimeLeaderboardPayload) => {
    setRanking(payload.leaderboard as PlayRankingEntry[]);
    setLastPlays(payload.last_plays as PlayGameSession[]);
    setGameAnalytics(payload.analytics);
  }, []);

  const { connectionStatus } = usePlayRealtime({
    channelName: gameQuery.data?.runtime.realtime.channel,
    onLeaderboardUpdated: handleRealtimeLeaderboardUpdated,
  });

  const finishMutation = useMutation({
    mutationFn: async (result: NormalizedGameResult) => {
      await flushMoves({ throwOnError: true });

      return finishPublicPlaySession(runtimePayload!.sessionUuid, {
        clientResult: {
          score: result.score,
          completed: result.completed,
          timeMs: result.timeMs,
          moves: result.moves,
          mistakes: result.mistakes,
          accuracy: result.accuracy,
          metadata: result.metadata,
        },
      });
    },
    onSuccess: async (response, result) => {
      gameCompletedRef.current = true;
      setLatestResult(toNormalizedGameResult(response.authoritative_result ?? response.result) ?? result);
      setSessionAnalytics(response.analytics);
      setRanking(response.leaderboard);
      setLastPlays(response.last_plays);
      setGameAnalytics(response.game_analytics);
      if (slug && gameSlug) {
        clearStoredPlaySession(slug, gameSlug);
      }
      setResumeCandidate(null);
      await queryClient.invalidateQueries({ queryKey: ['public-play-game', slug, gameSlug] });
      toast({
        title: 'Partida finalizada',
        description: `Pontuacao ${Number((response.authoritative_result ?? response.result).score ?? result.score)} registrada com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao registrar resultado',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const startMutation = useMutation({
    mutationFn: (values: PlayerFormValues) => startPublicPlaySession(slug as string, gameSlug as string, {
      playerIdentifier,
      displayName: values.player_name?.trim() || undefined,
      device: assetProfile.device,
    }),
    onMutate: () => {
      primeCurrentGameRuntime(true);
    },
    onSuccess: (response, values) => {
      clearFlushTimer();
      pendingMovesRef.current = [];
      moveNumberRef.current = 0;
      gameCompletedRef.current = false;
      setProgress(buildInitialRuntimeProgress(response.gameKey, response.settings));
      setLatestResult(null);
      setRuntimePayload(response);
      setSessionAnalytics(response.analytics);
      persistPlayerName(slug as string, values.player_name?.trim());
      if (slug && gameSlug) {
        const stored = buildStoredSession(slug, gameSlug, response);
        writeStoredPlaySession(stored);
        setResumeCandidate(stored);
      }
      toast({
        title: 'Partida iniciada',
        description: 'O jogo ja esta pronto para rodar no navegador.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao iniciar partida',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const resumeMutation = useMutation({
    mutationFn: () => resumePublicPlaySession(resumeCandidate!.sessionUuid, {
      resumeToken: resumeCandidate!.resumeToken,
    }),
    onMutate: () => {
      primeCurrentGameRuntime(true);
    },
    onSuccess: (response) => {
      clearFlushTimer();
      pendingMovesRef.current = [];
      moveNumberRef.current = response.restore?.lastAcceptedMoveNumber ?? 0;
      gameCompletedRef.current = false;
      setLatestResult(null);
      setProgress(buildRestoredRuntimeProgress(response));
      setRuntimePayload(response);
      setSessionAnalytics(response.analytics);

      if (slug && gameSlug) {
        const stored = buildStoredSession(slug, gameSlug, response);
        writeStoredPlaySession(stored);
        setResumeCandidate(stored);
      }

      toast({
        title: 'Sessao retomada',
        description: 'A partida anterior foi reaberta neste dispositivo.',
      });
    },
    onError: (error: Error) => {
      if (slug && gameSlug) {
        clearStoredPlaySession(slug, gameSlug);
      }
      setResumeCandidate(null);
      toast({
        title: 'Falha ao retomar sessao',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const heartbeatMutation = useMutation({
    mutationFn: (payload: { state: 'visible' | 'hidden' | 'backgrounded'; reason?: string; elapsedMs?: number }) =>
      heartbeatPublicPlaySession(runtimePayload!.sessionUuid, payload),
    onSuccess: (response) => {
      setSessionAnalytics(response.analytics);

      if (!slug || !gameSlug || !runtimePayload) {
        return;
      }

      const stored = readStoredPlaySession(slug, gameSlug);
      if (!stored) {
        return;
      }

      const nextStored: StoredPlaySession = {
        ...stored,
        lastActivityAt: response.session.last_activity_at ?? stored.lastActivityAt ?? null,
        expiresAt: response.session.expires_at ?? stored.expiresAt ?? null,
      };
      writeStoredPlaySession(nextStored);
      setResumeCandidate(nextStored);

      if (response.session.status === 'abandoned') {
        setRuntimePayload(null);
        toast({
          title: 'Sessao pausada por inatividade',
          description: 'Use a retomada para continuar se ainda estiver dentro da janela da sessao.',
        });
      }
    },
  });

  const handleRuntimeMove = useCallback((move: GameRuntimeMove) => {
    if (!runtimePayload) {
      return;
    }

    moveNumberRef.current += 1;
    pendingMovesRef.current.push({
      move_number: moveNumberRef.current,
      move_type: move.moveType,
      payload: move.payload,
      occurred_at: move.occurredAt ?? new Date().toISOString(),
    });

    if (pendingMovesRef.current.length >= 5) {
      void flushMoves();
    } else {
      scheduleFlush();
    }
  }, [flushMoves, runtimePayload, scheduleFlush]);

  useEffect(() => () => {
    clearFlushTimer();
  }, [clearFlushTimer]);

  useEffect(() => {
    if (!runtimePayload) {
      return undefined;
    }

    const sendHeartbeat = (state: 'visible' | 'hidden' | 'backgrounded', reason: string) => {
      if (gameCompletedRef.current || finishMutation.isPending) {
        return;
      }

      heartbeatMutation.mutate({
        state,
        reason,
        elapsedMs: latestResult?.timeMs ?? undefined,
      });
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'hidden') {
        void flushMoves();
        sendHeartbeat('hidden', 'visibilitychange');
        return;
      }

      sendHeartbeat('visible', 'visibilitychange');
    };

    const handlePageHide = () => {
      void flushMoves();
      sendHeartbeat('backgrounded', 'pagehide');
    };

    const intervalId = window.setInterval(() => {
      if (document.visibilityState === 'visible') {
        sendHeartbeat('visible', 'interval');
      }
    }, 30000);

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('pagehide', handlePageHide);

    return () => {
      window.clearInterval(intervalId);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('pagehide', handlePageHide);
    };
  }, [finishMutation.isPending, flushMoves, heartbeatMutation, latestResult?.timeMs, runtimePayload]);

  const { containerRef, runtimeStatus, runtimeError } = usePhaserGame({
    payload: runtimePayload
      ? {
          eventGameId: runtimePayload.eventGameId,
          sessionUuid: runtimePayload.sessionUuid,
          gameKey: runtimePayload.gameKey,
          sessionSeed: runtimePayload.sessionSeed ?? runtimePayload.session?.seed ?? runtimePayload.sessionUuid,
          player: runtimePayload.player,
          assets: runtimePayload.assets,
          settings: runtimePayload.settings,
          restore: runtimePayload.restore ?? null,
        }
      : null,
    enabled: !!runtimePayload,
    onProgress: setProgress,
    onMove: handleRuntimeMove,
    onFinish: (result) => {
      if (!finishMutation.isPending) {
        finishMutation.mutate(result);
      }
    },
  });

  useEffect(() => {
    if (!runtimePayload?.assets.length) {
      return;
    }

    warmRuntimeAssets(runtimePayload.assets, {
      maxItems: runtimePayload.gameKey === 'puzzle' ? 1 : 2,
    });
  }, [runtimePayload]);

  if (manifestQuery.isLoading || gameQuery.isLoading) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 text-white">
        <Loader2 className="h-6 w-6 animate-spin" />
      </div>
    );
  }

  if (manifestQuery.isError || gameQuery.isError || !manifestQuery.data || !gameQuery.data) {
    return (
      <div className="flex min-h-[100dvh] items-center justify-center bg-slate-950 px-6 text-center text-sm text-white/80">
        O jogo solicitado nao esta disponivel.
      </div>
    );
  }

  const manifest = manifestQuery.data;
  const gameResponse = gameQuery.data;
  const currentGameKey = runtimePayload?.gameKey ?? gameResponse.game.game_type_key ?? null;
  const localMoves = Number(progress.moves ?? latestResult?.moves ?? 0);
  const sessionStatus = runtimePayload?.status ?? runtimePayload?.session?.status ?? 'idle';
  const resumeDeadline = runtimePayload?.expiresAt ?? runtimePayload?.session?.expiresAt ?? null;
  const memoryProgress = currentGameKey === 'memory' ? parseMemoryRuntimeProgress(progress) : null;
  const puzzleProgress = currentGameKey === 'puzzle' ? parsePuzzleRuntimeProgress(progress) : null;
  const loadingProgress = parseRuntimeLoadingProgress(progress);

  return (
    <div className="min-h-[100dvh] bg-slate-950 text-white">
      <div className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-5 sm:px-6 sm:py-6">
        <div className="flex items-center justify-between gap-3">
          <Button asChild variant="outline" className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white">
            <Link to={`/e/${manifest.event.slug}/play`}>
              <ArrowLeft className="mr-1.5 h-4 w-4" />
              Voltar
            </Link>
          </Button>

          <Button
            type="button"
            variant="outline"
            className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
            onClick={() => setMenuOpen(true)}
            onFocus={primeGameMenu}
            onMouseEnter={primeGameMenu}
            onTouchStart={primeGameMenu}
          >
            <Menu className="mr-1.5 h-4 w-4" />
            Menu
          </Button>
        </div>

        <div className="space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary" className="bg-white/10 text-white hover:bg-white/10">
              {gameResponse.game.game_type_name || gameResponse.game.game_type_key}
            </Badge>
            <Badge variant={gameResponse.game.ranking_enabled ? 'outline' : 'secondary'} className="border-white/20 bg-white/5 text-white">
              {gameResponse.game.ranking_enabled ? 'Ranking ativo' : 'Ranking inativo'}
            </Badge>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">{gameResponse.game.title}</h1>
          <p className="text-sm text-white/70">{manifest.event.title}</p>
        </div>

        {resumeCandidate && !runtimePayload ? (
          <SessionResumeBanner
            playerName={resumeCandidate.playerName}
            expiresAt={resumeCandidate.expiresAt}
            isPending={resumeMutation.isPending}
            onResume={() => {
              primeCurrentGameRuntime(true);
              resumeMutation.mutate();
            }}
            onDiscard={() => {
              if (slug && gameSlug) {
                clearStoredPlaySession(slug, gameSlug);
              }
              setResumeCandidate(null);
            }}
          />
        ) : null}

        <Card className="border-white/10 bg-white/5 shadow-none">
          <CardHeader className="pb-3">
            <CardTitle className="text-white">Entre e jogue</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <p className="text-sm font-medium text-white/90">
                  O apelido e opcional. Ranking, analytics e historico ficam no menu.
                </p>
                <p className="text-xs text-white/55">
                  A tela principal fica limpa para o usuario entrar e jogar rapido no celular.
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <Badge variant="outline" className="border-white/20 bg-white/5 text-white">
                  {connectionStatus === 'connected' ? 'Tempo real online' : 'Tempo real em espera'}
                </Badge>
                {runtimePayload ? (
                  <Badge variant="outline" className="border-white/20 bg-white/5 text-white">
                    Sessao {sessionStatus}
                  </Badge>
                ) : null}
              </div>
            </div>

            <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={playerForm.handleSubmit((values) => startMutation.mutate(values))}>
              <div className="flex-1 space-y-2">
                <Label htmlFor="player-name" className="text-white">Apelido para ranking</Label>
                <Input
                  id="player-name"
                  placeholder="Opcional"
                  className="border-white/15 bg-white/5 text-white placeholder:text-white/35"
                  onFocus={() => primeCurrentGameRuntime()}
                  {...playerForm.register('player_name')}
                />
              </div>
              <Button
                type="submit"
                disabled={startMutation.isPending}
                className="sm:min-w-[190px]"
                onMouseEnter={() => primeCurrentGameRuntime()}
                onTouchStart={() => primeCurrentGameRuntime()}
              >
                {startMutation.isPending ? (
                  <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                ) : (
                  <Play className="mr-1.5 h-4 w-4" />
                )}
                {runtimePayload ? 'Nova partida' : 'Iniciar partida'}
              </Button>
            </form>

            <div className="flex flex-wrap gap-2 text-xs text-white/65">
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">
                Fotos {runtimePayload?.assets.length ?? gameResponse.runtime.assets.length}
              </span>
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">
                Jogadas no celular {localMoves}
              </span>
              <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">
                Jogadas registradas {sessionAnalytics?.total_moves ?? 0}
              </span>
              {resumeDeadline ? (
                <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">
                  Retomada ate {new Date(resumeDeadline).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                </span>
              ) : null}
            </div>

            <PublicGameRuntimeStatus
              gameKey={currentGameKey}
              memoryProgress={memoryProgress}
              puzzleProgress={puzzleProgress}
            />
          </CardContent>
        </Card>

        <Card className="border-white/10 bg-black/30 shadow-none">
          <CardContent className="space-y-4 p-4">
            <div className="relative mx-auto aspect-[9/19] min-h-[420px] w-full max-w-[420px] overflow-hidden rounded-[32px] border border-white/10 bg-slate-950 shadow-[0_24px_80px_rgba(2,6,23,0.45)]">
              <div ref={containerRef} className="h-full w-full" />

              {runtimePayload && runtimeStatus === 'loading' ? (
                <div className="absolute inset-0 flex items-center justify-center bg-slate-950/88 text-sm text-white/75 backdrop-blur-sm">
                  <div className="flex flex-col items-center gap-2 text-center">
                    <div className="flex items-center gap-2">
                      <Loader2 className="h-4 w-4 animate-spin" />
                      Preparando o jogo...
                    </div>
                    {loadingProgress ? (
                      <span className="text-xs text-white/55">
                        {Math.round(loadingProgress.progress * 100)}%
                      </span>
                    ) : null}
                  </div>
                </div>
              ) : null}

              {runtimePayload && runtimeStatus === 'error' ? (
                <div className="absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-white/70">
                  {runtimeError?.message ?? 'Nao foi possivel abrir este jogo agora.'}
                </div>
              ) : null}
            </div>

            {!runtimePayload ? (
              <div className="flex min-h-[96px] items-center justify-center text-center text-sm text-white/45">
                Inicie uma partida para carregar este jogo.
              </div>
            ) : null}

            <div className="flex justify-center">
              <Button
                type="button"
                variant="outline"
                className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                onClick={() => setMenuOpen(true)}
                onFocus={primeGameMenu}
                onMouseEnter={primeGameMenu}
                onTouchStart={primeGameMenu}
              >
                <Menu className="mr-1.5 h-4 w-4" />
                Abrir menu do jogo
              </Button>
            </div>
          </CardContent>
        </Card>

        {latestResult ? (
          <Card className="border-emerald-500/20 bg-emerald-500/8 shadow-none">
            <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
              <div>
                <p className="text-sm font-semibold text-white">Ultima partida registrada</p>
                  <p className="text-sm text-white/70">
                  {latestResult.score} pts em {formatElapsed(latestResult.timeMs)} com {latestResult.moves} jogadas.
                  </p>
              </div>

              <Button
                type="button"
                variant="outline"
                className="border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                onClick={() => setMenuOpen(true)}
                onFocus={primeGameMenu}
                onMouseEnter={primeGameMenu}
                onTouchStart={primeGameMenu}
              >
                Ver detalhes
              </Button>
            </CardContent>
          </Card>
        ) : null}
      </div>

      <Suspense fallback={null}>
        {menuOpen ? (
          <PublicGameMenuSheet
            open={menuOpen}
            onOpenChange={setMenuOpen}
            manifest={manifest}
            gameResponse={gameResponse}
            playerIdentifier={playerIdentifier}
            connectionLabel={connectionLabel(connectionStatus)}
            runtimePayload={runtimePayload}
            latestResult={latestResult}
            sessionAnalytics={sessionAnalytics}
            ranking={ranking}
            lastPlays={lastPlays}
            gameAnalytics={gameAnalytics}
            localMoves={localMoves}
            memoryProgress={memoryProgress}
            puzzleProgress={puzzleProgress}
            onRefreshGameData={() => {
              void queryClient.invalidateQueries({ queryKey: ['public-play-game', slug, gameSlug] });
            }}
          />
        ) : null}
      </Suspense>
    </div>
  );
}

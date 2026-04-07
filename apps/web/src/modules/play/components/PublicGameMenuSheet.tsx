import { BarChart3, Clock3, Gamepad2, History, Radio, RefreshCw, Trophy, User } from 'lucide-react';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { PwaInstallBanner } from '@/modules/play/components/PwaInstallBanner';
import type { MemoryRuntimeProgress, NormalizedGameResult, PuzzleRuntimeProgress } from '@/modules/play/types';
import type {
  PlayGameAnalytics,
  PlayGameSession,
  PlayRankingEntry,
  PlaySessionAnalytics,
  PublicPlayEventManifest,
  PublicPlayGameResponse,
  StartPlaySessionResponse,
} from '@/lib/api-types';

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

function formatPlayerLabel(name: string | null | undefined, identifier: string) {
  return name || identifier.slice(0, 10);
}

function MetricCard({
  label,
  value,
}: {
  label: string;
  value: string | number;
}) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.16em] text-white/45">{label}</p>
      <p className="mt-2 text-xl font-semibold text-white">{value}</p>
    </div>
  );
}

type PublicGameMenuSheetProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  manifest: PublicPlayEventManifest;
  gameResponse: PublicPlayGameResponse;
  playerIdentifier: string;
  connectionLabel: string;
  runtimePayload: StartPlaySessionResponse | null;
  latestResult: NormalizedGameResult | null;
  sessionAnalytics: PlaySessionAnalytics | null;
  ranking: PlayRankingEntry[];
  lastPlays: PlayGameSession[];
  gameAnalytics: PlayGameAnalytics | null;
  localMoves: number;
  memoryProgress: MemoryRuntimeProgress | null;
  puzzleProgress: PuzzleRuntimeProgress | null;
  onRefreshGameData: () => void;
};

export function PublicGameMenuSheet({
  open,
  onOpenChange,
  manifest,
  gameResponse,
  playerIdentifier,
  connectionLabel,
  runtimePayload,
  latestResult,
  sessionAnalytics,
  ranking,
  lastPlays,
  gameAnalytics,
  localMoves,
  memoryProgress,
  puzzleProgress,
  onRefreshGameData,
}: PublicGameMenuSheetProps) {
  const sessionStatus = runtimePayload?.status ?? runtimePayload?.session?.status ?? 'idle';
  const sessionExpiresAt = runtimePayload?.expiresAt ?? runtimePayload?.session?.expiresAt ?? null;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="bottom"
        className="h-[86dvh] rounded-t-[32px] border-white/10 bg-slate-950 px-0 text-white"
      >
        <div className="mx-auto flex h-full max-w-3xl flex-col">
          <SheetHeader className="border-b border-white/10 px-5 pb-4 text-left">
            <SheetTitle className="text-white">Menu do jogo</SheetTitle>
            <SheetDescription className="text-white/60">
              Abra ranking, status da sessao e detalhes sem poluir a tela principal.
            </SheetDescription>
          </SheetHeader>

          <div className="flex-1 overflow-y-auto px-5 pb-8">
            <div className="mt-4 flex flex-wrap items-center gap-2">
              <Badge variant="secondary" className="bg-white/10 text-white hover:bg-white/10">
                {gameResponse.game.game_type_name || gameResponse.game.game_type_key}
              </Badge>
              <Badge variant={gameResponse.game.ranking_enabled ? 'outline' : 'secondary'} className="border-white/20 bg-white/5 text-white">
                {gameResponse.game.ranking_enabled ? 'Ranking ativo' : 'Ranking inativo'}
              </Badge>
              <Badge variant="outline" className="border-white/20 bg-white/5 text-white">
                {manifest.event.title}
              </Badge>
            </div>

            <Accordion type="single" collapsible defaultValue="session" className="mt-4 w-full">
              <AccordionItem value="session" className="border-white/10">
                <AccordionTrigger className="text-left text-white">
                  <span className="flex items-center gap-2">
                    <Gamepad2 className="h-4 w-4 text-emerald-300" />
                    Partida e sessao
                  </span>
                </AccordionTrigger>
                <AccordionContent className="space-y-4">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <MetricCard label="Jogadas no celular" value={localMoves} />
                    <MetricCard label="Jogadas registradas" value={sessionAnalytics?.total_moves ?? 0} />
                    <MetricCard label="Tempo da ultima partida" value={formatElapsed(latestResult?.timeMs ?? null)} />
                    <MetricCard label="Status" value={sessionStatus} />
                  </div>

                  {memoryProgress ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <MetricCard label="Pares encontrados" value={`${memoryProgress.matchedPairs}/${memoryProgress.totalPairs}`} />
                      <MetricCard label="Progresso" value={`${Math.round(memoryProgress.completionRatio * 100)}%`} />
                      <MetricCard label="Erros" value={memoryProgress.mistakes} />
                      <MetricCard label="Pontuacao estimada" value={memoryProgress.scorePreview} />
                    </div>
                  ) : null}

                  {puzzleProgress ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <MetricCard label="Pecas encaixadas" value={`${puzzleProgress.placed}/${puzzleProgress.total}`} />
                      <MetricCard label="Progresso" value={`${Math.round(puzzleProgress.completionRatio * 100)}%`} />
                      <MetricCard label="Combo" value={`x${puzzleProgress.combo}`} />
                      <MetricCard label="Pontuacao estimada" value={puzzleProgress.scorePreview} />
                    </div>
                  ) : null}

                  {sessionAnalytics ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <MetricCard label="Tipos de jogada" value={sessionAnalytics.unique_move_types} />
                      <MetricCard label="Janela de atividade" value={formatElapsed(sessionAnalytics.activity_window_ms)} />
                      <MetricCard label="Erros" value={sessionAnalytics.mistakes ?? 0} />
                      <MetricCard
                        label="Precisao"
                        value={sessionAnalytics.accuracy !== null && sessionAnalytics.accuracy !== undefined
                          ? `${Math.round(sessionAnalytics.accuracy * 100)}%`
                          : 'n/a'}
                      />
                    </div>
                  ) : null}

                  {latestResult ? (
                    <div className="rounded-3xl border border-emerald-500/20 bg-emerald-500/10 p-4">
                      <p className="text-sm font-semibold text-white">Ultima partida registrada</p>
                      <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <MetricCard label="Score" value={latestResult.score} />
                        <MetricCard label="Tempo" value={formatElapsed(latestResult.timeMs)} />
                        <MetricCard label="Jogadas" value={latestResult.moves} />
                        <MetricCard
                          label="Precisao"
                          value={latestResult.accuracy !== undefined ? `${Math.round(latestResult.accuracy * 100)}%` : 'n/a'}
                        />
                      </div>
                    </div>
                  ) : null}

                  {sessionExpiresAt ? (
                    <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/70">
                      Sessao retomavel ate{' '}
                      {new Date(sessionExpiresAt).toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                      .
                    </div>
                  ) : null}
                </AccordionContent>
              </AccordionItem>

              <AccordionItem value="ranking" className="border-white/10">
                <AccordionTrigger className="text-left text-white">
                  <span className="flex items-center gap-2">
                    <Trophy className="h-4 w-4 text-emerald-300" />
                    Ranking e jogo ao vivo
                  </span>
                </AccordionTrigger>
                <AccordionContent className="space-y-4">
                  {ranking.length === 0 ? (
                    <p className="text-sm text-white/60">Ainda nao existem pontuacoes registradas para este jogo.</p>
                  ) : (
                    <div className="space-y-3">
                      {ranking.slice(0, 8).map((entry) => (
                        <div key={entry.player_identifier} className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                          <div className="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-400/15 text-sm font-semibold text-emerald-200">
                            #{entry.position}
                          </div>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-white">
                              {formatPlayerLabel(entry.player_name, entry.player_identifier)}
                            </p>
                            <p className="text-xs text-white/45">{formatElapsed(entry.best_time_ms)}</p>
                          </div>
                          <p className="text-sm font-semibold text-white">{entry.best_score}</p>
                        </div>
                      ))}
                    </div>
                  )}

                  <div className="grid gap-3 sm:grid-cols-2">
                    <MetricCard label="Sessoes" value={gameAnalytics?.total_sessions ?? 0} />
                    <MetricCard label="Jogadores unicos" value={gameAnalytics?.unique_players ?? 0} />
                    <MetricCard
                      label="Conclusao"
                      value={gameAnalytics ? `${Math.round(gameAnalytics.completion_rate)}%` : '0%'}
                    />
                    <MetricCard label="Jogadas totais" value={gameAnalytics?.total_moves ?? 0} />
                  </div>

                  <Button
                    variant="outline"
                    className="w-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                    onClick={onRefreshGameData}
                  >
                    <RefreshCw className="mr-1.5 h-4 w-4" />
                    Atualizar dados
                  </Button>
                </AccordionContent>
              </AccordionItem>

              <AccordionItem value="history" className="border-white/10">
                <AccordionTrigger className="text-left text-white">
                  <span className="flex items-center gap-2">
                    <History className="h-4 w-4 text-emerald-300" />
                    Ultimas partidas
                  </span>
                </AccordionTrigger>
                <AccordionContent className="space-y-3">
                  {lastPlays.length === 0 ? (
                    <p className="text-sm text-white/60">Nenhuma partida finalizada ainda.</p>
                  ) : (
                    lastPlays.slice(0, 6).map((session) => (
                      <div key={session.uuid} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                        <div className="flex items-center justify-between gap-3">
                          <p className="truncate text-sm font-medium text-white">
                            {formatPlayerLabel(session.player_name, session.player_identifier)}
                          </p>
                          <span className="text-sm font-semibold text-white">{session.score ?? 0}</span>
                        </div>
                        <p className="mt-1 text-xs text-white/45">{formatElapsed(session.time_ms)}</p>
                      </div>
                    ))
                  )}
                </AccordionContent>
              </AccordionItem>

              <AccordionItem value="details" className="border-white/10">
                <AccordionTrigger className="text-left text-white">
                  <span className="flex items-center gap-2">
                    <BarChart3 className="h-4 w-4 text-emerald-300" />
                    Detalhes e app
                  </span>
                </AccordionTrigger>
                <AccordionContent className="space-y-4">
                  <div className="space-y-3 rounded-3xl border border-white/10 bg-white/5 p-4">
                    <div className="flex items-center gap-2 text-sm text-white/75">
                      <User className="h-4 w-4 text-white/45" />
                      Jogador
                    </div>
                    <p className="font-mono text-sm text-white/90">{playerIdentifier}</p>

                    <div className="flex items-center gap-2 text-sm text-white/75">
                      <Radio className="h-4 w-4 text-white/45" />
                      {connectionLabel}
                    </div>

                    <div className="flex items-center gap-2 text-sm text-white/75">
                      <Clock3 className="h-4 w-4 text-white/45" />
                      endereco do jogo: <span className="font-mono text-white/90">{gameResponse.game.slug}</span>
                    </div>
                  </div>

                  <PwaInstallBanner />
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

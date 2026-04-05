import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Activity, Loader2, RefreshCw, Search, TimerReset, Trophy, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PlayEventGame } from '@/lib/api-types';
import { fetchEventPlayAnalytics } from '@/modules/play/api/playApi';
import { StatsCard } from '@/shared/components/StatsCard';

interface PlayAnalyticsPanelProps {
  eventId: string;
  games: PlayEventGame[];
}

function formatElapsed(value: number | null | undefined) {
  if (!value) return '0s';

  const seconds = Math.round(value / 1000);
  const minutes = Math.floor(seconds / 60);
  const remaining = seconds % 60;

  if (minutes === 0) return `${remaining}s`;

  return `${minutes}m ${remaining}s`;
}

function formatDateLabel(value: string) {
  if (!value || value === 'unknown') return 'sem data';

  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
  });
}

function statusLabel(status: string) {
  switch (status) {
    case 'finished':
      return 'Concluida';
    case 'abandoned':
      return 'Abandonada';
    case 'started':
      return 'Em andamento';
    default:
      return status;
  }
}

export function PlayAnalyticsPanel({ eventId, games }: PlayAnalyticsPanelProps) {
  const [selectedGameId, setSelectedGameId] = useState<string>('all');
  const [status, setStatus] = useState<string>('all');
  const [search, setSearch] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const analyticsQuery = useQuery({
    queryKey: ['event-play-analytics', eventId, selectedGameId, status, search, dateFrom, dateTo],
    queryFn: () => fetchEventPlayAnalytics(eventId, {
      play_game_id: selectedGameId === 'all' ? undefined : Number(selectedGameId),
      status: status === 'all' ? undefined : status,
      search: search || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      session_limit: 20,
    }),
  });

  const analytics = analyticsQuery.data;

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold tracking-tight">Analytics do Play</h2>
        <p className="text-sm text-muted-foreground">
          Visao administrativa das sessoes, ranking e comportamento por jogo com filtros por periodo.
        </p>
      </div>

      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="pb-3">
          <CardTitle className="text-base">Filtros</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
          <div className="space-y-2">
            <Label>Jogo</Label>
            <Select value={selectedGameId} onValueChange={setSelectedGameId}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os jogos</SelectItem>
                {games.map((game) => (
                  <SelectItem key={game.id} value={String(game.id)}>
                    {game.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Status da sessao</Label>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="started">Em andamento</SelectItem>
                <SelectItem value="finished">Concluidas</SelectItem>
                <SelectItem value="abandoned">Abandonadas</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Data inicial</Label>
            <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
          </div>

          <div className="space-y-2">
            <Label>Data final</Label>
            <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
          </div>

          <div className="space-y-2">
            <Label>Buscar jogador</Label>
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nome ou token" />
            </div>
          </div>
        </CardContent>
      </Card>

      {analyticsQuery.isLoading ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando analytics do Play...
        </div>
      ) : analyticsQuery.isError || !analytics ? (
        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardContent className="px-6 py-10 text-center text-sm text-destructive">
            Nao foi possivel carregar os analytics do Play.
          </CardContent>
        </Card>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <StatsCard title="Sessoes" value={analytics.summary.total_sessions} icon={Activity} />
            <StatsCard title="Jogadores unicos" value={analytics.summary.unique_players} icon={Users} />
            <StatsCard title="Moves totais" value={analytics.summary.total_moves} icon={TimerReset} />
            <StatsCard title="Melhor score" value={analytics.summary.best_score ?? 0} icon={Trophy} />
          </div>

          <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <Card className="border-white/70 bg-white/90 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Timeline de sessoes</CardTitle>
              </CardHeader>
              <CardContent>
                {analytics.timeline.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-muted-foreground">
                    Nenhum dado no periodo selecionado.
                  </div>
                ) : (
                  <ChartContainer
                    config={{
                      sessions: { label: 'Sessoes', color: '#22c55e' },
                      total_moves: { label: 'Moves', color: '#0f172a' },
                    }}
                    className="h-[280px] w-full"
                  >
                    <LineChart data={analytics.timeline}>
                      <CartesianGrid vertical={false} />
                      <XAxis dataKey="date" tickFormatter={formatDateLabel} tickLine={false} axisLine={false} />
                      <YAxis tickLine={false} axisLine={false} allowDecimals={false} />
                      <ChartTooltip content={<ChartTooltipContent />} />
                      <Line type="monotone" dataKey="sessions" stroke="var(--color-sessions)" strokeWidth={2.5} dot={false} />
                      <Line type="monotone" dataKey="total_moves" stroke="var(--color-total_moves)" strokeWidth={2} dot={false} />
                    </LineChart>
                  </ChartContainer>
                )}
              </CardContent>
            </Card>

            <Card className="border-white/70 bg-white/90 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Scores por dia</CardTitle>
              </CardHeader>
              <CardContent>
                {analytics.timeline.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-muted-foreground">
                    Sem score agregado no periodo.
                  </div>
                ) : (
                  <ChartContainer
                    config={{
                      average_score: { label: 'Score medio', color: '#3b82f6' },
                      best_score: { label: 'Melhor score', color: '#f97316' },
                    }}
                    className="h-[280px] w-full"
                  >
                    <BarChart data={analytics.timeline}>
                      <CartesianGrid vertical={false} />
                      <XAxis dataKey="date" tickFormatter={formatDateLabel} tickLine={false} axisLine={false} />
                      <YAxis tickLine={false} axisLine={false} allowDecimals={false} />
                      <ChartTooltip content={<ChartTooltipContent />} />
                      <Bar dataKey="average_score" fill="var(--color-average_score)" radius={[8, 8, 0, 0]} />
                      <Bar dataKey="best_score" fill="var(--color-best_score)" radius={[8, 8, 0, 0]} />
                    </BarChart>
                  </ChartContainer>
                )}
              </CardContent>
            </Card>
          </div>

          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Performance por jogo</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 lg:grid-cols-2">
              {analytics.games.map((item) => (
                <div key={item.game.id} className="rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-semibold">{item.game.title}</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {item.game.game_type_name || item.game.game_type_key} · slug `{item.game.slug}`
                      </p>
                    </div>
                    <Badge variant={item.game.is_active ? 'outline' : 'secondary'}>
                      {item.game.is_active ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div className="rounded-2xl bg-white p-3">
                      <p className="text-xs text-muted-foreground">Sessoes</p>
                      <p className="mt-1 font-semibold">{item.analytics.total_sessions}</p>
                    </div>
                    <div className="rounded-2xl bg-white p-3">
                      <p className="text-xs text-muted-foreground">Conclusao</p>
                      <p className="mt-1 font-semibold">{Math.round(item.analytics.completion_rate)}%</p>
                    </div>
                    <div className="rounded-2xl bg-white p-3">
                      <p className="text-xs text-muted-foreground">Moves</p>
                      <p className="mt-1 font-semibold">{item.analytics.total_moves}</p>
                    </div>
                    <div className="rounded-2xl bg-white p-3">
                      <p className="text-xs text-muted-foreground">Score medio</p>
                      <p className="mt-1 font-semibold">{item.analytics.average_score ?? 0}</p>
                    </div>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Sessoes recentes</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow className="border-border/50">
                    <TableHead>Jogo</TableHead>
                    <TableHead>Jogador</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Score</TableHead>
                    <TableHead>Moves</TableHead>
                    <TableHead>Tempo</TableHead>
                    <TableHead>Fim</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {analytics.recent_sessions.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-sm text-muted-foreground">
                        Nenhuma sessao encontrada com os filtros atuais.
                      </TableCell>
                    </TableRow>
                  ) : (
                    analytics.recent_sessions.map((session) => (
                      <TableRow key={session.uuid} className="border-border/30">
                        <TableCell>
                          <div>
                            <p className="font-medium">{session.game?.title || 'Jogo removido'}</p>
                            <p className="text-xs text-muted-foreground">{session.game?.game_type_name || session.game?.game_type_key}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div>
                            <p className="font-medium">{session.player_name || 'Anonimo'}</p>
                            <p className="text-xs text-muted-foreground">{session.player_identifier.slice(0, 14)}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={session.status === 'finished' ? 'outline' : 'secondary'}>
                            {statusLabel(session.status)}
                          </Badge>
                        </TableCell>
                        <TableCell>{session.score ?? 0}</TableCell>
                        <TableCell>
                          <div>
                            <p>{session.move_count ?? 0}</p>
                            <p className="text-xs text-muted-foreground">reportado {session.moves_reported ?? 0}</p>
                          </div>
                        </TableCell>
                        <TableCell>{formatElapsed(session.time_ms)}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {session.finished_at ? new Date(session.finished_at).toLocaleString('pt-BR') : 'em aberto'}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button variant="outline" onClick={() => analyticsQuery.refetch()} disabled={analyticsQuery.isFetching}>
              {analyticsQuery.isFetching ? (
                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
              ) : (
                <RefreshCw className="mr-1.5 h-4 w-4" />
              )}
              Atualizar analytics
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

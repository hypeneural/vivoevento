import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { ExternalLink, Loader2, Monitor, Settings, Tv2 } from 'lucide-react';
import { Link } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { queryKeys } from '@/lib/query-client';
import { PageHeader } from '@/shared/components/PageHeader';

import { listEvents } from '@/modules/events/api';

const WALL_STATUS_ORDER: Record<string, number> = {
  live: 0,
  paused: 1,
  draft: 2,
  stopped: 3,
  expired: 4,
};

function formatDateRange(startsAt?: string | null, endsAt?: string | null) {
  if (!startsAt && !endsAt) return null;

  const formatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });

  const startLabel = startsAt ? formatter.format(new Date(startsAt)) : null;
  const endLabel = endsAt ? formatter.format(new Date(endsAt)) : null;

  return [startLabel, endLabel].filter(Boolean).join(' - ');
}

function wallStatusLabel(status?: string | null) {
  switch (status) {
    case 'live':
      return 'Ao vivo';
    case 'paused':
      return 'Pausado';
    case 'stopped':
      return 'Parado';
    case 'expired':
      return 'Expirado';
    default:
      return 'Nao configurado';
  }
}

function wallStatusClasses(status?: string | null) {
  switch (status) {
    case 'live':
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600';
    case 'paused':
      return 'border-amber-500/30 bg-amber-500/10 text-amber-700';
    case 'stopped':
      return 'border-rose-500/30 bg-rose-500/10 text-rose-700';
    case 'expired':
      return 'border-slate-500/30 bg-slate-500/10 text-slate-600';
    default:
      return 'border-border bg-muted/60 text-muted-foreground';
  }
}

export default function WallHubPage() {
  const eventsQuery = useQuery({
    queryKey: queryKeys.events.list({ module: 'wall', status: 'active', per_page: 50 }),
    queryFn: () => listEvents({ module: 'wall', status: 'active', per_page: 50 }),
  });

  const events = useMemo(() => {
    const items = (eventsQuery.data?.data ?? []).filter((event) => event.wall?.is_enabled);

    return [...items].sort((left, right) => {
      const leftRank = WALL_STATUS_ORDER[left.wall?.status ?? 'draft'] ?? 99;
      const rightRank = WALL_STATUS_ORDER[right.wall?.status ?? 'draft'] ?? 99;

      if (leftRank !== rightRank) return leftRank - rightRank;

      return left.title.localeCompare(right.title, 'pt-BR');
    });
  }, [eventsQuery.data?.data]);

  if (eventsQuery.isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  if (events.length === 0) {
    return (
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
        <PageHeader
          title="Telao"
          description="Nao existe nenhum telao ativo em eventos em andamento para acompanhar agora."
        />
        <div className="rounded-3xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center text-sm text-muted-foreground">
          Ative o telao no evento para ele aparecer nesta visao de acompanhamento.
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Telao"
        description="Acompanhe os eventos com telao ativo e abra a configuracao ou a exibicao com um clique."
      />

      <div className="grid gap-4 xl:grid-cols-2">
        {events.map((event) => {
          const schedule = formatDateRange(event.starts_at, event.ends_at);

          return (
            <section key={event.id} className="glass rounded-3xl border border-border/60 p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{event.status}</Badge>
                    <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium ${wallStatusClasses(event.wall?.status)}`}>
                      {wallStatusLabel(event.wall?.status)}
                    </span>
                  </div>
                  <div>
                    <h2 className="text-lg font-semibold">{event.title}</h2>
                    <p className="text-sm text-muted-foreground">
                      {[schedule, event.location_name].filter(Boolean).join(' • ') || 'Sem agenda definida'}
                    </p>
                  </div>
                </div>

                <div className="rounded-2xl border border-border/60 bg-background/70 px-4 py-3 text-right">
                  <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Codigo do telao</p>
                  <p className="mt-1 font-mono text-sm font-semibold tracking-[0.18em]">
                    {event.wall?.wall_code ?? 'PENDENTE'}
                  </p>
                </div>
              </div>

              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                  <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Organizacao</p>
                  <p className="mt-2 text-sm font-medium">{event.organization_name ?? 'Nao informada'}</p>
                </div>
                <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                  <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Cliente</p>
                  <p className="mt-2 text-sm font-medium">{event.client_name ?? 'Nao informado'}</p>
                </div>
                <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                  <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Publicacao</p>
                  <p className="mt-2 text-sm font-medium">{event.wall?.is_enabled ? 'Ativa' : 'Nao iniciada'}</p>
                </div>
              </div>

              <div className="mt-5 flex flex-wrap gap-2">
                <Button asChild>
                  <Link to={`/events/${event.id}/wall`}>
                    <Settings className="mr-1.5 h-4 w-4" />
                    Configurar telao
                  </Link>
                </Button>
                {event.wall?.public_url ? (
                  <Button asChild variant="outline">
                    <a href={event.wall.public_url} target="_blank" rel="noreferrer">
                      <ExternalLink className="mr-1.5 h-4 w-4" />
                      Abrir telao
                    </a>
                  </Button>
                ) : null}
                <Button asChild variant="ghost">
                  <Link to={`/events/${event.id}`}>
                    <Tv2 className="mr-1.5 h-4 w-4" />
                    Ver evento
                  </Link>
                </Button>
              </div>

              <div className="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
                <Monitor className="h-3.5 w-3.5" />
                {event.wall?.status === 'live'
                  ? 'Telao operando em tempo real.'
                  : 'Entre na configuracao para iniciar, pausar ou ajustar a exibicao.'}
              </div>
            </section>
          );
        })}
      </div>
    </motion.div>
  );
}

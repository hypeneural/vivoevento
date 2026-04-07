import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { CalendarDays, ExternalLink, Gamepad2, Loader2, PlaySquare, Sparkles } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { listEvents } from '@/modules/events/api';
import { EVENT_MODULE_LABELS, EVENT_STATUS_LABELS } from '@/modules/events/types';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';

function formatDate(value: string | null) {
  if (!value) {
    return 'Sem data definida';
  }

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export default function PlayHubPage() {
  const eventsQuery = useQuery({
    queryKey: ['play-events'],
    queryFn: () => listEvents({ module: 'play', per_page: 24 }),
  });
  const events = Array.isArray(eventsQuery.data?.data) ? eventsQuery.data.data : [];
  const hasMalformedEventsResponse = Boolean(eventsQuery.data && !Array.isArray(eventsQuery.data.data));

  const stats = useMemo(() => {
    return {
      total: events.length,
      active: events.filter((event) => event.status === 'active').length,
      withMedia: events.filter((event) => (event.media_count ?? 0) > 0).length,
      publicReady: events.filter((event) => !!event.slug).length,
    };
  }, [events]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Jogos"
        description="Selecione um evento para configurar os jogos, escolher as fotos e acompanhar o ranking."
      />

      <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatsCard title="Eventos com jogos" value={stats.total} icon={Gamepad2} />
        <StatsCard title="Eventos ativos" value={stats.active} icon={Sparkles} />
        <StatsCard title="Com fotos prontas" value={stats.withMedia} icon={PlaySquare} />
        <StatsCard title="Pagina publica pronta" value={stats.publicReady} icon={ExternalLink} />
      </div>

      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Eventos</CardTitle>
        </CardHeader>
        <CardContent>
          {eventsQuery.isLoading ? (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              Carregando eventos com jogos...
            </div>
          ) : eventsQuery.isError || hasMalformedEventsResponse ? (
            <p className="text-sm text-destructive">Nao foi possivel carregar os eventos com jogos.</p>
          ) : events.length === 0 ? (
            <div className="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center">
              <p className="text-sm font-medium">Nenhum evento com jogos ativos foi encontrado.</p>
              <p className="mt-2 text-sm text-muted-foreground">
                Ative o modulo Jogos no evento e volte para configurar esta area.
              </p>
            </div>
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              {events.map((event) => (
                <Card key={event.id} className="overflow-hidden border-slate-200 shadow-none">
                  <div className="relative h-36 bg-slate-950">
                    {event.cover_image_url ? (
                      <img
                        src={event.cover_image_url}
                        alt={event.title}
                        className="absolute inset-0 h-full w-full object-cover opacity-35"
                      />
                    ) : null}
                    <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900/90 to-slate-800/80" />
                    <div className="relative flex h-full flex-col justify-between px-5 py-4 text-white">
                      <div className="flex items-center justify-between gap-3">
                        <Badge variant="secondary" className="bg-white/10 text-white hover:bg-white/10">
                          {EVENT_STATUS_LABELS[event.status] ?? event.status}
                        </Badge>
                        <div className="flex items-center gap-1 text-xs text-white/70">
                          <CalendarDays className="h-3.5 w-3.5" />
                          {formatDate(event.starts_at)}
                        </div>
                      </div>

                      <div className="space-y-1">
                        <p className="text-lg font-semibold">{event.title}</p>
                        <p className="text-xs text-white/70">
                          {event.location_name || 'Local a definir'} - endereco do evento `{event.slug}`
                        </p>
                      </div>
                    </div>
                  </div>

                  <CardContent className="space-y-4 p-5">
                    <div className="grid grid-cols-3 gap-3 text-sm">
                      <div className="rounded-2xl bg-slate-50 p-3">
                        <p className="text-xs text-muted-foreground">Fotos</p>
                        <p className="mt-1 font-semibold">{event.media_count ?? 0}</p>
                      </div>
                      <div className="rounded-2xl bg-slate-50 p-3">
                        <p className="text-xs text-muted-foreground">Modulos</p>
                        <p className="mt-1 font-semibold">{event.enabled_modules?.map((module) => EVENT_MODULE_LABELS[module]).join(', ') || 'Jogos'}</p>
                      </div>
                      <div className="rounded-2xl bg-slate-50 p-3">
                        <p className="text-xs text-muted-foreground">Pagina publica</p>
                        <p className="mt-1 font-semibold">{event.slug ? 'Pronto' : 'Pendente'}</p>
                      </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      <Button asChild>
                        <Link to={`/events/${event.id}/play`}>
                          <Gamepad2 className="mr-1.5 h-4 w-4" />
                          Configurar jogos
                        </Link>
                      </Button>

                      <Button asChild variant="outline">
                        <Link to={`/events/${event.id}`}>
                          Abrir evento
                        </Link>
                      </Button>

                      <Button asChild variant="outline">
                        <Link to={`/e/${event.slug}/play`} target="_blank" rel="noreferrer">
                          <ExternalLink className="mr-1.5 h-4 w-4" />
                          Pagina de jogos
                        </Link>
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  CalendarDays,
  ChevronDown,
  Edit3,
  Eye,
  Loader2,
  Plus,
  Search,
  SlidersHorizontal,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';

import { EventActionsMenu } from './components/EventActionsMenu';
import { EventsPagination } from './components/EventsPagination';
import { eventsListQueryOptions, eventsService } from './services/events.service';
import {
  EVENT_MODULE_LABELS,
  EVENT_COMMERCIAL_MODE_LABELS,
  EVENT_COMMERCIAL_SCOPE_LABELS,
  EVENT_SORT_OPTIONS,
  EVENT_STATUS_OPTIONS,
  EVENT_TYPE_LABELS,
  EVENT_TYPE_OPTIONS,
  type EventListItem,
  type EventModuleKey,
  type EventSortBy,
  type SortDirection,
} from './types';

function formatEventDate(value?: string | null) {
  if (!value) return 'Sem data definida';

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function formatFilterDate(value?: string) {
  if (!value) return null;

  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function buildCoverPreview(event: EventListItem) {
  return event.cover_image_url || null;
}

export default function EventsListPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [moduleFilter, setModuleFilter] = useState<string>('all');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sortBy, setSortBy] = useState<EventSortBy>('starts_at');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
  const [isFiltersOpen, setIsFiltersOpen] = useState(false);

  const deferredSearch = useDeferredValue(search);

  useEffect(() => {
    setPage(1);
  }, [deferredSearch, statusFilter, typeFilter, moduleFilter, dateFrom, dateTo, sortBy, sortDirection]);

  const filters = useMemo(() => ({
    search: deferredSearch || undefined,
    status: statusFilter === 'all' ? undefined : statusFilter,
    event_type: typeFilter === 'all' ? undefined : typeFilter,
    module: moduleFilter === 'all' ? undefined : moduleFilter as EventModuleKey,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    sort_by: sortBy,
    sort_direction: sortDirection,
    page,
    per_page: 12,
  }), [dateFrom, dateTo, deferredSearch, moduleFilter, page, sortBy, sortDirection, statusFilter, typeFilter]);

  const eventsQuery = useQuery({
    ...eventsListQueryOptions(filters),
    placeholderData: keepPreviousData,
  });

  const statusMutation = useMutation({
    mutationFn: async ({ action, event }: { action: 'publish' | 'archive'; event: EventListItem }) => {
      if (action === 'publish') {
        await eventsService.publish(event.id);
      } else {
        await eventsService.archive(event.id);
      }

      return { action, event };
    },
    onSuccess: async ({ action, event }) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.events.all() });

      toast({
        title: action === 'publish' ? 'Evento publicado' : 'Evento arquivado',
        description: `"${event.title}" foi atualizado com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar evento',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const events = eventsQuery.data?.data ?? [];
  const pagination = eventsQuery.data?.meta;
  const total = pagination?.total ?? events.length;
  const isBusyEventId = statusMutation.isPending ? statusMutation.variables?.event.id : null;
  const sortLabel = EVENT_SORT_OPTIONS.find((option) => option.value === sortBy)?.label ?? 'Data do evento';

  const activeFiltersCount = useMemo(() => {
    let count = 0;

    if (search.trim()) count += 1;
    if (statusFilter !== 'all') count += 1;
    if (typeFilter !== 'all') count += 1;
    if (moduleFilter !== 'all') count += 1;
    if (dateFrom || dateTo) count += 1;
    if (sortBy !== 'starts_at') count += 1;
    if (sortDirection !== 'desc') count += 1;

    return count;
  }, [dateFrom, dateTo, moduleFilter, search, sortBy, sortDirection, statusFilter, typeFilter]);

  const filtersSummary = useMemo(() => {
    const segments: string[] = [];

    if (search.trim()) {
      segments.push(`Busca: "${search.trim()}"`);
    }

    if (statusFilter !== 'all') {
      segments.push(
        `Status: ${EVENT_STATUS_OPTIONS.find((option) => option.value === statusFilter)?.label ?? statusFilter}`,
      );
    }

    if (typeFilter !== 'all') {
      segments.push(
        `Tipo: ${EVENT_TYPE_OPTIONS.find((option) => option.value === typeFilter)?.label ?? typeFilter}`,
      );
    }

    if (moduleFilter !== 'all') {
      segments.push(`Modulo: ${EVENT_MODULE_LABELS[moduleFilter as EventModuleKey] ?? moduleFilter}`);
    }

    if (dateFrom || dateTo) {
      const start = formatFilterDate(dateFrom) ?? 'inicio livre';
      const end = formatFilterDate(dateTo) ?? 'fim livre';
      segments.push(`Periodo: ${start} ate ${end}`);
    }

    segments.push(
      `Ordenacao: ${sortLabel}, ${sortDirection === 'desc' ? 'mais recentes primeiro' : 'mais antigos primeiro'}`,
    );

    return segments.join(' | ');
  }, [dateFrom, dateTo, moduleFilter, search, sortBy, sortDirection, sortLabel, statusFilter, typeFilter]);

  const copyToClipboard = async (label: string, url?: string | null) => {
    if (!url) {
      toast({
        title: 'Link indisponivel',
        description: `${label} ainda nao esta disponivel para este evento.`,
        variant: 'destructive',
      });
      return;
    }

    await navigator.clipboard.writeText(url);

    toast({
      title: 'Link copiado',
      description: `${label} copiado para a area de transferencia.`,
    });
  };

  const resetFilters = () => {
    setSearch('');
    setStatusFilter('all');
    setTypeFilter('all');
    setModuleFilter('all');
    setDateFrom('');
    setDateTo('');
    setSortBy('starts_at');
    setSortDirection('desc');
    setPage(1);
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-5">
      <PageHeader
        title="Eventos"
        description={eventsQuery.isLoading ? 'Carregando eventos...' : `${total} eventos encontrados`}
        actions={(
          <Button asChild className="gradient-primary border-0">
            <Link to="/events/create">
              <Plus className="h-4 w-4" />
              Novo evento
            </Link>
          </Button>
        )}
      />

      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div className="space-y-1">
            <div className="flex flex-wrap items-center gap-2 text-sm font-semibold">
              <SlidersHorizontal className="h-4 w-4 text-primary" />
              Filtros e ordenacao
              {activeFiltersCount > 0 ? (
                <Badge variant="secondary" className="rounded-full px-2.5 py-0.5 text-[11px]">
                  {activeFiltersCount} ativos
                </Badge>
              ) : null}
            </div>
            <p className="text-sm text-muted-foreground">
              {activeFiltersCount > 0
                ? filtersSummary
                : `Nenhum filtro ativo. Ordenacao padrao: ${sortLabel}, ${sortDirection === 'desc' ? 'mais recentes primeiro' : 'mais antigos primeiro'}.`}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            {activeFiltersCount > 0 ? (
              <Button variant="ghost" size="sm" onClick={resetFilters}>
                Limpar
              </Button>
            ) : null}

            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsFiltersOpen((open) => !open)}
              aria-expanded={isFiltersOpen}
            >
              {isFiltersOpen ? 'Ocultar filtros' : 'Mostrar filtros'}
              <ChevronDown className={`h-4 w-4 transition-transform ${isFiltersOpen ? 'rotate-180' : ''}`} />
            </Button>
          </div>
        </div>

        {isFiltersOpen ? (
          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
            <div className="relative xl:col-span-4">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar por nome, cliente ou organizacao"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="pl-9"
              />
            </div>

            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os status</SelectItem>
                {EVENT_STATUS_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Tipo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os tipos</SelectItem>
                {EVENT_TYPE_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={moduleFilter} onValueChange={setModuleFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Modulo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os modulos</SelectItem>
                {Object.entries(EVENT_MODULE_LABELS).map(([value, label]) => (
                  <SelectItem key={value} value={value}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={sortBy} onValueChange={(value) => setSortBy(value as EventSortBy)}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Ordenar por" />
              </SelectTrigger>
              <SelectContent>
                {EVENT_SORT_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Input
              type="date"
              value={dateFrom}
              onChange={(event) => setDateFrom(event.target.value)}
              className="xl:col-span-2"
            />

            <Input
              type="date"
              value={dateTo}
              onChange={(event) => setDateTo(event.target.value)}
              className="xl:col-span-2"
            />

            <Select value={sortDirection} onValueChange={(value) => setSortDirection(value as SortDirection)}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Direcao" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="desc">Mais recentes primeiro</SelectItem>
                <SelectItem value="asc">Mais antigos primeiro</SelectItem>
              </SelectContent>
            </Select>

            <div className="xl:col-span-2">
              <Button variant="outline" className="w-full" onClick={resetFilters}>
                Limpar filtros
              </Button>
            </div>
          </div>
        ) : null}
      </section>

      {eventsQuery.isLoading && events.length === 0 ? (
        <div className="glass rounded-3xl border border-border/60 px-4 py-16 text-center text-sm text-muted-foreground">
          <div className="flex items-center justify-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando eventos...
          </div>
        </div>
      ) : eventsQuery.isError ? (
        <div className="glass rounded-3xl border border-destructive/30 px-4 py-16 text-center text-sm text-destructive">
          Nao foi possivel carregar os eventos agora.
        </div>
      ) : events.length === 0 ? (
        <div className="glass rounded-3xl border border-border/60">
          <EmptyState
            icon={CalendarDays}
            title="Nenhum evento encontrado"
            description="Ajuste os filtros, revise o periodo informado ou crie um novo evento para continuar."
            action={(
              <Button asChild className="gradient-primary border-0">
                <Link to="/events/create">Criar evento</Link>
              </Button>
            )}
          />
        </div>
      ) : (
        <>
          <div className="grid gap-3 lg:hidden">
            {events.map((event) => {
              const coverPreview = buildCoverPreview(event);

              return (
                <article key={event.id} className="glass overflow-hidden rounded-3xl border border-border/60">
                  {coverPreview ? (
                    <img src={coverPreview} alt={event.title} className="h-40 w-full object-cover" />
                  ) : (
                    <div className="h-40 w-full bg-gradient-to-br from-slate-900 via-slate-700 to-slate-500" />
                  )}

                  <div className="space-y-4 p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <Link to={`/events/${event.id}`} className="line-clamp-2 text-base font-semibold">
                          {event.title}
                        </Link>
                        <p className="mt-1 text-xs text-muted-foreground">
                          {event.client_name || event.organization_name || 'Sem cliente vinculado'}
                        </p>
                      </div>

                      <EventActionsMenu
                        event={event}
                        isBusy={isBusyEventId === event.id}
                        onCopyLink={copyToClipboard}
                        onRequestPublish={(selectedEvent) => statusMutation.mutate({ action: 'publish', event: selectedEvent })}
                        onRequestArchive={(selectedEvent) => statusMutation.mutate({ action: 'archive', event: selectedEvent })}
                      />
                    </div>

                    <div className="flex flex-wrap gap-2">
                      <EventStatusBadge status={event.status} />
                      <Badge variant="secondary">{EVENT_TYPE_LABELS[event.event_type]}</Badge>
                      <Badge variant="outline">{EVENT_COMMERCIAL_MODE_LABELS[event.commercial_mode || 'none']}</Badge>
                      <Badge variant="outline">{EVENT_COMMERCIAL_SCOPE_LABELS[event.commercial_mode || 'none']}</Badge>
                      {(event.enabled_modules ?? []).slice(0, 3).map((moduleKey) => (
                        <Badge key={moduleKey} variant="outline">
                          {EVENT_MODULE_LABELS[moduleKey]}
                        </Badge>
                      ))}
                    </div>

                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Data</p>
                        <p className="mt-1 font-medium">{formatEventDate(event.starts_at)}</p>
                      </div>
                      <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Fotos</p>
                        <p className="mt-1 font-medium">{event.media_count ?? 0}</p>
                      </div>
                    </div>

                    <div className="flex gap-2">
                      <Button asChild size="sm" variant="outline" className="flex-1">
                        <Link to={`/events/${event.id}`}>
                          <Eye className="h-4 w-4" />
                          Detalhes
                        </Link>
                      </Button>

                      <Button asChild size="sm" className="flex-1">
                        <Link to={`/events/${event.id}/edit`}>
                          <Edit3 className="h-4 w-4" />
                          Editar
                        </Link>
                      </Button>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>

          <div className="glass hidden overflow-hidden rounded-3xl border border-border/60 lg:block">
            <Table>
              <TableHeader>
                <TableRow className="border-border/50">
                  <TableHead>Evento</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Data</TableHead>
                  <TableHead>Cliente</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Fotos</TableHead>
                  <TableHead>Modulos</TableHead>
                  <TableHead className="w-[180px] text-right">Acoes</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {events.map((event) => (
                  <TableRow key={event.id} className="border-border/30 hover:bg-muted/20">
                    <TableCell>
                      <div className="flex items-center gap-3">
                        {buildCoverPreview(event) ? (
                          <img
                            src={buildCoverPreview(event) ?? undefined}
                            alt={event.title}
                            className="h-11 w-16 rounded-xl object-cover"
                          />
                        ) : (
                          <div className="h-11 w-16 rounded-xl bg-slate-200" />
                        )}

                        <div className="min-w-0">
                          <Link to={`/events/${event.id}`} className="block truncate font-medium">
                            {event.title}
                          </Link>
                          <p className="truncate text-xs text-muted-foreground">
                            {event.organization_name || 'Organizacao nao informada'}
                          </p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {EVENT_TYPE_LABELS[event.event_type]}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {formatEventDate(event.starts_at)}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {event.client_name || 'Sem cliente'}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        <EventStatusBadge status={event.status} />
                        <Badge variant="outline" className="text-[10px]">
                          {EVENT_COMMERCIAL_MODE_LABELS[event.commercial_mode || 'none']}
                        </Badge>
                        <Badge variant="outline" className="text-[10px]">
                          {EVENT_COMMERCIAL_SCOPE_LABELS[event.commercial_mode || 'none']}
                        </Badge>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm">{event.media_count ?? 0}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {(event.enabled_modules ?? []).map((moduleKey) => (
                          <Badge key={moduleKey} variant="outline" className="text-[10px]">
                            {EVENT_MODULE_LABELS[moduleKey]}
                          </Badge>
                        ))}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center justify-end gap-1">
                        <Button asChild variant="ghost" size="sm">
                          <Link to={`/events/${event.id}`}>
                            <Eye className="h-4 w-4" />
                            Ver
                          </Link>
                        </Button>

                        <Button asChild variant="ghost" size="sm">
                          <Link to={`/events/${event.id}/edit`}>
                            <Edit3 className="h-4 w-4" />
                            Editar
                          </Link>
                        </Button>

                        <EventActionsMenu
                          event={event}
                          isBusy={isBusyEventId === event.id}
                          onCopyLink={copyToClipboard}
                          onRequestPublish={(selectedEvent) => statusMutation.mutate({ action: 'publish', event: selectedEvent })}
                          onRequestArchive={(selectedEvent) => statusMutation.mutate({ action: 'archive', event: selectedEvent })}
                        />
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            {pagination ? (
              <EventsPagination
                currentPage={pagination.page}
                lastPage={pagination.last_page}
                perPage={pagination.per_page}
                total={pagination.total}
                isFetching={eventsQuery.isFetching}
                onPageChange={setPage}
              />
            ) : null}
          </div>

          {pagination ? (
            <div className="glass rounded-3xl border border-border/60 lg:hidden">
              <EventsPagination
                currentPage={pagination.page}
                lastPage={pagination.last_page}
                perPage={pagination.per_page}
                total={pagination.total}
                isFetching={eventsQuery.isFetching}
                onPageChange={setPage}
              />
            </div>
          ) : null}
        </>
      )}
    </motion.div>
  );
}

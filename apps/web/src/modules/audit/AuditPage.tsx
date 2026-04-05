import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Activity,
  Clock3,
  Filter,
  Loader2,
  RefreshCw,
  Search,
  Shield,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';

import { AuditPagination } from './components/AuditPagination';
import { auditService } from './services/audit.service';
import type { AuditEntry } from './types';

const CATEGORY_LABELS: Record<string, string> = {
  security: 'Seguranca',
  billing: 'Billing',
  event: 'Evento',
  organization: 'Organizacao',
  customer: 'Cliente',
  account: 'Conta',
  system: 'Sistema',
};

const CATEGORY_STYLES: Record<string, string> = {
  security: 'border-red-500/30 bg-red-500/10 text-red-700',
  billing: 'border-amber-500/30 bg-amber-500/10 text-amber-700',
  event: 'border-sky-500/30 bg-sky-500/10 text-sky-700',
  organization: 'border-violet-500/30 bg-violet-500/10 text-violet-700',
  customer: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700',
  account: 'border-slate-500/30 bg-slate-500/10 text-slate-700',
  system: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-700',
};

const SEVERITY_LABELS: Record<string, string> = {
  high: 'Alta',
  medium: 'Media',
  low: 'Baixa',
};

const SEVERITY_STYLES: Record<string, string> = {
  high: 'border-red-500/30 bg-red-500/10 text-red-700',
  medium: 'border-amber-500/30 bg-amber-500/10 text-amber-700',
  low: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700',
};

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatDayLabel(value: string) {
  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function formatDayKey(value: string) {
  const date = new Date(value);
  const year = String(date.getFullYear());
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function JsonPanel({ title, value }: { title: string; value: Record<string, unknown> | null }) {
  if (!value || Object.keys(value).length === 0) {
    return null;
  }

  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
      <h3 className="text-sm font-semibold">{title}</h3>
      <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
        {JSON.stringify(value, null, 2)}
      </pre>
    </div>
  );
}

function AuditTimelineSkeleton() {
  return (
    <div className="space-y-4">
      {Array.from({ length: 5 }, (_, index) => (
        <div key={index} className="rounded-2xl border border-border/60 p-4">
          <Skeleton className="h-4 w-32" />
          <Skeleton className="mt-3 h-5 w-3/4" />
          <Skeleton className="mt-3 h-4 w-full" />
          <Skeleton className="mt-2 h-4 w-2/3" />
        </div>
      ))}
    </div>
  );
}

export default function AuditPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [actorId, setActorId] = useState('all');
  const [subjectType, setSubjectType] = useState('all');
  const [activityEvent, setActivityEvent] = useState('all');
  const [batchUuid, setBatchUuid] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [perPage, setPerPage] = useState('30');
  const [hasChanges, setHasChanges] = useState(false);
  const [selectedEntryId, setSelectedEntryId] = useState<number | null>(null);

  const deferredSearch = useDeferredValue(search);

  useEffect(() => {
    setPage(1);
  }, [deferredSearch, actorId, subjectType, activityEvent, batchUuid, dateFrom, dateTo, perPage, hasChanges]);

  const filters = useMemo(() => ({
    search: deferredSearch || undefined,
    actor_id: actorId === 'all' ? undefined : Number(actorId),
    subject_type: subjectType === 'all' ? undefined : subjectType,
    activity_event: activityEvent === 'all' ? undefined : activityEvent,
    batch_uuid: batchUuid || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    has_changes: hasChanges || undefined,
    page,
    per_page: Number(perPage),
  }), [activityEvent, actorId, batchUuid, dateFrom, dateTo, deferredSearch, hasChanges, page, perPage, subjectType]);

  const auditFiltersQuery = useQuery({
    queryKey: queryKeys.audit.filters(),
    queryFn: () => auditService.filters(),
    staleTime: 15 * 60 * 1000,
  });

  const auditQuery = useQuery({
    queryKey: queryKeys.audit.list(filters),
    queryFn: () => auditService.list(filters),
    refetchInterval: 30000,
  });

  const entries = auditQuery.data?.data ?? [];
  const pagination = auditQuery.data?.meta;
  const total = pagination?.total ?? 0;
  const scope = pagination?.scope ?? auditFiltersQuery.data?.scope;

  useEffect(() => {
    if (entries.length === 0) {
      setSelectedEntryId(null);
      return;
    }

    if (!entries.some((entry) => entry.id === selectedEntryId)) {
      setSelectedEntryId(entries[0].id);
    }
  }, [entries, selectedEntryId]);

  const selectedEntry = useMemo(
    () => entries.find((entry) => entry.id === selectedEntryId) ?? null,
    [entries, selectedEntryId],
  );

  const groupedEntries = useMemo(() => {
    const groups = new Map<string, { label: string; items: AuditEntry[] }>();

    entries.forEach((entry) => {
      const key = formatDayKey(entry.created_at);
      const existingGroup = groups.get(key);

      if (existingGroup) {
        existingGroup.items.push(entry);
        return;
      }

      groups.set(key, {
        label: formatDayLabel(entry.created_at),
        items: [entry],
      });
    });

    return Array.from(groups.values());
  }, [entries]);

  const totalChangedEntries = useMemo(
    () => entries.filter((entry) => entry.changes.count > 0).length,
    [entries],
  );

  const uniqueActors = useMemo(
    () => new Set(entries.map((entry) => entry.actor?.id).filter(Boolean)).size,
    [entries],
  );

  const resetFilters = () => {
    setSearch('');
    setActorId('all');
    setSubjectType('all');
    setActivityEvent('all');
    setBatchUuid('');
    setDateFrom('');
    setDateTo('');
    setPerPage('30');
    setHasChanges(false);
    setPage(1);
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-5">
      <PageHeader
        title="Auditoria"
        description={auditQuery.isLoading
          ? 'Carregando trilha de auditoria...'
          : `${total} registros disponiveis${scope?.organization_name ? ` em ${scope.organization_name}` : scope?.is_global ? ' em escopo global' : ''}`}
        actions={(
          <>
            {scope && (
              <Badge variant="outline" className="gap-1 text-xs">
                <Shield className="h-3 w-3" />
                {scope.is_global ? 'Escopo global' : scope.organization_name || 'Escopo restrito'}
              </Badge>
            )}

            <Button variant="outline" onClick={() => auditQuery.refetch()} disabled={auditQuery.isFetching}>
              <RefreshCw className={cn('h-4 w-4', auditQuery.isFetching && 'animate-spin')} />
              Atualizar
            </Button>
          </>
        )}
      />

      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
        <div className="flex items-center gap-2 text-sm font-semibold">
          <Filter className="h-4 w-4 text-primary" />
          Filtros avancados
        </div>

        <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
          <div className="relative xl:col-span-4">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por descricao, ator ou contexto"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="pl-9"
            />
          </div>

          <Select value={actorId} onValueChange={setActorId}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Ator" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos os atores</SelectItem>
              {(auditFiltersQuery.data?.actors ?? []).map((actor) => (
                <SelectItem key={actor.id} value={String(actor.id)}>
                  {actor.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select value={subjectType} onValueChange={setSubjectType}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Entidade" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todas as entidades</SelectItem>
              {(auditFiltersQuery.data?.subject_types ?? []).map((option) => (
                <SelectItem key={option.key} value={option.key}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select value={activityEvent} onValueChange={setActivityEvent}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Evento tecnico" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos os eventos</SelectItem>
              {(auditFiltersQuery.data?.activity_events ?? []).map((option) => (
                <SelectItem key={option} value={option}>
                  {option}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select value={perPage} onValueChange={setPerPage}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Pagina" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="15">15 por pagina</SelectItem>
              <SelectItem value="30">30 por pagina</SelectItem>
              <SelectItem value="50">50 por pagina</SelectItem>
              <SelectItem value="100">100 por pagina</SelectItem>
            </SelectContent>
          </Select>

          <div className="xl:col-span-2">
            <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
          </div>

          <div className="xl:col-span-2">
            <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
          </div>

          <div className="xl:col-span-4">
            <Input
              placeholder="Batch UUID para correlacao"
              value={batchUuid}
              onChange={(event) => setBatchUuid(event.target.value)}
            />
          </div>

          <div className="flex items-center justify-between rounded-2xl border border-border/60 bg-background/70 px-4 py-2.5 xl:col-span-4">
            <div>
              <p className="text-sm font-medium">Somente com alteracoes</p>
              <p className="text-xs text-muted-foreground">Mostra apenas registros com diff saneado.</p>
            </div>
            <Switch checked={hasChanges} onCheckedChange={setHasChanges} />
          </div>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
          <span>Atualizacao automatica a cada 30s</span>
          <Button variant="ghost" size="sm" className="h-auto px-0 text-xs" onClick={resetFilters}>
            Limpar filtros
          </Button>
        </div>
      </section>

      <section className="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_380px]">
        <div className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="glass rounded-2xl border border-border/60 p-4">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Pagina atual</p>
              <p className="mt-2 text-2xl font-semibold">{entries.length}</p>
            </div>

            <div className="glass rounded-2xl border border-border/60 p-4">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Com alteracoes</p>
              <p className="mt-2 text-2xl font-semibold">{totalChangedEntries}</p>
            </div>

            <div className="glass rounded-2xl border border-border/60 p-4">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Atores visiveis</p>
              <p className="mt-2 text-2xl font-semibold">{uniqueActors}</p>
            </div>
          </div>

          <div className="glass overflow-hidden rounded-3xl border border-border/60">
            <div className="flex items-center justify-between border-b border-border/60 px-4 py-4">
              <div>
                <h2 className="text-sm font-semibold">Timeline</h2>
                <p className="text-xs text-muted-foreground">Sequencia cronologica dos eventos auditados.</p>
              </div>

              {auditQuery.isFetching && !auditQuery.isLoading ? (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                  Atualizando
                </div>
              ) : null}
            </div>

            <div className="px-4 py-4">
              {auditQuery.isLoading ? (
                <AuditTimelineSkeleton />
              ) : auditQuery.isError ? (
                <EmptyState
                  icon={Activity}
                  title="Falha ao carregar auditoria"
                  description={auditQuery.error instanceof Error ? auditQuery.error.message : 'Nao foi possivel consultar a trilha agora.'}
                  action={<Button onClick={() => auditQuery.refetch()}>Tentar novamente</Button>}
                />
              ) : entries.length === 0 ? (
                <EmptyState
                  icon={Activity}
                  title="Nenhum registro encontrado"
                  description="Ajuste os filtros ou aguarde novas atividades no sistema."
                />
              ) : (
                <div className="space-y-6">
                  {groupedEntries.map((group) => (
                    <div key={group.label}>
                      <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <Clock3 className="h-3.5 w-3.5" />
                        {group.label}
                      </div>

                      <div className="space-y-3">
                        {group.items.map((entry) => (
                          <button
                            key={entry.id}
                            type="button"
                            onClick={() => setSelectedEntryId(entry.id)}
                            className={cn(
                              'w-full rounded-2xl border p-4 text-left transition-colors',
                              selectedEntryId === entry.id
                                ? 'border-primary/50 bg-primary/5'
                                : 'border-border/60 hover:bg-muted/20',
                            )}
                          >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                              <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                  <Badge variant="outline" className={cn('text-[10px]', CATEGORY_STYLES[entry.category] || CATEGORY_STYLES.system)}>
                                    {CATEGORY_LABELS[entry.category] || 'Sistema'}
                                  </Badge>
                                  <Badge variant="outline" className={cn('text-[10px]', SEVERITY_STYLES[entry.severity] || SEVERITY_STYLES.low)}>
                                    {SEVERITY_LABELS[entry.severity] || 'Baixa'}
                                  </Badge>
                                  <Badge variant="outline" className="text-[10px]">
                                    {entry.subject.type_label}
                                  </Badge>
                                  {entry.activity_event && (
                                    <Badge variant="secondary" className="text-[10px] font-mono">
                                      {entry.activity_event}
                                    </Badge>
                                  )}
                                  {entry.changes.count > 0 && (
                                    <Badge variant="secondary" className="text-[10px]">
                                      {entry.changes.count} campo(s)
                                    </Badge>
                                  )}
                                </div>

                                <p className="mt-3 text-sm font-medium leading-6">
                                  {entry.description}
                                </p>

                                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                  <span>{entry.actor?.name || 'Sistema'}</span>
                                  <span>/</span>
                                  <span>{entry.subject.label}</span>
                                  {entry.related_event && (
                                    <>
                                      <span>/</span>
                                      <span>{entry.related_event.title}</span>
                                    </>
                                  )}
                                  {entry.organization && (
                                    <>
                                      <span>/</span>
                                      <span>{entry.organization.name}</span>
                                    </>
                                  )}
                                </div>
                              </div>

                              <span className="shrink-0 text-xs text-muted-foreground">
                                {formatDateTime(entry.created_at)}
                              </span>
                            </div>
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {pagination ? (
              <AuditPagination
                currentPage={pagination.page}
                lastPage={pagination.last_page}
                perPage={pagination.per_page}
                total={pagination.total}
                isFetching={auditQuery.isFetching}
                onPageChange={setPage}
              />
            ) : null}
          </div>
        </div>

        <aside className="lg:sticky lg:top-20 lg:self-start">
          <div className="glass rounded-3xl border border-border/60 p-5">
            <div className="border-b border-border/60 pb-4">
              <h2 className="text-sm font-semibold">Detalhes do registro</h2>
              <p className="mt-1 text-xs text-muted-foreground">
                Contexto saneado, ator, escopo e diffs do item selecionado.
              </p>
            </div>

            {!selectedEntry ? (
              <div className="py-12">
                <EmptyState
                  icon={Activity}
                  title="Selecione um item"
                  description="Escolha um registro da timeline para inspecionar o contexto detalhado."
                />
              </div>
            ) : (
              <div className="space-y-5 pt-5">
                <div className="space-y-3">
                  <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className={cn('text-[10px]', CATEGORY_STYLES[selectedEntry.category] || CATEGORY_STYLES.system)}>
                      {CATEGORY_LABELS[selectedEntry.category] || 'Sistema'}
                    </Badge>
                    <Badge variant="outline" className={cn('text-[10px]', SEVERITY_STYLES[selectedEntry.severity] || SEVERITY_STYLES.low)}>
                      {SEVERITY_LABELS[selectedEntry.severity] || 'Baixa'}
                    </Badge>
                    <Badge variant="outline" className="text-[10px]">
                      {selectedEntry.subject.type_label}
                    </Badge>
                  </div>

                  <div>
                    <p className="text-sm font-semibold leading-6">{selectedEntry.description}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{formatDateTime(selectedEntry.created_at)}</p>
                    {selectedEntry.activity_event && (
                      <p className="mt-2 font-mono text-[11px] text-muted-foreground">{selectedEntry.activity_event}</p>
                    )}
                  </div>
                </div>

                <div className="grid gap-3 rounded-2xl border border-border/60 bg-background/70 p-4">
                  <div>
                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Ator</p>
                    <p className="mt-1 text-sm font-medium">{selectedEntry.actor?.name || 'Sistema'}</p>
                    {selectedEntry.actor?.email && (
                      <p className="text-xs text-muted-foreground">{selectedEntry.actor.email}</p>
                    )}
                  </div>

                  <div>
                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Entidade</p>
                    {selectedEntry.subject.route ? (
                      <Link to={selectedEntry.subject.route} className="mt-1 inline-block text-sm font-medium text-primary hover:underline">
                        {selectedEntry.subject.label}
                      </Link>
                    ) : (
                      <p className="mt-1 text-sm font-medium">{selectedEntry.subject.label}</p>
                    )}
                  </div>

                  {selectedEntry.related_event && (
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Evento relacionado</p>
                      {selectedEntry.related_event.route ? (
                        <Link to={selectedEntry.related_event.route} className="mt-1 inline-block text-sm font-medium text-primary hover:underline">
                          {selectedEntry.related_event.title}
                        </Link>
                      ) : (
                        <p className="mt-1 text-sm font-medium">{selectedEntry.related_event.title}</p>
                      )}
                    </div>
                  )}

                  {selectedEntry.organization && (
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Organizacao</p>
                      <p className="mt-1 text-sm font-medium">{selectedEntry.organization.name}</p>
                    </div>
                  )}

                  {selectedEntry.batch_uuid && (
                    <div>
                      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Batch</p>
                      <p className="mt-1 break-all font-mono text-xs text-muted-foreground">{selectedEntry.batch_uuid}</p>
                    </div>
                  )}
                </div>

                {selectedEntry.changes.fields.length > 0 ? (
                  <div>
                    <p className="mb-2 text-[11px] uppercase tracking-wide text-muted-foreground">Campos alterados</p>
                    <div className="flex flex-wrap gap-2">
                      {selectedEntry.changes.fields.map((field) => (
                        <Badge key={field} variant="secondary" className="text-[10px]">
                          {field}
                        </Badge>
                      ))}
                    </div>
                  </div>
                ) : null}

                <JsonPanel title="Estado anterior" value={selectedEntry.changes.old} />
                <JsonPanel title="Estado atual" value={selectedEntry.changes.new} />
                <JsonPanel title="Metadados seguros" value={selectedEntry.metadata} />
              </div>
            )}
          </div>
        </aside>
      </section>
    </motion.div>
  );
}

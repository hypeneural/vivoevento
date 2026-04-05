import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Activity,
  Camera,
  CheckCircle2,
  Gamepad2,
  Globe2,
  Image,
  Layers3,
  Loader2,
  Monitor,
  Upload,
  Users2,
} from 'lucide-react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { useTheme } from '@/app/providers/ThemeProvider';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { usePermissions } from '@/shared/hooks/usePermissions';

import { AnalyticsAsyncSelect } from './components/AnalyticsAsyncSelect';
import { analyticsService } from './services/analytics.service';
import type {
  AnalyticsBreakdownItem,
  AnalyticsDelta,
  AnalyticsFilters,
  AnalyticsModule,
  AnalyticsOption,
  AnalyticsPeriod,
  AnalyticsPlayTimelinePoint,
  AnalyticsTrafficTimelinePoint,
  EventAnalyticsResponse,
  PlatformAnalyticsResponse,
} from './types';

const PERIOD_OPTIONS: Array<{ value: AnalyticsPeriod; label: string }> = [
  { value: '7d', label: 'Ultimos 7 dias' },
  { value: '30d', label: 'Ultimos 30 dias' },
  { value: '90d', label: 'Ultimos 90 dias' },
  { value: 'custom', label: 'Periodo customizado' },
];

const MODULE_OPTIONS: Array<{ value: AnalyticsModule; label: string }> = [
  { value: 'live', label: 'Live' },
  { value: 'hub', label: 'Hub' },
  { value: 'wall', label: 'Wall' },
  { value: 'play', label: 'Play' },
];

const EVENT_STATUS_OPTIONS = [
  { value: 'draft', label: 'Rascunho' },
  { value: 'scheduled', label: 'Agendado' },
  { value: 'active', label: 'Ativo' },
  { value: 'paused', label: 'Pausado' },
  { value: 'ended', label: 'Encerrado' },
  { value: 'archived', label: 'Arquivado' },
];

function parsePeriod(value: string | null): AnalyticsPeriod {
  return value === '7d' || value === '30d' || value === '90d' || value === 'custom'
    ? value
    : '30d';
}

function parseNumber(value: string | null): number | null {
  if (!value) return null;

  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function useChartStyles() {
  const { resolvedTheme } = useTheme();
  const isDark = resolvedTheme === 'dark';

  return {
    grid: isDark ? 'hsl(230 12% 18%)' : 'hsl(220 13% 91%)',
    text: isDark ? 'hsl(220 10% 55%)' : 'hsl(220 10% 46%)',
    tooltipBg: isDark ? 'hsl(230 14% 11%)' : 'hsl(0 0% 100%)',
    tooltipBorder: isDark ? 'hsl(230 12% 18%)' : 'hsl(220 13% 90%)',
    primary: isDark ? 'hsl(258 70% 58%)' : 'hsl(258 65% 52%)',
    accent: isDark ? 'hsl(215 80% 55%)' : 'hsl(215 75% 50%)',
    success: isDark ? 'hsl(152 60% 42%)' : 'hsl(152 60% 38%)',
    warning: isDark ? 'hsl(35 85% 58%)' : 'hsl(35 82% 52%)',
    neutral: isDark ? 'hsl(220 14% 70%)' : 'hsl(220 12% 52%)',
  };
}

function formatInteger(value: number) {
  return value.toLocaleString('pt-BR');
}

function formatPercent(value: number) {
  return `${value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`;
}

function formatDateTick(value: string) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
  });
}

function formatDateRange(dateFrom?: string, dateTo?: string) {
  if (!dateFrom || !dateTo) {
    return 'Periodo indisponivel';
  }

  return `${new Date(`${dateFrom}T00:00:00`).toLocaleDateString('pt-BR')} ate ${new Date(`${dateTo}T00:00:00`).toLocaleDateString('pt-BR')}`;
}

function deltaLabel(delta?: AnalyticsDelta) {
  if (!delta) return undefined;

  const sign = delta.direction > 0 ? '+' : '';
  const formatted = delta.type === 'points'
    ? `${sign}${delta.value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })} pts`
    : `${sign}${delta.value.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;

  return `${formatted} vs periodo anterior`;
}

function deltaType(delta?: AnalyticsDelta): 'positive' | 'negative' | 'neutral' {
  if (!delta || delta.direction === 0) return 'neutral';
  return delta.direction > 0 ? 'positive' : 'negative';
}

function hasAnalyticsData(summary?: PlatformAnalyticsResponse['summary'] | EventAnalyticsResponse['summary']) {
  if (!summary) return false;

  return summary.uploads_received > 0
    || summary.public_interactions > 0
    || summary.play_sessions > 0
    || summary.unique_players > 0;
}

function BreakdownCard({ title, items }: { title: string; items: AnalyticsBreakdownItem[] }) {
  return (
    <div className="glass rounded-3xl border border-border/60 p-4">
      <div className="mb-4 flex items-center gap-2 text-sm font-semibold">
        <Layers3 className="h-4 w-4 text-primary" />
        {title}
      </div>

      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">Sem dados no periodo selecionado.</p>
      ) : (
        <div className="space-y-3">
          {items.map((item) => (
            <div key={item.key} className="space-y-1.5">
              <div className="flex items-center justify-between gap-3 text-sm">
                <span className="truncate">{item.label}</span>
                <span className="shrink-0 font-medium">
                  {formatInteger(item.count)} · {formatPercent(item.percentage)}
                </span>
              </div>
              <div className="h-2 overflow-hidden rounded-full bg-muted/60">
                <div
                  className="h-full rounded-full bg-primary/80 transition-all"
                  style={{ width: `${Math.min(100, Math.max(0, item.percentage))}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function buildSearchParams(filters: {
  period: AnalyticsPeriod;
  dateFrom: string;
  dateTo: string;
  organizationId: number | null;
  clientId: number | null;
  eventStatus: string;
  module: string;
  eventId: number | null;
}) {
  const next = new URLSearchParams();

  if (filters.period !== '30d') next.set('period', filters.period);
  if (filters.period === 'custom') {
    if (filters.dateFrom) next.set('date_from', filters.dateFrom);
    if (filters.dateTo) next.set('date_to', filters.dateTo);
  }
  if (filters.organizationId) next.set('organization_id', String(filters.organizationId));
  if (filters.clientId) next.set('client_id', String(filters.clientId));
  if (filters.eventStatus !== 'all') next.set('event_status', filters.eventStatus);
  if (filters.module !== 'all') next.set('module', filters.module);
  if (filters.eventId) next.set('event_id', String(filters.eventId));

  return next;
}

export default function AnalyticsPage() {
  const { isPlatformAdmin } = usePermissions();
  const [searchParams, setSearchParams] = useSearchParams();

  const [period, setPeriod] = useState<AnalyticsPeriod>(parsePeriod(searchParams.get('period')));
  const [dateFrom, setDateFrom] = useState(searchParams.get('date_from') ?? '');
  const [dateTo, setDateTo] = useState(searchParams.get('date_to') ?? '');
  const [organizationId, setOrganizationId] = useState<number | null>(parseNumber(searchParams.get('organization_id')));
  const [clientId, setClientId] = useState<number | null>(parseNumber(searchParams.get('client_id')));
  const [eventStatus, setEventStatus] = useState(searchParams.get('event_status') ?? 'all');
  const [moduleFilter, setModuleFilter] = useState(searchParams.get('module') ?? 'all');
  const [eventId, setEventId] = useState<number | null>(parseNumber(searchParams.get('event_id')));

  const [organizationSearch, setOrganizationSearch] = useState('');
  const [clientSearch, setClientSearch] = useState('');
  const [eventSearch, setEventSearch] = useState('');

  const deferredOrganizationSearch = useDeferredValue(organizationSearch);
  const deferredClientSearch = useDeferredValue(clientSearch);
  const deferredEventSearch = useDeferredValue(eventSearch);

  useEffect(() => {
    setSearchParams(buildSearchParams({
      period,
      dateFrom,
      dateTo,
      organizationId,
      clientId,
      eventStatus,
      module: moduleFilter,
      eventId,
    }), { replace: true });
  }, [period, dateFrom, dateTo, organizationId, clientId, eventStatus, moduleFilter, eventId, setSearchParams]);

  const analyticsFilters = useMemo<AnalyticsFilters>(() => ({
    period,
    date_from: period === 'custom' ? dateFrom || undefined : undefined,
    date_to: period === 'custom' ? dateTo || undefined : undefined,
    organization_id: isPlatformAdmin ? organizationId ?? undefined : undefined,
    client_id: clientId ?? undefined,
    event_status: eventStatus === 'all' ? undefined : eventStatus,
    module: moduleFilter === 'all' ? undefined : moduleFilter as AnalyticsModule,
    event_id: eventId ?? undefined,
  }), [period, dateFrom, dateTo, isPlatformAdmin, organizationId, clientId, eventStatus, moduleFilter, eventId]);

  const eventAnalyticsFilters = useMemo<AnalyticsFilters>(() => ({
    period,
    date_from: period === 'custom' ? dateFrom || undefined : undefined,
    date_to: period === 'custom' ? dateTo || undefined : undefined,
    module: moduleFilter === 'all' ? undefined : moduleFilter as AnalyticsModule,
  }), [period, dateFrom, dateTo, moduleFilter]);

  const organizationsQuery = useQuery({
    queryKey: queryKeys.analytics.options('organizations', { search: deferredOrganizationSearch }),
    queryFn: () => analyticsService.searchOrganizations(deferredOrganizationSearch),
    enabled: isPlatformAdmin,
    placeholderData: keepPreviousData,
  });

  const clientsQuery = useQuery({
    queryKey: queryKeys.analytics.options('clients', { search: deferredClientSearch, organizationId }),
    queryFn: () => analyticsService.searchClients(deferredClientSearch, organizationId ?? undefined),
    placeholderData: keepPreviousData,
  });

  const eventsQuery = useQuery({
    queryKey: queryKeys.analytics.options('events', {
      search: deferredEventSearch,
      organizationId,
      clientId,
      eventStatus,
      module: moduleFilter,
    }),
    queryFn: () => analyticsService.searchEvents(deferredEventSearch, {
      organization_id: organizationId ?? undefined,
      client_id: clientId ?? undefined,
      event_status: eventStatus === 'all' ? undefined : eventStatus,
      module: moduleFilter === 'all' ? undefined : moduleFilter as AnalyticsModule,
    }),
    placeholderData: keepPreviousData,
  });

  const selectedOrganizationQuery = useQuery({
    queryKey: queryKeys.analytics.options('organization-detail', { id: organizationId }),
    queryFn: () => analyticsService.getOrganizationOption(organizationId!),
    enabled: isPlatformAdmin && !!organizationId,
  });

  const selectedClientQuery = useQuery({
    queryKey: queryKeys.analytics.options('client-detail', { id: clientId }),
    queryFn: () => analyticsService.getClientOption(clientId!),
    enabled: !!clientId,
  });

  const selectedEventQuery = useQuery({
    queryKey: queryKeys.analytics.options('event-detail', { id: eventId }),
    queryFn: () => analyticsService.getEventOption(eventId!),
    enabled: !!eventId,
  });

  const platformQuery = useQuery({
    queryKey: queryKeys.analytics.platform(analyticsFilters),
    queryFn: () => analyticsService.getPlatform(analyticsFilters),
    enabled: !eventId && (period !== 'custom' || (!!dateFrom && !!dateTo)),
    placeholderData: keepPreviousData,
  });

  const eventQuery = useQuery({
    queryKey: queryKeys.analytics.event(eventId ?? 'none', eventAnalyticsFilters),
    queryFn: () => analyticsService.getEvent(eventId!, eventAnalyticsFilters),
    enabled: !!eventId && (period !== 'custom' || (!!dateFrom && !!dateTo)),
    placeholderData: keepPreviousData,
  });

  const selectedOrganization = useMemo<AnalyticsOption | null>(() => (
    organizationsQuery.data?.find((option) => option.id === organizationId)
      ?? selectedOrganizationQuery.data
      ?? null
  ), [organizationsQuery.data, organizationId, selectedOrganizationQuery.data]);

  const selectedClient = useMemo<AnalyticsOption | null>(() => (
    clientsQuery.data?.find((option) => option.id === clientId)
      ?? selectedClientQuery.data
      ?? null
  ), [clientId, clientsQuery.data, selectedClientQuery.data]);

  const selectedEvent = useMemo<AnalyticsOption | null>(() => (
    eventsQuery.data?.find((option) => option.id === eventId)
      ?? selectedEventQuery.data
      ?? null
  ), [eventId, eventsQuery.data, selectedEventQuery.data]);

  const isEventMode = !!eventId;
  const data = isEventMode ? eventQuery.data : platformQuery.data;
  const eventAnalytics = isEventMode ? data as EventAnalyticsResponse | undefined : undefined;
  const platformAnalytics = !isEventMode ? data as PlatformAnalyticsResponse | undefined : undefined;
  const isLoading = isEventMode ? eventQuery.isLoading : platformQuery.isLoading;
  const isError = isEventMode ? eventQuery.isError : platformQuery.isError;
  const isFetching = isEventMode ? eventQuery.isFetching : platformQuery.isFetching;
  const summary = data?.summary;
  const cs = useChartStyles();

  const tooltipStyle = useMemo<React.CSSProperties>(() => ({
    background: cs.tooltipBg,
    border: `1px solid ${cs.tooltipBorder}`,
    borderRadius: 10,
    fontSize: 12,
  }), [cs.tooltipBg, cs.tooltipBorder]);

  const trafficSeries = useMemo<Array<{ key: keyof AnalyticsTrafficTimelinePoint; label: string; color: string }>>(() => {
    const all = [
      { key: 'hub_views' as const, label: 'Hub', color: cs.primary },
      { key: 'gallery_views' as const, label: 'Galeria', color: cs.accent },
      { key: 'wall_views' as const, label: 'Wall', color: cs.warning },
      { key: 'upload_views' as const, label: 'Upload', color: cs.neutral },
      { key: 'play_views' as const, label: 'Play', color: cs.success },
    ];

    if (moduleFilter === 'all') {
      return all;
    }

    return all.filter((series) => (
      moduleFilter === 'live'
        ? series.key === 'gallery_views' || series.key === 'upload_views'
        : series.key === `${moduleFilter}_views`
    ));
  }, [cs.accent, cs.neutral, cs.primary, cs.success, cs.warning, moduleFilter]);

  const periodLabel = data?.filters
    ? formatDateRange(data.filters.date_from, data.filters.date_to)
    : period === 'custom'
      ? formatDateRange(dateFrom, dateTo)
      : PERIOD_OPTIONS.find((option) => option.value === period)?.label;

  const resetFilters = () => {
    setPeriod('30d');
    setDateFrom('');
    setDateTo('');
    setOrganizationId(null);
    setClientId(null);
    setEventStatus('all');
    setModuleFilter('all');
    setEventId(null);
    setOrganizationSearch('');
    setClientSearch('');
    setEventSearch('');
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title={isEventMode ? 'Analytics do Evento' : 'Analytics'}
        description={isEventMode
          ? `${selectedEvent?.label || eventAnalytics?.event.title || 'Evento selecionado'} · ${periodLabel}`
          : `Visao geral por uso, midia e trafego · ${periodLabel}`}
      />

      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
          <Select value={period} onValueChange={(value) => setPeriod(value as AnalyticsPeriod)}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Periodo" />
            </SelectTrigger>
            <SelectContent>
              {PERIOD_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {period === 'custom' ? (
            <>
              <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="xl:col-span-2" />
              <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="xl:col-span-2" />
            </>
          ) : null}

          {isPlatformAdmin ? (
            <div className="xl:col-span-2">
              <AnalyticsAsyncSelect
                value={selectedOrganization}
                options={organizationsQuery.data ?? []}
                search={organizationSearch}
                onSearchChange={setOrganizationSearch}
                onSelect={(option) => {
                  setOrganizationId(option?.id ?? null);
                  setClientId(null);
                  setEventId(null);
                }}
                placeholder="Filtrar parceiro"
                emptyMessage="Nenhum parceiro encontrado."
                loading={organizationsQuery.isFetching}
              />
            </div>
          ) : null}

          <div className="xl:col-span-2">
            <AnalyticsAsyncSelect
              value={selectedClient}
              options={clientsQuery.data ?? []}
              search={clientSearch}
              onSearchChange={setClientSearch}
              onSelect={(option) => {
                setClientId(option?.id ?? null);
                setEventId(null);
              }}
              placeholder="Filtrar cliente"
              emptyMessage="Nenhum cliente encontrado."
              loading={clientsQuery.isFetching}
            />
          </div>

          <div className="xl:col-span-2">
            <AnalyticsAsyncSelect
              value={selectedEvent}
              options={eventsQuery.data ?? []}
              search={eventSearch}
              onSearchChange={setEventSearch}
              onSelect={(option) => setEventId(option?.id ?? null)}
              placeholder="Selecionar evento"
              emptyMessage="Nenhum evento encontrado."
              loading={eventsQuery.isFetching}
            />
          </div>

          <Select value={eventStatus} onValueChange={(value) => { setEventStatus(value); setEventId(null); }}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Status do evento" />
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

          <Select value={moduleFilter} onValueChange={(value) => setModuleFilter(value)}>
            <SelectTrigger className="xl:col-span-2">
              <SelectValue placeholder="Modulo" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos os modulos</SelectItem>
              {MODULE_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <div className="xl:col-span-2">
            <Button variant="outline" className="w-full" onClick={resetFilters}>
              Limpar filtros
            </Button>
          </div>
        </div>
      </section>

      {isLoading && !data ? (
        <div className="glass rounded-3xl border border-border/60 px-4 py-20 text-center">
          <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando analytics...
          </div>
        </div>
      ) : isError ? (
        <div className="glass rounded-3xl border border-destructive/30">
          <EmptyState
            icon={Activity}
            title="Nao foi possivel carregar a analytics"
            description="Revise os filtros e tente novamente."
          />
        </div>
      ) : !hasAnalyticsData(summary) ? (
        <div className="glass rounded-3xl border border-border/60">
          <EmptyState
            icon={Activity}
            title="Sem dados suficientes para analytics"
            description="Nao existe tracking ou movimentacao suficiente no periodo selecionado. Ajuste os filtros ou aguarde novos acessos e uploads."
          />
        </div>
      ) : data ? (
        <>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <StatsCard
              title="Uploads Recebidos"
              value={formatInteger(summary!.uploads_received)}
              icon={Camera}
              change={deltaLabel(data.deltas.uploads_received)}
              changeType={deltaType(data.deltas.uploads_received)}
            />
            <StatsCard
              title="Taxa de Aprovacao"
              value={formatPercent(summary!.approval_rate)}
              icon={CheckCircle2}
              change={deltaLabel(data.deltas.approval_rate)}
              changeType={deltaType(data.deltas.approval_rate)}
            />
            <StatsCard
              title="Taxa de Publicacao"
              value={formatPercent(summary!.publication_rate)}
              icon={Upload}
              change={deltaLabel(data.deltas.publication_rate)}
              changeType={deltaType(data.deltas.publication_rate)}
            />
            <StatsCard
              title="Hub Views"
              value={formatInteger(summary!.hub_views)}
              icon={Globe2}
              change={deltaLabel(data.deltas.hub_views)}
              changeType={deltaType(data.deltas.hub_views)}
            />
            <StatsCard
              title="Galeria"
              value={formatInteger(summary!.gallery_views)}
              icon={Image}
              change={deltaLabel(data.deltas.gallery_views)}
              changeType={deltaType(data.deltas.gallery_views)}
            />
            <StatsCard
              title="Wall"
              value={formatInteger(summary!.wall_views)}
              icon={Monitor}
              change={deltaLabel(data.deltas.wall_views)}
              changeType={deltaType(data.deltas.wall_views)}
            />
            <StatsCard
              title="Views de Upload"
              value={formatInteger(summary!.upload_views)}
              icon={Upload}
              change={deltaLabel(data.deltas.upload_views)}
              changeType={deltaType(data.deltas.upload_views)}
            />
            <StatsCard
              title="Play Sessions"
              value={formatInteger(summary!.play_sessions)}
              icon={Gamepad2}
              change={deltaLabel(data.deltas.play_sessions)}
              changeType={deltaType(data.deltas.play_sessions)}
              description={`${formatInteger(summary!.unique_players)} jogadores unicos`}
            />
          </div>

          <div className="grid gap-4 xl:grid-cols-2">
            <div className="glass rounded-3xl border border-border/60 p-4">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h3 className="text-sm font-semibold">Uploads, aprovacao e publicacao</h3>
                  <p className="text-xs text-muted-foreground">Numeros absolutos por dia.</p>
                </div>
                {isFetching ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : null}
              </div>
              <ResponsiveContainer width="100%" height={280}>
                <AreaChart data={data.timelines.media}>
                  <defs>
                    <linearGradient id="uploads-gradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor={cs.primary} stopOpacity={0.28} />
                      <stop offset="95%" stopColor={cs.primary} stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} />
                  <XAxis dataKey="date" tick={{ fill: cs.text, fontSize: 11 }} tickFormatter={formatDateTick} />
                  <YAxis tick={{ fill: cs.text, fontSize: 11 }} />
                  <Tooltip contentStyle={tooltipStyle} labelFormatter={formatDateTick} />
                  <Legend />
                  <Area type="monotone" dataKey="uploads_received" name="Recebidos" stroke={cs.primary} fill="url(#uploads-gradient)" strokeWidth={2} />
                  <Area type="monotone" dataKey="uploads_approved" name="Aprovados" stroke={cs.success} fillOpacity={0} strokeWidth={2} />
                  <Area type="monotone" dataKey="uploads_published" name="Publicados" stroke={cs.warning} fillOpacity={0} strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            </div>

            <div className="glass rounded-3xl border border-border/60 p-4">
              <div className="mb-4">
                <h3 className="text-sm font-semibold">Trafego por superficie</h3>
                <p className="text-xs text-muted-foreground">Views publicas e interacoes registradas por dia.</p>
              </div>
              <ResponsiveContainer width="100%" height={280}>
                <LineChart data={data.timelines.traffic}>
                  <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} />
                  <XAxis dataKey="date" tick={{ fill: cs.text, fontSize: 11 }} tickFormatter={formatDateTick} />
                  <YAxis tick={{ fill: cs.text, fontSize: 11 }} />
                  <Tooltip contentStyle={tooltipStyle} labelFormatter={formatDateTick} />
                  <Legend />
                  {trafficSeries.map((series) => (
                    <Line
                      key={series.key}
                      type="monotone"
                      dataKey={series.key}
                      name={series.label}
                      stroke={series.color}
                      strokeWidth={2}
                      dot={false}
                    />
                  ))}
                </LineChart>
              </ResponsiveContainer>
            </div>
          </div>

          <div className="grid gap-4 xl:grid-cols-3">
            <BreakdownCard
              title={isEventMode ? 'Fontes de midia' : 'Breakdown por modulo'}
              items={isEventMode ? (eventAnalytics?.breakdowns.source_types ?? []) : (platformAnalytics?.breakdowns.modules ?? [])}
            />
            <BreakdownCard
              title={isEventMode ? 'Superficies' : 'Tipos de origem'}
              items={isEventMode ? (eventAnalytics?.breakdowns.surfaces ?? []) : (platformAnalytics?.breakdowns.source_types ?? [])}
            />
            <BreakdownCard
              title={isEventMode ? 'Funil do evento' : 'Status dos eventos'}
              items={isEventMode
                ? (eventAnalytics?.funnel ?? []).map((step) => ({
                    key: step.key,
                    label: step.label,
                    count: step.count,
                    percentage: step.percentage,
                  }))
                : (platformAnalytics?.breakdowns.event_statuses ?? [])}
            />
          </div>

          <div className="grid gap-4 xl:grid-cols-2">
              <div className="glass rounded-3xl border border-border/60 p-4">
                <div className="mb-4 flex items-center gap-2 text-sm font-semibold">
                  <Users2 className="h-4 w-4 text-primary" />
                  Evolucao do Play
                </div>
                <ResponsiveContainer width="100%" height={240}>
                  <BarChart data={data.timelines.play}>
                    <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} />
                    <XAxis dataKey="date" tick={{ fill: cs.text, fontSize: 11 }} tickFormatter={formatDateTick} />
                    <YAxis tick={{ fill: cs.text, fontSize: 11 }} />
                    <Tooltip contentStyle={tooltipStyle} labelFormatter={formatDateTick} />
                    <Legend />
                    <Bar dataKey="sessions" name="Sessoes" fill={cs.success} radius={[6, 6, 0, 0]} />
                    <Bar dataKey="unique_players" name="Jogadores unicos" fill={cs.accent} radius={[6, 6, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>

              <div className="glass rounded-3xl border border-border/60 p-4">
                <div className="mb-4 flex items-center gap-2 text-sm font-semibold">
                  <Gamepad2 className="h-4 w-4 text-primary" />
                  {isEventMode ? 'Jogos do evento' : 'Top eventos por interacao'}
                </div>

                {isEventMode ? (
                  eventAnalytics?.play?.games && eventAnalytics.play.games.length > 0 ? (
                    <div className="space-y-3">
                      {eventAnalytics.play.games.map((game) => (
                        <div key={game.id} className="rounded-2xl border border-border/60 bg-background/60 p-3">
                          <div className="flex items-center justify-between gap-3">
                            <div>
                              <p className="font-medium">{game.title}</p>
                              <p className="text-xs text-muted-foreground">{game.game_type_name || game.game_type_key || 'Jogo'}</p>
                            </div>
                            <div className="text-right text-sm">
                              <p className="font-medium">{formatInteger(game.sessions)} sessoes</p>
                              <p className="text-xs text-muted-foreground">{formatPercent(game.share_percentage)} do total</p>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">Nenhum jogo com sessoes no periodo selecionado.</p>
                  )
                ) : (
                  (platformAnalytics?.rankings.top_events.length ?? 0) > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Evento</TableHead>
                          <TableHead>Uploads</TableHead>
                          <TableHead>Interacoes</TableHead>
                          <TableHead>Share</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {platformAnalytics!.rankings.top_events.map((event) => (
                          <TableRow key={event.event_id}>
                            <TableCell>
                              <div className="min-w-0">
                                <p className="truncate font-medium">{event.title}</p>
                                <p className="truncate text-xs text-muted-foreground">{event.client_name || event.organization_name || 'Sem cliente'}</p>
                              </div>
                            </TableCell>
                            <TableCell>{formatInteger(event.uploads)}</TableCell>
                            <TableCell>{formatInteger(event.public_interactions)}</TableCell>
                            <TableCell>{formatPercent(event.share_percentage)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <p className="text-sm text-muted-foreground">Nenhum evento com interacoes no periodo selecionado.</p>
                  )
                )}
              </div>
            </div>
        </>
      ) : null}
    </motion.div>
  );
}

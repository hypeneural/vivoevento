import { motion } from 'framer-motion';
import {
  CalendarDays, Camera, CheckCircle2, Gauge, Gamepad2, Globe2, Wallet, Users2,
  TrendingUp, AlertTriangle, AlertCircle, Clock, Zap, Eye, ImagePlus, ShieldCheck,
  ArrowRight, Loader2, CalendarClock, Info,
} from 'lucide-react';
import {
  AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell,
  ResponsiveContainer, XAxis, YAxis, Tooltip, CartesianGrid,
} from 'recharts';
import { StatsCard } from '@/shared/components/StatsCard';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { useTheme } from '@/app/providers/ThemeProvider';
import { useDashboardStats, type DashboardData } from './hooks/useDashboardStats';
import { EVENT_TYPE_LABELS } from '@/shared/types';

// ─── Animation variants ───────────────────────────────────
const container = { hidden: {}, show: { transition: { staggerChildren: 0.04 } } };
const item = { hidden: { opacity: 0, y: 10 }, show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: 'easeOut' } } };

// ─── Chart styles hook ────────────────────────────────────
function useChartStyles() {
  const { resolvedTheme } = useTheme();
  const isDark = resolvedTheme === 'dark';
  return {
    grid: isDark ? 'hsl(230 12% 18%)' : 'hsl(220 13% 91%)',
    text: isDark ? 'hsl(220 10% 55%)' : 'hsl(220 10% 46%)',
    tooltipBg: isDark ? 'hsl(230 14% 11%)' : 'hsl(0 0% 100%)',
    tooltipBorder: isDark ? 'hsl(230 12% 18%)' : 'hsl(220 13% 90%)',
    tooltipText: isDark ? 'hsl(220 20% 92%)' : 'hsl(230 25% 12%)',
    primary: isDark ? 'hsl(258 70% 58%)' : 'hsl(258 65% 52%)',
    accent: isDark ? 'hsl(215 80% 55%)' : 'hsl(215 75% 50%)',
    success: isDark ? 'hsl(152 60% 42%)' : 'hsl(152 60% 38%)',
    gradientOpacity: isDark ? 0.3 : 0.15,
  };
}

function formatRevenue(cents: number): string {
  return (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 0 });
}

function changeLabel(value: number, suffix: string): string {
  if (value === 0) return '';
  const sign = value > 0 ? '+' : '';
  return `${sign}${value}${suffix}`;
}

function formatRevenueShort(cents: number): string {
  return `R$ ${formatRevenue(cents)}`;
}

function buildCommercialMixDescription(kpis: {
  active_events_subscription_covered: number;
  active_events_single_purchase: number;
  active_events_trial: number;
  active_events_bonus: number;
}) {
  const parts = [
    kpis.active_events_subscription_covered > 0 ? `Conta ${kpis.active_events_subscription_covered}` : null,
    kpis.active_events_single_purchase > 0 ? `Avulso ${kpis.active_events_single_purchase}` : null,
    kpis.active_events_trial > 0 ? `Trial ${kpis.active_events_trial}` : null,
    kpis.active_events_bonus > 0 ? `Bonus ${kpis.active_events_bonus}` : null,
  ].filter((value): value is string => Boolean(value));

  return parts.length > 0 ? parts.join(' · ') : 'Sem distribuicao comercial ativa';
}

function buildRevenueBreakdown(kpis: {
  subscription_revenue_cents: number;
  event_revenue_cents: number;
}) {
  const parts = [
    kpis.subscription_revenue_cents > 0 ? `Assinatura ${formatRevenueShort(kpis.subscription_revenue_cents)}` : null,
    kpis.event_revenue_cents > 0 ? `Evento ${formatRevenueShort(kpis.event_revenue_cents)}` : null,
  ].filter((value): value is string => Boolean(value));

  return parts.length > 0 ? parts.join(' · ') : 'Sem receita liquidada no periodo';
}

// ─── Skeleton Placeholder ─────────────────────────────────
function hasDashboardData(value: DashboardData | undefined): value is DashboardData {
  return Boolean(
    value
      && value.kpis
      && value.changes
      && value.charts
      && Array.isArray(value.charts.uploads_per_hour)
      && Array.isArray(value.charts.events_by_type)
      && Array.isArray(value.charts.engagement_by_module)
      && Array.isArray(value.recent_events)
      && Array.isArray(value.moderation_queue)
      && Array.isArray(value.top_partners)
      && Array.isArray(value.alerts),
  );
}

function SkeletonCard() {
  return (
    <div className="glass rounded-xl p-3.5 animate-pulse">
      <div className="flex items-start justify-between">
        <div className="space-y-2 flex-1">
          <div className="h-3 w-20 bg-muted rounded" />
          <div className="h-6 w-14 bg-muted rounded" />
          <div className="h-2.5 w-24 bg-muted rounded" />
        </div>
        <div className="h-8 w-8 bg-muted rounded-lg" />
      </div>
    </div>
  );
}

function SkeletonChart({ height = 200 }: { height?: number }) {
  return (
    <div className="glass rounded-xl p-4 sm:p-5 animate-pulse">
      <div className="h-3.5 w-32 bg-muted rounded mb-4" />
      <div className="bg-muted/50 rounded-lg" style={{ height }} />
    </div>
  );
}

// ─── Main Component ───────────────────────────────────────
export default function DashboardPage() {
  const { data, isLoading, isError } = useDashboardStats();
  const cs = useChartStyles();
  const dashboardData = hasDashboardData(data) ? data : null;

  const tooltipStyle: React.CSSProperties = {
    background: cs.tooltipBg,
    border: `1px solid ${cs.tooltipBorder}`,
    borderRadius: 10,
    fontSize: 12,
    color: cs.tooltipText,
    boxShadow: '0 8px 24px -4px rgb(0 0 0 / 0.12)',
    padding: '8px 12px',
  };

  // ─── Loading State ──────────────────────────────────────
  if (isLoading) {
    return (
      <div className="space-y-4">
        <PageHeader title="Dashboard" description="Visão geral da plataforma" />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          {Array.from({ length: 8 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
          <SkeletonChart />
          <SkeletonChart />
        </div>
      </div>
    );
  }

  // ─── Error State ────────────────────────────────────────
  if (isError || !dashboardData) {
    return (
      <div className="flex flex-col items-center justify-center h-[60vh] gap-4">
        <div className="rounded-full bg-destructive/10 p-4">
          <AlertCircle className="h-8 w-8 text-destructive" />
        </div>
        <div className="text-center">
          <p className="font-semibold">Erro ao carregar dashboard</p>
          <p className="text-sm text-muted-foreground mt-1">Verifique sua conexão e tente novamente</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => window.location.reload()}>
          Tentar novamente
        </Button>
      </div>
    );
  }

  const { kpis, changes, charts, recent_events, moderation_queue, top_partners, alerts } = dashboardData;

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="space-y-4 sm:space-y-5">
      <PageHeader title="Dashboard" description="Visão geral da plataforma Evento Vivo" />

      {/* ═══ KPI Cards ═══════════════════════════════════ */}
      <motion.div variants={item} className="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
        <StatsCard
          title="Eventos Ativos"
          value={kpis.active_events}
          icon={CalendarDays}
          iconColor="text-violet-500"
          iconBg="bg-violet-500/10"
          change={changes.events_new_this_week > 0 ? `+${changes.events_new_this_week} esta semana` : undefined}
          changeType="positive"
          description={buildCommercialMixDescription(kpis)}
        />
        <StatsCard
          title="Fotos Hoje"
          value={kpis.photos_today.toLocaleString('pt-BR')}
          icon={Camera}
          iconColor="text-blue-500"
          iconBg="bg-blue-500/10"
          change={changeLabel(changes.photos_today_change, '% vs ontem') || undefined}
          changeType={changes.photos_today_change >= 0 ? 'positive' : 'negative'}
        />
        <StatsCard
          title="Aprovadas"
          value={kpis.photos_approved_today.toLocaleString('pt-BR')}
          icon={CheckCircle2}
          iconColor="text-emerald-500"
          iconBg="bg-emerald-500/10"
          change={`${kpis.moderation_rate}% taxa`}
          changeType="positive"
        />
        <StatsCard
          title="Moderação"
          value={`${kpis.moderation_rate}%`}
          icon={ShieldCheck}
          iconColor="text-amber-500"
          iconBg="bg-amber-500/10"
        />
        <StatsCard
          title="Partidas"
          value={kpis.games_played.toLocaleString('pt-BR')}
          icon={Gamepad2}
          iconColor="text-pink-500"
          iconBg="bg-pink-500/10"
          change={changes.games_played_today > 0 ? `+${changes.games_played_today} hoje` : undefined}
          changeType="positive"
        />
        <StatsCard
          title="Acessos Hub"
          value={kpis.hub_accesses.toLocaleString('pt-BR')}
          icon={Globe2}
          iconColor="text-cyan-500"
          iconBg="bg-cyan-500/10"
        />
        <StatsCard
          title="Receita liquidada"
          value={`R$ ${formatRevenue(kpis.revenue_cents)}`}
          icon={Wallet}
          iconColor="text-green-500"
          iconBg="bg-green-500/10"
          description={buildRevenueBreakdown(kpis)}
        />
        <StatsCard
          title="Equipe"
          value={kpis.active_partners}
          icon={Users2}
          iconColor="text-indigo-500"
          iconBg="bg-indigo-500/10"
        />
      </motion.div>

      {/* ═══ Charts Row ══════════════════════════════════ */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-2 gap-2.5 sm:gap-3">
        {/* Uploads per Hour */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="rounded-md bg-primary/10 p-1.5">
              <ImagePlus className="h-3.5 w-3.5 text-primary" />
            </div>
            <h3 className="text-sm font-semibold">Uploads por Hora</h3>
          </div>
          <ResponsiveContainer width="100%" height={180}>
            <AreaChart data={charts.uploads_per_hour}>
              <defs>
                <linearGradient id="uploadGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={cs.primary} stopOpacity={cs.gradientOpacity} />
                  <stop offset="95%" stopColor={cs.primary} stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} />
              <XAxis
                dataKey="hour"
                tick={{ fill: cs.text, fontSize: 10 }}
                tickLine={false}
                axisLine={false}
                interval="preserveStartEnd"
                tickFormatter={(v: string) => v.replace(':00', 'h')}
              />
              <YAxis tick={{ fill: cs.text, fontSize: 10 }} tickLine={false} axisLine={false} width={28} />
              <Tooltip contentStyle={tooltipStyle} />
              <Area type="monotone" dataKey="uploads" stroke={cs.primary} fill="url(#uploadGrad)" strokeWidth={2} dot={false} />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Events by Type */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="rounded-md bg-primary/10 p-1.5">
              <CalendarDays className="h-3.5 w-3.5 text-primary" />
            </div>
            <h3 className="text-sm font-semibold">Eventos por Tipo</h3>
          </div>
          {charts.events_by_type.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-[180px] text-muted-foreground/60">
              <CalendarDays className="h-8 w-8 mb-2" />
              <p className="text-sm">Nenhum evento criado</p>
            </div>
          ) : (
            <div className="flex flex-col sm:flex-row items-center gap-4">
              <div className="w-full sm:w-1/2">
                <ResponsiveContainer width="100%" height={170}>
                  <PieChart>
                    <Pie
                      data={charts.events_by_type}
                      cx="50%"
                      cy="50%"
                      innerRadius={40}
                      outerRadius={68}
                      paddingAngle={3}
                      dataKey="count"
                      nameKey="label"
                      strokeWidth={0}
                    >
                      {charts.events_by_type.map((entry, i) => (
                        <Cell key={i} fill={entry.fill} />
                      ))}
                    </Pie>
                    <Tooltip contentStyle={tooltipStyle} />
                  </PieChart>
                </ResponsiveContainer>
              </div>
              <div className="flex flex-wrap gap-x-4 gap-y-1.5 justify-center sm:justify-start sm:w-1/2">
                {charts.events_by_type.map((e, i) => (
                  <div key={i} className="flex items-center gap-1.5 text-xs">
                    <span className="h-2 w-2 rounded-full shrink-0" style={{ background: e.fill }} />
                    <span className="text-muted-foreground">{e.label || EVENT_TYPE_LABELS[e.type as keyof typeof EVENT_TYPE_LABELS] || e.type}</span>
                    <span className="font-semibold">{e.count}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </motion.div>

      {/* ═══ Engagement + Recent Events ══════════════════ */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-3 gap-2.5 sm:gap-3">
        {/* Module Engagement */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-4">
            <div className="rounded-md bg-blue-500/10 p-1.5">
              <TrendingUp className="h-3.5 w-3.5 text-blue-500" />
            </div>
            <h3 className="text-sm font-semibold">Engajamento</h3>
          </div>
          <ResponsiveContainer width="100%" height={160}>
            <BarChart data={charts.engagement_by_module} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} horizontal={false} />
              <XAxis type="number" tick={{ fill: cs.text, fontSize: 10 }} domain={[0, 100]} hide />
              <YAxis type="category" dataKey="module" tick={{ fill: cs.text, fontSize: 11, fontWeight: 500 }} width={36} axisLine={false} tickLine={false} />
              <Tooltip contentStyle={tooltipStyle} formatter={(v: number) => [`${v}%`, 'Engajamento']} />
              <Bar dataKey="percentage" fill={cs.accent} radius={[0, 8, 8, 0]} barSize={16} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Recent Events */}
        <div className="glass rounded-xl p-4 sm:p-5 lg:col-span-2">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <div className="rounded-md bg-violet-500/10 p-1.5">
                <CalendarClock className="h-3.5 w-3.5 text-violet-500" />
              </div>
              <h3 className="text-sm font-semibold">Eventos Recentes</h3>
            </div>
            <Button variant="ghost" size="sm" className="text-xs h-7 px-2 gap-1" asChild>
              <Link to="/events">Ver todos <ArrowRight className="h-3 w-3" /></Link>
            </Button>
          </div>
          <div className="space-y-0.5">
            {recent_events.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-8 text-muted-foreground/60">
                <CalendarDays className="h-8 w-8 mb-2" />
                <p className="text-sm">Nenhum evento criado ainda</p>
                <Button variant="outline" size="sm" className="mt-3 text-xs" asChild>
                  <Link to="/events/create"><Zap className="h-3.5 w-3.5 mr-1" /> Criar evento</Link>
                </Button>
              </div>
            ) : (
              recent_events.map(evt => (
                <Link
                  key={evt.id}
                  to={`/events/${evt.id}`}
                  className="flex items-center gap-2.5 p-2 rounded-lg hover:bg-muted/50 active:bg-muted/70 transition-colors group"
                >
                  {evt.cover_image_url ? (
                    <img src={evt.cover_image_url} alt="" className="h-9 w-14 rounded-md object-cover shrink-0 ring-1 ring-border/50" loading="lazy" />
                  ) : (
                    <div className="h-9 w-14 rounded-md bg-muted flex items-center justify-center shrink-0">
                      <CalendarDays className="h-3.5 w-3.5 text-muted-foreground" />
                    </div>
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate group-hover:text-primary transition-colors">{evt.title}</p>
                    <p className="text-[11px] text-muted-foreground truncate">
                      {evt.organization_name}
                      {evt.starts_at && ` · ${new Date(evt.starts_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })}`}
                    </p>
                  </div>
                  <div className="hidden sm:flex items-center gap-2">
                    <EventStatusBadge status={evt.status as any} />
                    <span className="text-[11px] tabular-nums text-muted-foreground w-8 text-right">{evt.photos_received}</span>
                  </div>
                </Link>
              ))
            )}
          </div>
        </div>
      </motion.div>

      {/* ═══ Moderation + Quick Actions + Partners ═══════ */}
      <motion.div variants={item} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 sm:gap-3">
        {/* Moderation Queue */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <div className="rounded-md bg-amber-500/10 p-1.5">
                <Eye className="h-3.5 w-3.5 text-amber-500" />
              </div>
              <h3 className="text-sm font-semibold">Moderação</h3>
            </div>
            {kpis.pending_moderation > 0 && (
              <span className="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-500">
                {kpis.pending_moderation}
              </span>
            )}
          </div>
          {moderation_queue.length > 0 ? (
            <div className="grid grid-cols-4 gap-1.5 mb-3">
              {moderation_queue.map(m => (
                m.thumbnail_url ? (
                  <img key={m.id} src={m.thumbnail_url} alt="" className="aspect-square w-full rounded-lg object-cover ring-1 ring-border/30" loading="lazy" />
                ) : (
                  <div key={m.id} className="aspect-square w-full rounded-lg bg-muted/70 flex items-center justify-center">
                    <Camera className="h-3.5 w-3.5 text-muted-foreground/40" />
                  </div>
                )
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center py-6 text-muted-foreground/50">
              <CheckCircle2 className="h-6 w-6 mb-1.5" />
              <p className="text-xs">Tudo moderado ✨</p>
            </div>
          )}
          <Button variant="outline" size="sm" className="w-full h-8 text-xs" asChild>
            <Link to="/moderation"><Eye className="h-3 w-3 mr-1.5" />Moderar Agora</Link>
          </Button>
        </div>

        {/* Quick Actions + Alerts */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center gap-2 mb-3">
            <div className="rounded-md bg-emerald-500/10 p-1.5">
              <Zap className="h-3.5 w-3.5 text-emerald-500" />
            </div>
            <h3 className="text-sm font-semibold">Ações Rápidas</h3>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <Button variant="outline" size="sm" className="h-9 text-xs justify-start gap-1.5" asChild>
              <Link to="/events/create"><CalendarDays className="h-3.5 w-3.5 text-violet-500" />Novo Evento</Link>
            </Button>
            <Button variant="outline" size="sm" className="h-9 text-xs justify-start gap-1.5" asChild>
              <Link to="/moderation"><ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />Moderar</Link>
            </Button>
            <Button variant="outline" size="sm" className="h-9 text-xs justify-start gap-1.5" asChild>
              <Link to="/analytics"><TrendingUp className="h-3.5 w-3.5 text-blue-500" />Relatorios</Link>
            </Button>
            <Button variant="outline" size="sm" className="h-9 text-xs justify-start gap-1.5" asChild>
              <Link to="/settings"><Users2 className="h-3.5 w-3.5 text-amber-500" />Equipe</Link>
            </Button>
          </div>

          {/* Alerts */}
          {alerts.length > 0 && (
            <div className="mt-3 pt-3 border-t border-border/40">
              <div className="flex items-center gap-1.5 mb-2">
                <AlertTriangle className="h-3 w-3 text-amber-500" />
                <span className="text-[11px] font-semibold text-muted-foreground">Alertas</span>
              </div>
              <div className="space-y-1.5">
                {alerts.slice(0, 3).map((alert, i) => (
                  <div key={i} className="flex items-start gap-1.5 text-[11px] leading-relaxed">
                    {alert.type === 'warning' && <AlertTriangle className="h-3 w-3 text-amber-500 shrink-0 mt-0.5" />}
                    {alert.type === 'error' && <AlertCircle className="h-3 w-3 text-destructive shrink-0 mt-0.5" />}
                    {alert.type === 'info' && <Info className="h-3 w-3 text-blue-400 shrink-0 mt-0.5" />}
                    <span className="text-muted-foreground">{alert.message}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Top Partners */}
        <div className="glass rounded-xl p-4 sm:p-5 sm:col-span-2 lg:col-span-1">
          <div className="flex items-center gap-2 mb-3">
            <div className="rounded-md bg-indigo-500/10 p-1.5">
              <Users2 className="h-3.5 w-3.5 text-indigo-500" />
            </div>
            <h3 className="text-sm font-semibold">Parceiros por receita liquidada</h3>
          </div>
          <div className="space-y-2.5">
            {top_partners.length > 0 ? (
              top_partners.map((p, i) => (
                <div key={p.id} className="flex items-center gap-2.5">
                  <span className="text-[11px] font-bold text-muted-foreground/50 w-3 text-right tabular-nums">{i + 1}</span>
                  {p.logo_url ? (
                    <img src={p.logo_url} alt="" className="h-7 w-7 rounded-full object-cover ring-1 ring-border/50" loading="lazy" />
                  ) : (
                    <div className="h-7 w-7 rounded-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center text-xs font-bold text-primary">
                      {p.name.charAt(0)}
                    </div>
                  )}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate leading-tight">{p.name}</p>
                    <p className="text-[11px] text-muted-foreground">
                      {p.active_events} eventos ativos{p.revenue > 0 ? ` · R$ ${p.revenue.toLocaleString('pt-BR')}` : ''}
                    </p>
                    <p className="text-[10px] text-muted-foreground/80">
                      Conta {p.active_subscription_events} · Avulso {p.active_paid_events}
                      {(p.subscription_revenue > 0 || p.event_revenue > 0)
                        ? ` · Assinatura R$ ${p.subscription_revenue.toLocaleString('pt-BR')} · Evento R$ ${p.event_revenue.toLocaleString('pt-BR')}`
                        : ''}
                    </p>
                  </div>
                </div>
              ))
            ) : (
              <div className="flex flex-col items-center py-6 text-muted-foreground/50">
                <Users2 className="h-6 w-6 mb-1.5" />
                <p className="text-xs">Sem dados ainda</p>
              </div>
            )}
          </div>
        </div>
      </motion.div>
    </motion.div>
  );
}

import { motion } from 'framer-motion';
import {
  CalendarDays, Camera, CheckCircle, Gauge, Gamepad2, Globe, DollarSign, Users,
  TrendingUp, AlertTriangle, Clock, Zap,
} from 'lucide-react';
import { AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell, ResponsiveContainer, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';
import { StatsCard } from '@/shared/components/StatsCard';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';
import { mockDashboardStats, mockUploadsPerHour, mockEventsByType, mockEngagement, mockEvents, mockMedia, mockPartners } from '@/shared/mock/data';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { useTheme } from '@/app/providers/ThemeProvider';

const container = { hidden: {}, show: { transition: { staggerChildren: 0.04 } } };
const item = { hidden: { opacity: 0, y: 8 }, show: { opacity: 1, y: 0, transition: { duration: 0.3 } } };

// Theme-aware chart colors
function useChartStyles() {
  const { resolvedTheme } = useTheme();
  const isDark = resolvedTheme === 'dark';
  return {
    grid: isDark ? 'hsl(230, 12%, 18%)' : 'hsl(220, 13%, 91%)',
    text: isDark ? 'hsl(220, 10%, 55%)' : 'hsl(220, 10%, 46%)',
    tooltipBg: isDark ? 'hsl(230, 14%, 11%)' : 'hsl(0, 0%, 100%)',
    tooltipBorder: isDark ? 'hsl(230, 12%, 18%)' : 'hsl(220, 13%, 90%)',
    tooltipText: isDark ? 'hsl(220, 20%, 92%)' : 'hsl(230, 25%, 12%)',
    primary: isDark ? 'hsl(258, 70%, 58%)' : 'hsl(258, 65%, 52%)',
    accent: isDark ? 'hsl(215, 80%, 55%)' : 'hsl(215, 75%, 50%)',
    gradientOpacity: isDark ? 0.3 : 0.15,
  };
}

export default function DashboardPage() {
  const s = mockDashboardStats;
  const pendingMedia = mockMedia.filter(m => m.status === 'pending_moderation');
  const cs = useChartStyles();

  const tooltipStyle = {
    background: cs.tooltipBg,
    border: `1px solid ${cs.tooltipBorder}`,
    borderRadius: 8,
    fontSize: 12,
    color: cs.tooltipText,
    boxShadow: '0 4px 12px -2px rgb(0 0 0 / 0.1)',
  };

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="space-y-6">
      <PageHeader title="Dashboard" description="Visão geral da plataforma Evento Vivo" />

      {/* KPIs */}
      <motion.div variants={item} className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <StatsCard title="Eventos Ativos" value={s.activeEvents} icon={CalendarDays} change="+2 esta semana" changeType="positive" />
        <StatsCard title="Fotos Hoje" value={s.photosToday} icon={Camera} change="+18% vs ontem" changeType="positive" />
        <StatsCard title="Fotos Aprovadas" value={s.photosApproved} icon={CheckCircle} change={`${s.moderationRate}% taxa`} changeType="positive" />
        <StatsCard title="Taxa de Moderação" value={`${s.moderationRate}%`} icon={Gauge} />
        <StatsCard title="Partidas Jogadas" value={s.gamesPlayed} icon={Gamepad2} change="+34 hoje" changeType="positive" />
        <StatsCard title="Acessos ao Hub" value={s.hubAccesses.toLocaleString('pt-BR')} icon={Globe} change="+12% esta semana" changeType="positive" />
        <StatsCard title="Receita Estimada" value={`R$ ${s.estimatedRevenue.toLocaleString('pt-BR')}`} icon={DollarSign} change="+8% este mês" changeType="positive" />
        <StatsCard title="Parceiros Ativos" value={s.activePartners} icon={Users} />
      </motion.div>

      {/* Charts Row */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
        {/* Uploads per Hour */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <h3 className="text-sm font-semibold mb-4">Uploads por Hora</h3>
          <ResponsiveContainer width="100%" height={220}>
            <AreaChart data={mockUploadsPerHour}>
              <defs>
                <linearGradient id="uploadGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor={cs.primary} stopOpacity={cs.gradientOpacity} />
                  <stop offset="95%" stopColor={cs.primary} stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} />
              <XAxis dataKey="hour" tick={{ fill: cs.text, fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis tick={{ fill: cs.text, fontSize: 11 }} tickLine={false} axisLine={false} />
              <Tooltip contentStyle={tooltipStyle} />
              <Area type="monotone" dataKey="uploads" stroke={cs.primary} fill="url(#uploadGrad)" strokeWidth={2} />
            </AreaChart>
          </ResponsiveContainer>
        </div>

        {/* Events by Type */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <h3 className="text-sm font-semibold mb-4">Eventos por Tipo</h3>
          <div className="flex items-center gap-6">
            <ResponsiveContainer width="50%" height={200}>
              <PieChart>
                <Pie data={mockEventsByType} cx="50%" cy="50%" innerRadius={50} outerRadius={80} paddingAngle={3} dataKey="count" nameKey="type">
                  {mockEventsByType.map((entry, i) => (
                    <Cell key={i} fill={entry.fill} />
                  ))}
                </Pie>
                <Tooltip contentStyle={tooltipStyle} />
              </PieChart>
            </ResponsiveContainer>
            <div className="space-y-2">
              {mockEventsByType.map((e, i) => (
                <div key={i} className="flex items-center gap-2 text-sm">
                  <span className="h-2.5 w-2.5 rounded-full shrink-0" style={{ background: e.fill }} />
                  <span className="text-muted-foreground">{e.type}</span>
                  <span className="font-medium">{e.count}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </motion.div>

      {/* Engagement + Recent Events */}
      <motion.div variants={item} className="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
        {/* Module Engagement */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <h3 className="text-sm font-semibold mb-4">Engajamento por Módulo</h3>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={mockEngagement} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" stroke={cs.grid} horizontal={false} />
              <XAxis type="number" tick={{ fill: cs.text, fontSize: 11 }} domain={[0, 100]} />
              <YAxis type="category" dataKey="module" tick={{ fill: cs.text, fontSize: 12 }} width={40} />
              <Tooltip contentStyle={tooltipStyle} />
              <Bar dataKey="value" fill={cs.accent} radius={[0, 6, 6, 0]} barSize={20} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Recent Events */}
        <div className="glass rounded-xl p-4 sm:p-5 lg:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold">Eventos Recentes</h3>
            <Button variant="ghost" size="sm" asChild><Link to="/events">Ver todos</Link></Button>
          </div>
          <div className="space-y-1">
            {mockEvents.slice(0, 5).map(evt => (
              <Link
                key={evt.id}
                to={`/events/${evt.id}`}
                className="flex items-center gap-3 p-2.5 rounded-lg hover:bg-muted/50 transition-colors"
              >
                <img src={evt.coverUrl} alt={evt.name} className="h-10 w-14 rounded-md object-cover" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{evt.name}</p>
                  <p className="text-xs text-muted-foreground">{evt.organizationName} · {new Date(evt.date).toLocaleDateString('pt-BR')}</p>
                </div>
                <EventStatusBadge status={evt.status} />
                <span className="text-xs text-muted-foreground hidden sm:block">{evt.photosReceived} fotos</span>
              </Link>
            ))}
          </div>
        </div>
      </motion.div>

      {/* Moderation + Partners + Quick Actions */}
      <motion.div variants={item} className="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
        {/* Moderation Queue */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-sm font-semibold">Fila de Moderação</h3>
            <span className="text-xs font-medium text-warning">{pendingMedia.length} pendentes</span>
          </div>
          <div className="grid grid-cols-4 gap-2 mb-3">
            {pendingMedia.slice(0, 8).map(m => (
              <img key={m.id} src={m.thumbnailUrl} alt="" className="h-16 w-full rounded-md object-cover" />
            ))}
          </div>
          <Button variant="outline" size="sm" className="w-full" asChild><Link to="/moderation">Moderar Agora</Link></Button>
        </div>

        {/* Top Partners */}
        <div className="glass rounded-xl p-4 sm:p-5">
          <h3 className="text-sm font-semibold mb-4">Top Parceiros</h3>
          <div className="space-y-3">
            {mockPartners.slice(0, 4).map((p, i) => (
              <div key={p.id} className="flex items-center gap-3">
                <span className="text-xs font-bold text-muted-foreground w-4">{i + 1}</span>
                <span className="text-lg">{p.logo}</span>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{p.name}</p>
                  <p className="text-xs text-muted-foreground">{p.activeEvents} eventos · R$ {p.revenue.toLocaleString('pt-BR')}</p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Quick Actions + Alerts */}
        <div className="space-y-3 sm:space-y-4">
          <div className="glass rounded-xl p-4 sm:p-5">
            <h3 className="text-sm font-semibold mb-3">Ações Rápidas</h3>
            <div className="grid grid-cols-2 gap-2">
              <Button variant="outline" size="sm" asChild><Link to="/events/create"><Zap className="h-3.5 w-3.5 mr-1" /> Novo Evento</Link></Button>
              <Button variant="outline" size="sm" asChild><Link to="/moderation"><CheckCircle className="h-3.5 w-3.5 mr-1" /> Moderar</Link></Button>
              <Button variant="outline" size="sm" asChild><Link to="/analytics"><TrendingUp className="h-3.5 w-3.5 mr-1" /> Analytics</Link></Button>
              <Button variant="outline" size="sm" asChild><Link to="/partners"><Users className="h-3.5 w-3.5 mr-1" /> Parceiros</Link></Button>
            </div>
          </div>
          <div className="glass rounded-xl p-4 sm:p-5">
            <h3 className="text-sm font-semibold mb-3">Alertas</h3>
            <div className="space-y-2.5">
              <div className="flex items-start gap-2 text-xs">
                <AlertTriangle className="h-3.5 w-3.5 text-warning shrink-0 mt-0.5" />
                <span className="text-muted-foreground">Evento "Lançamento XYZ" atingiu 90% do limite de fotos</span>
              </div>
              <div className="flex items-start gap-2 text-xs">
                <Clock className="h-3.5 w-3.5 text-accent shrink-0 mt-0.5" />
                <span className="text-muted-foreground">3 fotos com erro de processamento</span>
              </div>
            </div>
          </div>
        </div>
      </motion.div>
    </motion.div>
  );
}

import { motion } from 'framer-motion';
import { BarChart3, TrendingUp, Eye, Camera, Gamepad2 } from 'lucide-react';
import { AreaChart, Area, BarChart, Bar, LineChart, Line, ResponsiveContainer, XAxis, YAxis, Tooltip, CartesianGrid, Legend } from 'recharts';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { mockAnalyticsData, mockEvents } from '@/shared/mock/data';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const tooltipStyle = { background: 'hsl(230, 14%, 11%)', border: '1px solid hsl(230, 12%, 18%)', borderRadius: 8, fontSize: 12 };
const tickStyle = { fill: 'hsl(220, 10%, 55%)', fontSize: 11 };

export default function AnalyticsPage() {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Analytics"
        description="Métricas detalhadas da plataforma"
        actions={
          <Select defaultValue="30d">
            <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="7d">Últimos 7 dias</SelectItem>
              <SelectItem value="30d">Últimos 30 dias</SelectItem>
              <SelectItem value="90d">Últimos 90 dias</SelectItem>
            </SelectContent>
          </Select>
        }
      />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Uploads Total" value="4.823" icon={Camera} change="+15% vs período anterior" changeType="positive" />
        <StatsCard title="Acessos ao Hub" value="12.450" icon={Eye} change="+22%" changeType="positive" />
        <StatsCard title="Visitas à Galeria" value="8.320" icon={BarChart3} change="+8%" changeType="positive" />
        <StatsCard title="Partidas Jogadas" value="1.560" icon={Gamepad2} change="+45%" changeType="positive" />
      </div>

      {/* Uploads over time */}
      <div className="glass rounded-xl p-5">
        <h3 className="text-sm font-semibold mb-4">Uploads por Período</h3>
        <ResponsiveContainer width="100%" height={280}>
          <AreaChart data={mockAnalyticsData}>
            <defs>
              <linearGradient id="grad1" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(258, 70%, 58%)" stopOpacity={0.3} />
                <stop offset="95%" stopColor="hsl(258, 70%, 58%)" stopOpacity={0} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" stroke="hsl(230, 12%, 18%)" />
            <XAxis dataKey="date" tick={tickStyle} tickFormatter={v => new Date(v).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} />
            <YAxis tick={tickStyle} />
            <Tooltip contentStyle={tooltipStyle} />
            <Area type="monotone" dataKey="uploads" stroke="hsl(258, 70%, 58%)" fill="url(#grad1)" strokeWidth={2} />
          </AreaChart>
        </ResponsiveContainer>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Hub + Gallery */}
        <div className="glass rounded-xl p-5">
          <h3 className="text-sm font-semibold mb-4">Acessos: Hub vs Galeria</h3>
          <ResponsiveContainer width="100%" height={250}>
            <LineChart data={mockAnalyticsData}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(230, 12%, 18%)" />
              <XAxis dataKey="date" tick={tickStyle} tickFormatter={v => new Date(v).toLocaleDateString('pt-BR', { day: '2-digit' })} />
              <YAxis tick={tickStyle} />
              <Tooltip contentStyle={tooltipStyle} />
              <Legend />
              <Line type="monotone" dataKey="hubVisits" name="Hub" stroke="hsl(258, 70%, 58%)" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="galleryViews" name="Galeria" stroke="hsl(215, 80%, 55%)" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>

        {/* Games */}
        <div className="glass rounded-xl p-5">
          <h3 className="text-sm font-semibold mb-4">Partidas Jogadas</h3>
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={mockAnalyticsData}>
              <CartesianGrid strokeDasharray="3 3" stroke="hsl(230, 12%, 18%)" />
              <XAxis dataKey="date" tick={tickStyle} tickFormatter={v => new Date(v).toLocaleDateString('pt-BR', { day: '2-digit' })} />
              <YAxis tick={tickStyle} />
              <Tooltip contentStyle={tooltipStyle} />
              <Bar dataKey="gamesPlayed" name="Partidas" fill="hsl(142, 70%, 45%)" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Top Events */}
      <div className="glass rounded-xl p-5">
        <h3 className="text-sm font-semibold mb-4">Eventos com Melhor Performance</h3>
        <div className="space-y-3">
          {mockEvents.filter(e => e.status === 'active' || e.status === 'finished').slice(0, 5).map((evt, i) => (
            <div key={evt.id} className="flex items-center gap-3">
              <span className="text-sm font-bold text-muted-foreground w-5">#{i + 1}</span>
              <img src={evt.coverUrl} alt="" className="h-8 w-12 rounded-md object-cover" />
              <div className="flex-1">
                <p className="text-sm font-medium">{evt.name}</p>
                <p className="text-xs text-muted-foreground">{evt.photosReceived} fotos · {evt.modulesActive.length} módulos</p>
              </div>
              <div className="flex items-center gap-1 text-success text-xs font-medium">
                <TrendingUp className="h-3.5 w-3.5" /> {Math.floor(Math.random() * 30 + 10)}%
              </div>
            </div>
          ))}
        </div>
      </div>
    </motion.div>
  );
}

import { motion } from 'framer-motion';
import { Users, TrendingUp, DollarSign, MoreHorizontal, Eye } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { mockPartners } from '@/shared/mock/data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function PartnersPage() {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Parceiros" description="Gerencie parceiros profissionais da plataforma" />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Total de Parceiros" value={mockPartners.length} icon={Users} />
        <StatsCard title="Ativos" value={mockPartners.filter(p => p.status === 'active').length} icon={TrendingUp} changeType="positive" />
        <StatsCard title="Receita Total" value={`R$ ${mockPartners.reduce((a, p) => a + p.revenue, 0).toLocaleString('pt-BR')}`} icon={DollarSign} />
        <StatsCard title="Eventos Ativos" value={mockPartners.reduce((a, p) => a + p.activeEvents, 0)} icon={Users} />
      </div>

      <div className="glass rounded-xl overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="border-border/50">
              <TableHead>Parceiro</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead>Plano</TableHead>
              <TableHead className="hidden md:table-cell">Eventos</TableHead>
              <TableHead className="hidden md:table-cell">Receita</TableHead>
              <TableHead className="hidden md:table-cell">Equipe</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {mockPartners.map(p => (
              <TableRow key={p.id} className="border-border/30">
                <TableCell>
                  <div className="flex items-center gap-2">
                    <span className="text-lg">{p.logo}</span>
                    <span className="font-medium text-sm">{p.name}</span>
                  </div>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">{p.type}</TableCell>
                <TableCell><Badge variant="outline" className="text-xs">{p.plan}</Badge></TableCell>
                <TableCell className="hidden md:table-cell text-sm">{p.activeEvents}</TableCell>
                <TableCell className="hidden md:table-cell text-sm">R$ {p.revenue.toLocaleString('pt-BR')}</TableCell>
                <TableCell className="hidden md:table-cell text-sm">{p.teamSize}</TableCell>
                <TableCell>
                  <Badge variant="outline" className={`text-xs ${p.status === 'active' ? 'text-success border-success/20 bg-success/10' : p.status === 'trial' ? 'text-warning border-warning/20 bg-warning/10' : 'text-muted-foreground'}`}>
                    {p.status === 'active' ? 'Ativo' : p.status === 'trial' ? 'Trial' : 'Inativo'}
                  </Badge>
                </TableCell>
                <TableCell><Button variant="ghost" size="icon"><MoreHorizontal className="h-4 w-4" /></Button></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </motion.div>
  );
}

import { motion } from 'framer-motion';
import { UserCheck, MoreHorizontal } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { mockClients } from '@/shared/mock/data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export default function ClientsPage() {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Clientes" description="Visualize os clientes finais da plataforma" />
      <div className="glass rounded-xl overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="border-border/50">
              <TableHead>Cliente</TableHead>
              <TableHead>Tipo</TableHead>
              <TableHead className="hidden md:table-cell">Parceiro</TableHead>
              <TableHead className="hidden md:table-cell">Eventos</TableHead>
              <TableHead>Plano</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {mockClients.map(c => (
              <TableRow key={c.id} className="border-border/30">
                <TableCell className="font-medium text-sm">{c.name}</TableCell>
                <TableCell className="text-sm text-muted-foreground">{c.type}</TableCell>
                <TableCell className="hidden md:table-cell text-sm text-muted-foreground">{c.partnerName}</TableCell>
                <TableCell className="hidden md:table-cell text-sm">{c.eventsCount}</TableCell>
                <TableCell><Badge variant="outline" className="text-xs">{c.plan}</Badge></TableCell>
                <TableCell>
                  <Badge variant="outline" className={`text-xs ${c.status === 'active' ? 'text-success border-success/20 bg-success/10' : 'text-muted-foreground'}`}>
                    {c.status === 'active' ? 'Ativo' : 'Inativo'}
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

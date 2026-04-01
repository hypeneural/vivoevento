import { useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Plus, Search, Filter, MoreHorizontal, Eye, Edit, Copy, Archive, Globe, BarChart3 } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';
import { mockEvents } from '@/shared/mock/data';
import { EVENT_TYPE_LABELS, EVENT_STATUS_LABELS } from '@/shared/types';
import type { EventStatus, EventType } from '@/shared/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/use-toast';

export default function EventsListPage() {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const { toast } = useToast();

  const filtered = useMemo(() => {
    return mockEvents.filter(e => {
      if (search && !e.name.toLowerCase().includes(search.toLowerCase())) return false;
      if (statusFilter !== 'all' && e.status !== statusFilter) return false;
      if (typeFilter !== 'all' && e.type !== typeFilter) return false;
      return true;
    });
  }, [search, statusFilter, typeFilter]);

  const handleAction = (action: string, name: string) => {
    toast({ title: `${action}`, description: `Ação "${action}" executada para "${name}" (mock)` });
  };

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Eventos"
        description={`${mockEvents.length} eventos cadastrados`}
        actions={<Button asChild className="gradient-primary border-0"><Link to="/events/create"><Plus className="h-4 w-4 mr-1" /> Novo Evento</Link></Button>}
      />

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input placeholder="Buscar eventos..." value={search} onChange={e => setSearch(e.target.value)} className="pl-9" />
        </div>
        <Select value={statusFilter} onValueChange={setStatusFilter}>
          <SelectTrigger className="w-full sm:w-40"><SelectValue placeholder="Status" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os status</SelectItem>
            {Object.entries(EVENT_STATUS_LABELS).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
          </SelectContent>
        </Select>
        <Select value={typeFilter} onValueChange={setTypeFilter}>
          <SelectTrigger className="w-full sm:w-40"><SelectValue placeholder="Tipo" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os tipos</SelectItem>
            {Object.entries(EVENT_TYPE_LABELS).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      <div className="glass rounded-xl overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow className="border-border/50">
              <TableHead>Evento</TableHead>
              <TableHead className="hidden md:table-cell">Tipo</TableHead>
              <TableHead className="hidden sm:table-cell">Data</TableHead>
              <TableHead className="hidden lg:table-cell">Organização</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="hidden md:table-cell">Fotos</TableHead>
              <TableHead className="hidden lg:table-cell">Módulos</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filtered.map(evt => (
              <TableRow key={evt.id} className="border-border/30 hover:bg-muted/30">
                <TableCell>
                  <Link to={`/events/${evt.id}`} className="flex items-center gap-3">
                    <img src={evt.coverUrl} alt="" className="h-9 w-14 rounded-md object-cover hidden sm:block" />
                    <span className="font-medium text-sm">{evt.name}</span>
                  </Link>
                </TableCell>
                <TableCell className="hidden md:table-cell text-sm text-muted-foreground">{EVENT_TYPE_LABELS[evt.type]}</TableCell>
                <TableCell className="hidden sm:table-cell text-sm text-muted-foreground">{new Date(evt.date).toLocaleDateString('pt-BR')}</TableCell>
                <TableCell className="hidden lg:table-cell text-sm text-muted-foreground">{evt.organizationName}</TableCell>
                <TableCell><EventStatusBadge status={evt.status} /></TableCell>
                <TableCell className="hidden md:table-cell text-sm">{evt.photosReceived}</TableCell>
                <TableCell className="hidden lg:table-cell">
                  <div className="flex gap-1">
                    {evt.modulesActive.map(m => <Badge key={m} variant="outline" className="text-[10px]">{m}</Badge>)}
                  </div>
                </TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild><Button variant="ghost" size="icon"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem asChild><Link to={`/events/${evt.id}`}><Eye className="h-4 w-4 mr-2" /> Ver Detalhes</Link></DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleAction('Editar', evt.name)}><Edit className="h-4 w-4 mr-2" /> Editar</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleAction('Duplicar', evt.name)}><Copy className="h-4 w-4 mr-2" /> Duplicar</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleAction('Abrir Hub', evt.name)}><Globe className="h-4 w-4 mr-2" /> Abrir Hub</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleAction('Analytics', evt.name)}><BarChart3 className="h-4 w-4 mr-2" /> Analytics</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => handleAction('Arquivar', evt.name)}><Archive className="h-4 w-4 mr-2" /> Arquivar</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </motion.div>
  );
}

import { useState, useMemo } from 'react';
import { motion } from 'framer-motion';
import { Search, LayoutGrid, List, Eye, Check, X, Star } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { MediaStatusBadge, ChannelBadge } from '@/shared/components/StatusBadges';
import { mockMedia } from '@/shared/mock/data';
import { MEDIA_STATUS_LABELS, CHANNEL_LABELS } from '@/shared/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';

export default function MediaPage() {
  const [view, setView] = useState<'grid' | 'list'>('grid');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [channelFilter, setChannelFilter] = useState('all');
  const { toast } = useToast();

  const filtered = useMemo(() => {
    return mockMedia.filter(m => {
      if (search && !m.senderName.toLowerCase().includes(search.toLowerCase()) && !m.eventName.toLowerCase().includes(search.toLowerCase())) return false;
      if (statusFilter !== 'all' && m.status !== statusFilter) return false;
      if (channelFilter !== 'all' && m.channel !== channelFilter) return false;
      return true;
    });
  }, [search, statusFilter, channelFilter]);

  const act = (action: string) => toast({ title: action, description: 'Ação executada (mock)' });

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Mídias" description={`${mockMedia.length} mídias recebidas`} />

      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input placeholder="Buscar por remetente ou evento..." value={search} onChange={e => setSearch(e.target.value)} className="pl-9" />
        </div>
        <Select value={statusFilter} onValueChange={setStatusFilter}>
          <SelectTrigger className="w-full sm:w-44"><SelectValue placeholder="Status" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os status</SelectItem>
            {Object.entries(MEDIA_STATUS_LABELS).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
          </SelectContent>
        </Select>
        <Select value={channelFilter} onValueChange={setChannelFilter}>
          <SelectTrigger className="w-full sm:w-40"><SelectValue placeholder="Canal" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os canais</SelectItem>
            {Object.entries(CHANNEL_LABELS).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
          </SelectContent>
        </Select>
        <div className="flex gap-1">
          <Button variant={view === 'grid' ? 'secondary' : 'ghost'} size="icon" onClick={() => setView('grid')}><LayoutGrid className="h-4 w-4" /></Button>
          <Button variant={view === 'list' ? 'secondary' : 'ghost'} size="icon" onClick={() => setView('list')}><List className="h-4 w-4" /></Button>
        </div>
      </div>

      {view === 'grid' ? (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {filtered.map(m => (
            <div key={m.id} className="glass rounded-lg overflow-hidden card-hover group">
              <div className="relative">
                <img src={m.thumbnailUrl} alt="" className="h-32 w-full object-cover" />
                <div className="absolute inset-0 bg-background/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                  <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => act('Visualizar')}><Eye className="h-4 w-4" /></Button>
                  <Button variant="ghost" size="icon" className="h-8 w-8 text-success" onClick={() => act('Aprovar')}><Check className="h-4 w-4" /></Button>
                  <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive" onClick={() => act('Rejeitar')}><X className="h-4 w-4" /></Button>
                </div>
              </div>
              <div className="p-2.5 space-y-1">
                <div className="flex items-center justify-between">
                  <ChannelBadge channel={m.channel} />
                  <MediaStatusBadge status={m.status} />
                </div>
                <p className="text-xs text-muted-foreground truncate">{m.senderName} · {m.eventName}</p>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="glass rounded-xl divide-y divide-border/30">
          {filtered.map(m => (
            <div key={m.id} className="flex items-center gap-3 p-3 hover:bg-muted/30 transition-colors">
              <img src={m.thumbnailUrl} alt="" className="h-12 w-12 rounded-md object-cover" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium truncate">{m.eventName}</p>
                <p className="text-xs text-muted-foreground">{m.senderName} · {new Date(m.createdAt).toLocaleString('pt-BR')}</p>
              </div>
              <ChannelBadge channel={m.channel} />
              <MediaStatusBadge status={m.status} />
              <div className="flex gap-1">
                <Button variant="ghost" size="icon" className="h-7 w-7 text-success" onClick={() => act('Aprovar')}><Check className="h-3.5 w-3.5" /></Button>
                <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={() => act('Rejeitar')}><X className="h-3.5 w-3.5" /></Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </motion.div>
  );
}

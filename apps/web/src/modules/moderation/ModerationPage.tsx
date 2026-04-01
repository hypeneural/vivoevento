import { useState, useMemo } from 'react';
import { motion } from 'framer-motion';
import { Check, X, Star, AlertTriangle, Clock, CheckCircle, XCircle, Zap } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { ChannelBadge } from '@/shared/components/StatusBadges';
import { mockMedia, mockEvents } from '@/shared/mock/data';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';

export default function ModerationPage() {
  const { toast } = useToast();
  const [eventFilter, setEventFilter] = useState('all');
  const [selected, setSelected] = useState<Set<string>>(new Set());

  const pending = useMemo(() => {
    let items = mockMedia.filter(m => m.status === 'pending_moderation' || m.status === 'received');
    if (eventFilter !== 'all') items = items.filter(m => m.eventId === eventFilter);
    return items;
  }, [eventFilter]);

  const toggleSelect = (id: string) => {
    setSelected(prev => {
      const n = new Set(prev);
      n.has(id) ? n.delete(id) : n.add(id);
      return n;
    });
  };

  const act = (action: string, count?: number) => {
    toast({ title: action, description: `${count || 1} mídia(s) — ação executada (mock)` });
    setSelected(new Set());
  };

  const approvedToday = mockMedia.filter(m => m.status === 'approved').length;
  const rejectedToday = mockMedia.filter(m => m.status === 'rejected').length;

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Moderação" description="Gerencie a fila de moderação de mídias" />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Pendentes" value={pending.length} icon={Clock} />
        <StatsCard title="Aprovadas Hoje" value={approvedToday} icon={CheckCircle} changeType="positive" />
        <StatsCard title="Rejeitadas Hoje" value={rejectedToday} icon={XCircle} />
        <StatsCard title="Tempo Médio" value="12s" icon={Zap} description="por moderação" />
      </div>

      {/* Filters & Bulk Actions */}
      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <Select value={eventFilter} onValueChange={setEventFilter}>
          <SelectTrigger className="w-60"><SelectValue placeholder="Filtrar por evento" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Todos os eventos</SelectItem>
            {mockEvents.map(e => <SelectItem key={e.id} value={e.id}>{e.name}</SelectItem>)}
          </SelectContent>
        </Select>
        {selected.size > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">{selected.size} selecionadas</span>
            <Button size="sm" variant="outline" className="text-success border-success/30" onClick={() => act('Aprovar em massa', selected.size)}>
              <Check className="h-3.5 w-3.5 mr-1" /> Aprovar
            </Button>
            <Button size="sm" variant="outline" className="text-destructive border-destructive/30" onClick={() => act('Rejeitar em massa', selected.size)}>
              <X className="h-3.5 w-3.5 mr-1" /> Rejeitar
            </Button>
          </div>
        )}
      </div>

      {/* Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        {pending.map(m => (
          <motion.div
            key={m.id}
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className={`glass rounded-lg overflow-hidden cursor-pointer transition-all ${selected.has(m.id) ? 'ring-2 ring-primary' : ''}`}
            onClick={() => toggleSelect(m.id)}
          >
            <div className="relative">
              <img src={m.thumbnailUrl} alt="" className="h-36 w-full object-cover" />
              {selected.has(m.id) && (
                <div className="absolute top-2 right-2 h-5 w-5 rounded-full bg-primary flex items-center justify-center">
                  <Check className="h-3 w-3 text-primary-foreground" />
                </div>
              )}
            </div>
            <div className="p-2 space-y-1.5">
              <div className="flex items-center justify-between">
                <ChannelBadge channel={m.channel} />
                <span className="text-[10px] text-muted-foreground">{new Date(m.createdAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</span>
              </div>
              <p className="text-xs truncate">{m.senderName}</p>
              <div className="flex gap-1">
                <Button variant="ghost" size="icon" className="h-7 w-7 text-success" onClick={e => { e.stopPropagation(); act('Aprovada'); }}>
                  <Check className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive" onClick={e => { e.stopPropagation(); act('Rejeitada'); }}>
                  <X className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon" className="h-7 w-7 text-warning" onClick={e => { e.stopPropagation(); act('Destaque'); }}>
                  <Star className="h-3.5 w-3.5" />
                </Button>
                <Button variant="ghost" size="icon" className="h-7 w-7 text-warning" onClick={e => { e.stopPropagation(); act('Sensível'); }}>
                  <AlertTriangle className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          </motion.div>
        ))}
      </div>
    </motion.div>
  );
}

import { useState, useMemo } from 'react';
import { motion } from 'framer-motion';
import { ClipboardList, Search } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { mockAudit } from '@/shared/mock/data';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

export default function AuditPage() {
  const [search, setSearch] = useState('');

  const filtered = useMemo(() => {
    if (!search) return mockAudit;
    const s = search.toLowerCase();
    return mockAudit.filter(a => a.userName.toLowerCase().includes(s) || a.action.toLowerCase().includes(s) || a.entityName.toLowerCase().includes(s));
  }, [search]);

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Auditoria" description="Log de atividades da plataforma" />

      <div className="relative max-w-md">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input placeholder="Buscar atividades..." value={search} onChange={e => setSearch(e.target.value)} className="pl-9" />
      </div>

      <div className="glass rounded-xl divide-y divide-border/30">
        {filtered.map(a => (
          <div key={a.id} className="flex items-start gap-3 p-4 hover:bg-muted/20 transition-colors">
            <div className="h-8 w-8 rounded-full gradient-primary flex items-center justify-center text-xs font-bold text-primary-foreground shrink-0 mt-0.5">
              {a.userName.split(' ').map(n => n[0]).join('').slice(0, 2)}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm">
                <span className="font-medium">{a.userName}</span>{' '}
                <span className="text-muted-foreground">{a.action}</span>{' '}
                <span className="font-medium">{a.entityName}</span>
              </p>
              <div className="flex items-center gap-2 mt-1">
                <Badge variant="outline" className="text-[10px]">{a.entityType}</Badge>
                {a.eventName && <span className="text-xs text-muted-foreground">em {a.eventName}</span>}
              </div>
            </div>
            <span className="text-xs text-muted-foreground shrink-0">
              {new Date(a.createdAt).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
            </span>
          </div>
        ))}
      </div>
    </motion.div>
  );
}

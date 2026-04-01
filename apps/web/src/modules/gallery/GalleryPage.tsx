import { useState } from 'react';
import { motion } from 'framer-motion';
import { Image, Star, Eye, EyeOff, Download, LayoutGrid, Grid3X3 } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { mockMedia } from '@/shared/mock/data';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';

export default function GalleryPage() {
  const { toast } = useToast();
  const published = mockMedia.filter(m => m.status === 'published' || m.status === 'approved');
  const [previewMode, setPreviewMode] = useState(false);

  const act = (a: string) => toast({ title: a, description: 'Ação executada (mock)' });

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Galeria"
        description="Gerencie a galeria pública do evento"
        actions={
          <Button variant={previewMode ? 'default' : 'outline'} size="sm" onClick={() => setPreviewMode(!previewMode)}>
            <Eye className="h-4 w-4 mr-1" /> {previewMode ? 'Modo Admin' : 'Preview Público'}
          </Button>
        }
      />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Total de Mídias" value={published.length} icon={Image} />
        <StatsCard title="Publicadas" value={published.filter(m => m.status === 'published').length} icon={Eye} />
        <StatsCard title="Destaques" value={5} icon={Star} />
        <StatsCard title="Downloads" value={234} icon={Download} />
      </div>

      {previewMode ? (
        <div className="glass rounded-xl p-6">
          <div className="text-center mb-6">
            <h2 className="text-xl font-bold gradient-text">Galeria ao Vivo</h2>
            <p className="text-sm text-muted-foreground">Compartilhe seus melhores momentos</p>
          </div>
          <div className="columns-2 md:columns-3 lg:columns-4 gap-3 space-y-3">
            {published.map(m => (
              <img key={m.id} src={m.thumbnailUrl} alt="" className="w-full rounded-lg break-inside-avoid" />
            ))}
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {published.map(m => (
            <div key={m.id} className="glass rounded-lg overflow-hidden group card-hover">
              <div className="relative">
                <img src={m.thumbnailUrl} alt="" className="h-32 w-full object-cover" />
                <div className="absolute inset-0 bg-background/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1">
                  <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => act('Destaque')}><Star className="h-3.5 w-3.5" /></Button>
                  <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => act('Esconder')}><EyeOff className="h-3.5 w-3.5" /></Button>
                </div>
              </div>
              <div className="p-2">
                <p className="text-xs text-muted-foreground truncate">{m.senderName}</p>
              </div>
            </div>
          ))}
        </div>
      )}
    </motion.div>
  );
}

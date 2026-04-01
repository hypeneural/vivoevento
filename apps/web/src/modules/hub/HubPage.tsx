import { useState } from 'react';
import { motion } from 'framer-motion';
import { Globe, Image, Camera, Gamepad2, Monitor, Link2, ExternalLink, Smartphone, MonitorSmartphone, GripVertical } from 'lucide-react';
import { PageHeader } from '@/shared/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { mockMedia } from '@/shared/mock/data';

export default function HubPage() {
  const [previewDevice, setPreviewDevice] = useState<'mobile' | 'desktop'>('mobile');
  const [buttons, setButtons] = useState({ gallery: true, upload: true, play: true, wall: false });

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Hub do Evento"
        description="Configure a página oficial do evento"
        actions={<Button variant="outline" size="sm"><ExternalLink className="h-4 w-4 mr-1" /> Abrir Hub</Button>}
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Config */}
        <div className="space-y-4">
          <div className="glass rounded-xl p-5 space-y-4">
            <h3 className="text-sm font-semibold">Conteúdo</h3>
            <div>
              <Label>Título do Hub</Label>
              <Input defaultValue="Casamento Ana & Pedro" className="mt-1.5" />
            </div>
            <div>
              <Label>Subtítulo</Label>
              <Input defaultValue="15 de Abril de 2026 · Espaço Villa Real" className="mt-1.5" />
            </div>
            <div>
              <Label>Mensagem de Boas-vindas</Label>
              <Textarea defaultValue="Bem-vindo ao nosso casamento! Compartilhe seus melhores momentos conosco." className="mt-1.5" rows={3} />
            </div>
          </div>

          <div className="glass rounded-xl p-5 space-y-3">
            <h3 className="text-sm font-semibold">Botões de Ação</h3>
            {[
              { key: 'gallery' as const, label: 'Ver Galeria', icon: Image },
              { key: 'upload' as const, label: 'Enviar Fotos', icon: Camera },
              { key: 'play' as const, label: 'Jogar', icon: Gamepad2 },
              { key: 'wall' as const, label: 'Ver Wall', icon: Monitor },
            ].map(b => (
              <div key={b.key} className="flex items-center justify-between p-2.5 rounded-lg bg-muted/30">
                <div className="flex items-center gap-2">
                  <GripVertical className="h-4 w-4 text-muted-foreground" />
                  <b.icon className="h-4 w-4 text-primary" />
                  <span className="text-sm">{b.label}</span>
                </div>
                <Switch checked={buttons[b.key]} onCheckedChange={v => setButtons(prev => ({ ...prev, [b.key]: v }))} />
              </div>
            ))}
          </div>

          <div className="glass rounded-xl p-5 space-y-3">
            <h3 className="text-sm font-semibold">Patrocinadores & Links</h3>
            <div>
              <Label>Link Externo</Label>
              <Input placeholder="https://..." className="mt-1.5" />
            </div>
            <div>
              <Label>Logo do Patrocinador</Label>
              <div className="mt-1.5 border-2 border-dashed border-border rounded-lg p-6 text-center text-xs text-muted-foreground">
                Arraste ou clique para enviar
              </div>
            </div>
          </div>
        </div>

        {/* Preview */}
        <div className="space-y-3">
          <div className="flex gap-2 justify-end">
            <Button variant={previewDevice === 'mobile' ? 'secondary' : 'ghost'} size="sm" onClick={() => setPreviewDevice('mobile')}>
              <Smartphone className="h-4 w-4 mr-1" /> Mobile
            </Button>
            <Button variant={previewDevice === 'desktop' ? 'secondary' : 'ghost'} size="sm" onClick={() => setPreviewDevice('desktop')}>
              <MonitorSmartphone className="h-4 w-4 mr-1" /> Desktop
            </Button>
          </div>
          <div className={`glass rounded-2xl overflow-hidden mx-auto ${previewDevice === 'mobile' ? 'max-w-[320px]' : 'max-w-full'}`}>
            {/* Mock Hub Preview */}
            <div className="gradient-primary h-40 flex items-end p-4">
              <div>
                <h2 className="text-lg font-bold text-primary-foreground">Casamento Ana & Pedro</h2>
                <p className="text-xs text-primary-foreground/80">15 de Abril de 2026 · Espaço Villa Real</p>
              </div>
            </div>
            <div className="p-4 space-y-3">
              <p className="text-sm text-muted-foreground">Bem-vindo ao nosso casamento! Compartilhe seus melhores momentos conosco.</p>
              <div className="space-y-2">
                {buttons.gallery && <Button variant="outline" className="w-full justify-start"><Image className="h-4 w-4 mr-2" /> Ver Galeria</Button>}
                {buttons.upload && <Button className="w-full justify-start gradient-primary border-0"><Camera className="h-4 w-4 mr-2" /> Enviar Fotos</Button>}
                {buttons.play && <Button variant="outline" className="w-full justify-start"><Gamepad2 className="h-4 w-4 mr-2" /> Jogar</Button>}
                {buttons.wall && <Button variant="outline" className="w-full justify-start"><Monitor className="h-4 w-4 mr-2" /> Ver Wall</Button>}
              </div>
              <div className="grid grid-cols-3 gap-1 pt-2">
                {mockMedia.slice(0, 6).map(m => (
                  <img key={m.id} src={m.thumbnailUrl} alt="" className="h-20 rounded-md object-cover" />
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}

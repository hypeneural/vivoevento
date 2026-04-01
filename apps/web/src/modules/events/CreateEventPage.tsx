import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { PageHeader } from '@/shared/components/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useToast } from '@/hooks/use-toast';
import { CalendarDays, Monitor, Gamepad2, Globe, Image, Save, Eye } from 'lucide-react';

export default function CreateEventPage() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [modules, setModules] = useState({ live: true, wall: false, play: false, hub: false });
  const [name, setName] = useState('');

  const handleSave = () => {
    toast({ title: 'Evento criado com sucesso!', description: `"${name || 'Novo Evento'}" foi criado (mock)` });
    navigate('/events');
  };

  const moduleItems = [
    { key: 'live' as const, label: 'Live Gallery', icon: Image, desc: 'Galeria ao vivo com fotos dos convidados' },
    { key: 'wall' as const, label: 'Wall / Telão', icon: Monitor, desc: 'Slideshow para exibição no telão' },
    { key: 'play' as const, label: 'Play / Minigames', icon: Gamepad2, desc: 'Jogos interativos com fotos do evento' },
    { key: 'hub' as const, label: 'Hub do Evento', icon: Globe, desc: 'Página oficial do evento' },
  ];

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6 max-w-4xl">
      <PageHeader
        title="Criar Evento"
        description="Configure um novo evento na plataforma"
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => navigate('/events')}>Cancelar</Button>
            <Button className="gradient-primary border-0" onClick={handleSave}><Save className="h-4 w-4 mr-1" /> Salvar Evento</Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form */}
        <div className="lg:col-span-2 space-y-6">
          {/* Basic Info */}
          <div className="glass rounded-xl p-6 space-y-4">
            <h3 className="text-sm font-semibold">Informações Básicas</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="sm:col-span-2">
                <Label>Nome do Evento</Label>
                <Input value={name} onChange={e => setName(e.target.value)} placeholder="Ex: Casamento Ana & Pedro" className="mt-1.5" />
              </div>
              <div>
                <Label>Tipo</Label>
                <Select><SelectTrigger className="mt-1.5"><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="wedding">Casamento</SelectItem>
                    <SelectItem value="corporate">Corporativo</SelectItem>
                    <SelectItem value="birthday">Aniversário</SelectItem>
                    <SelectItem value="conference">Conferência</SelectItem>
                    <SelectItem value="party">Festa</SelectItem>
                    <SelectItem value="festival">Festival</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Data</Label>
                <Input type="date" className="mt-1.5" />
              </div>
              <div>
                <Label>Local</Label>
                <Input placeholder="Ex: Espaço Villa Real, SP" className="mt-1.5" />
              </div>
              <div>
                <Label>Responsável</Label>
                <Input placeholder="Nome do responsável" className="mt-1.5" />
              </div>
              <div className="sm:col-span-2">
                <Label>Descrição Curta</Label>
                <Textarea placeholder="Descreva brevemente o evento..." className="mt-1.5" rows={3} />
              </div>
            </div>
          </div>

          {/* Branding */}
          <div className="glass rounded-xl p-6 space-y-4">
            <h3 className="text-sm font-semibold">Branding & Aparência</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <Label>Slug / URL Pública</Label>
                <Input placeholder="meu-evento" className="mt-1.5" />
              </div>
              <div>
                <Label>Cor Principal</Label>
                <div className="flex gap-2 mt-1.5">
                  <Input type="color" defaultValue="#7c3aed" className="w-12 h-9 p-1 cursor-pointer" />
                  <Input defaultValue="#7c3aed" className="flex-1" />
                </div>
              </div>
              <div className="sm:col-span-2">
                <Label>Imagem de Capa</Label>
                <div className="mt-1.5 border-2 border-dashed border-border rounded-lg p-8 text-center text-sm text-muted-foreground cursor-pointer hover:border-primary/50 transition-colors">
                  <Image className="h-8 w-8 mx-auto mb-2 text-muted-foreground" />
                  Clique para enviar ou arraste uma imagem
                </div>
              </div>
            </div>
          </div>

          {/* Modules */}
          <div className="glass rounded-xl p-6 space-y-4">
            <h3 className="text-sm font-semibold">Módulos Ativos</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {moduleItems.map(m => (
                <div key={m.key} className={`flex items-center gap-4 p-4 rounded-lg border transition-colors ${modules[m.key] ? 'border-primary/30 bg-primary/5' : 'border-border'}`}>
                  <div className="rounded-lg bg-primary/10 p-2"><m.icon className="h-5 w-5 text-primary" /></div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">{m.label}</p>
                    <p className="text-xs text-muted-foreground">{m.desc}</p>
                  </div>
                  <Switch checked={modules[m.key]} onCheckedChange={v => setModules(prev => ({ ...prev, [m.key]: v }))} />
                </div>
              ))}
            </div>
          </div>

          {/* Privacy */}
          <div className="glass rounded-xl p-6 space-y-4">
            <h3 className="text-sm font-semibold">Privacidade & Retenção</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div><Label>Evento Privado</Label><p className="text-xs text-muted-foreground">Apenas convidados com link podem acessar</p></div>
                <Switch />
              </div>
              <div className="flex items-center justify-between">
                <div><Label>Moderação Obrigatória</Label><p className="text-xs text-muted-foreground">Fotos precisam de aprovação antes de aparecer</p></div>
                <Switch defaultChecked />
              </div>
              <div>
                <Label>Retenção de Dados</Label>
                <Select><SelectTrigger className="mt-1.5 w-48"><SelectValue placeholder="Selecione" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="30">30 dias</SelectItem>
                    <SelectItem value="90">90 dias</SelectItem>
                    <SelectItem value="180">6 meses</SelectItem>
                    <SelectItem value="365">1 ano</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
        </div>

        {/* Preview */}
        <div className="space-y-4">
          <div className="glass rounded-xl p-5 sticky top-20">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2"><Eye className="h-4 w-4" /> Preview</h3>
            <div className="rounded-lg bg-muted/50 overflow-hidden">
              <div className="h-28 gradient-primary flex items-center justify-center">
                <CalendarDays className="h-10 w-10 text-primary-foreground/60" />
              </div>
              <div className="p-4 space-y-2">
                <p className="font-semibold">{name || 'Nome do Evento'}</p>
                <p className="text-xs text-muted-foreground">Data não definida · Local não definido</p>
                <div className="flex gap-1.5 mt-3">
                  {Object.entries(modules).filter(([, v]) => v).map(([k]) => (
                    <span key={k} className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-primary/10 text-primary capitalize">{k}</span>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}

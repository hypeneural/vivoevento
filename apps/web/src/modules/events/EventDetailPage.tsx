import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { mockEvents, mockMedia } from '@/shared/mock/data';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { EventStatusBadge, MediaStatusBadge, ChannelBadge } from '@/shared/components/StatusBadges';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Camera, CheckCircle, Clock, Globe, QrCode, Link2, Edit, Settings, Image, Monitor, Gamepad2, BarChart3 } from 'lucide-react';

export default function EventDetailPage() {
  const { id } = useParams();
  const event = mockEvents.find(e => e.id === id) || mockEvents[0];
  const eventMedia = mockMedia.filter(m => m.eventId === event.id);

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      {/* Banner */}
      <div className="relative rounded-xl overflow-hidden h-48 md:h-56">
        <img src={event.coverUrl} alt={event.name} className="w-full h-full object-cover" />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/40 to-transparent" />
        <div className="absolute bottom-4 left-4 right-4 flex items-end justify-between">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <h1 className="text-xl md:text-2xl font-bold">{event.name}</h1>
              <EventStatusBadge status={event.status} />
            </div>
            <p className="text-sm text-muted-foreground">{event.location} · {new Date(event.date).toLocaleDateString('pt-BR')}</p>
            <p className="text-xs text-muted-foreground mt-0.5">{event.organizationName} · {event.plan}</p>
          </div>
          <div className="hidden md:flex gap-2">
            <Button variant="outline" size="sm"><Edit className="h-3.5 w-3.5 mr-1" /> Editar</Button>
            <Button variant="outline" size="sm"><QrCode className="h-3.5 w-3.5 mr-1" /> QR Code</Button>
            <Button variant="outline" size="sm"><Link2 className="h-3.5 w-3.5 mr-1" /> Link Público</Button>
          </div>
        </div>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Fotos Recebidas" value={event.photosReceived} icon={Camera} />
        <StatsCard title="Fotos Aprovadas" value={event.photosApproved} icon={CheckCircle} />
        <StatsCard title="Pendentes" value={event.photosReceived - event.photosApproved} icon={Clock} />
        <StatsCard title="Módulos Ativos" value={event.modulesActive.length} icon={Settings} />
      </div>

      {/* Tabs */}
      <Tabs defaultValue="overview">
        <TabsList className="bg-muted/50">
          <TabsTrigger value="overview">Visão Geral</TabsTrigger>
          <TabsTrigger value="uploads">Uploads</TabsTrigger>
          <TabsTrigger value="moderation">Moderação</TabsTrigger>
          <TabsTrigger value="gallery">Galeria</TabsTrigger>
          <TabsTrigger value="wall">Wall</TabsTrigger>
          <TabsTrigger value="play">Play</TabsTrigger>
          <TabsTrigger value="hub">Hub</TabsTrigger>
          <TabsTrigger value="analytics">Analytics</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-6 space-y-6">
          {/* Modules Status */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {[
              { name: 'Live', icon: Image, active: event.modulesActive.includes('Live') },
              { name: 'Wall', icon: Monitor, active: event.modulesActive.includes('Wall') },
              { name: 'Play', icon: Gamepad2, active: event.modulesActive.includes('Play') },
              { name: 'Hub', icon: Globe, active: event.modulesActive.includes('Hub') },
            ].map(m => (
              <div key={m.name} className={`glass rounded-xl p-4 flex items-center gap-3 ${m.active ? 'border-success/20' : 'opacity-50'}`}>
                <div className={`rounded-lg p-2 ${m.active ? 'bg-success/10' : 'bg-muted'}`}>
                  <m.icon className={`h-5 w-5 ${m.active ? 'text-success' : 'text-muted-foreground'}`} />
                </div>
                <div>
                  <p className="text-sm font-medium">{m.name}</p>
                  <p className="text-xs text-muted-foreground">{m.active ? 'Ativo' : 'Inativo'}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Recent Uploads */}
          <div className="glass rounded-xl p-5">
            <h3 className="text-sm font-semibold mb-4">Últimos Uploads</h3>
            <div className="grid grid-cols-4 md:grid-cols-8 gap-2">
              {eventMedia.slice(0, 8).map(m => (
                <div key={m.id} className="relative group">
                  <img src={m.thumbnailUrl} alt="" className="h-20 w-full rounded-md object-cover" />
                  <div className="absolute bottom-1 left-1"><MediaStatusBadge status={m.status} /></div>
                </div>
              ))}
            </div>
          </div>

          {/* Description */}
          <div className="glass rounded-xl p-5">
            <h3 className="text-sm font-semibold mb-2">Descrição</h3>
            <p className="text-sm text-muted-foreground">{event.description}</p>
            <p className="text-sm text-muted-foreground mt-2">Responsável: <span className="text-foreground font-medium">{event.responsible}</span></p>
          </div>
        </TabsContent>

        <TabsContent value="uploads" className="mt-6">
          <div className="glass rounded-xl p-5">
            <h3 className="text-sm font-semibold mb-4">Uploads do Evento</h3>
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
              {eventMedia.map(m => (
                <div key={m.id} className="space-y-1.5">
                  <img src={m.thumbnailUrl} alt="" className="h-28 w-full rounded-lg object-cover" />
                  <div className="flex items-center justify-between">
                    <ChannelBadge channel={m.channel} />
                    <MediaStatusBadge status={m.status} />
                  </div>
                  <p className="text-xs text-muted-foreground">{m.senderName}</p>
                </div>
              ))}
            </div>
          </div>
        </TabsContent>

        {['moderation', 'gallery', 'wall', 'play', 'hub', 'analytics'].map(tab => (
          <TabsContent key={tab} value={tab} className="mt-6">
            <div className="glass rounded-xl p-8 text-center">
              <BarChart3 className="h-10 w-10 text-muted-foreground mx-auto mb-3" />
              <p className="text-sm text-muted-foreground">Acesse a página dedicada de <Link to={`/${tab === 'analytics' ? 'analytics' : tab}`} className="text-primary hover:underline">{tab}</Link> para gerenciar este módulo.</p>
            </div>
          </TabsContent>
        ))}
      </Tabs>
    </motion.div>
  );
}

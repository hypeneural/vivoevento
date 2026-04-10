import { Link, Navigate, useOutletContext, useParams } from 'react-router-dom';
import { ChevronLeft, Info } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { EventWorkspaceOutletContext } from './EventWorkspaceLayout';

const SECTION_CONFIG = {
  media: {
    title: 'Mídias do evento',
    description: 'Aqui ficará a visualização isolada das mídias deste evento.',
    capability: 'media',
  },
  moderation: {
    title: 'Moderação do evento',
    description: 'Aqui ficará a moderação isolada das mídias deste evento.',
    capability: 'moderation',
  },
  wall: {
    title: 'Telão do evento',
    description: 'Aqui ficará a operação isolada do telão deste evento.',
    capability: 'wall',
  },
  play: {
    title: 'Jogos do evento',
    description: 'Aqui ficará a operação isolada dos jogos deste evento.',
    capability: 'play',
  },
} as const;

export default function EventWorkspaceModulePage() {
  const { workspace } = useOutletContext<EventWorkspaceOutletContext>();
  const { section } = useParams<{ section?: string }>();

  if (!workspace || !section || !(section in SECTION_CONFIG)) {
    return <Navigate to={workspace ? `/my-events/${workspace.event_id}` : '/my-events'} replace />;
  }

  const config = SECTION_CONFIG[section as keyof typeof SECTION_CONFIG];

  if (!workspace.capabilities.includes(config.capability)) {
    return <Navigate to={`/my-events/${workspace.event_id}`} replace />;
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" asChild className="px-0">
        <Link to={`/my-events/${workspace.event_id}`}>
          <ChevronLeft className="mr-2 h-4 w-4" />
          Voltar para o evento
        </Link>
      </Button>

      <Card className="border-border/60 shadow-sm">
        <CardHeader>
          <CardTitle>{config.title}</CardTitle>
          <CardDescription>{config.description}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 p-6 text-sm text-muted-foreground">
            <div className="flex items-center gap-2 font-medium text-foreground">
              <Info className="h-4 w-4 text-primary" />
              Estrutura inicial pronta
            </div>
            <p className="mt-2">
              Esta rota já está isolada pelo contexto do evento e preparada para receber a tela dedicada na próxima fase.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

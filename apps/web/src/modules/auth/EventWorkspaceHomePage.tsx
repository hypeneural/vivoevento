import { Link, useOutletContext } from 'react-router-dom';
import { CalendarDays, ChevronLeft, ShieldCheck } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { EventWorkspaceOutletContext } from './EventWorkspaceLayout';
import { capabilityLabel, eventWorkspaceActions, formatEventDate } from './workspace-utils';

export default function EventWorkspaceHomePage() {
  const { workspace } = useOutletContext<EventWorkspaceOutletContext>();

  if (!workspace) {
    return null;
  }

  const actions = eventWorkspaceActions(workspace);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" asChild className="px-0">
          <Link to="/my-events">
            <ChevronLeft className="mr-2 h-4 w-4" />
            Voltar para meus eventos
          </Link>
        </Button>
      </div>

      <Card className="border-border/60 bg-gradient-to-br from-primary/5 via-background to-background shadow-sm">
        <CardContent className="grid gap-4 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">{workspace.organization_name}</Badge>
              <Badge variant="outline">{workspace.role_label}</Badge>
              <Badge variant="outline">{formatEventDate(workspace.event_date)}</Badge>
            </div>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{workspace.event_title}</h1>
              <p className="text-sm text-muted-foreground">
                Este acesso está isolado para este evento. Você só verá as áreas liberadas neste contexto.
              </p>
            </div>
          </div>

          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <CalendarDays className="h-6 w-6" />
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
        <Card className="border-border/60 shadow-sm">
          <CardHeader>
            <CardTitle>Ações liberadas</CardTitle>
            <CardDescription>
              Escolha a área que você precisa usar neste evento.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            {actions.map((action) => (
              <Card key={action.key} className="border-border/60 shadow-none">
                <CardHeader>
                  <CardTitle className="text-base">{action.label}</CardTitle>
                  <CardDescription>{action.description}</CardDescription>
                </CardHeader>
                <CardContent>
                  <Button asChild className="w-full">
                    <Link to={action.to}>Entrar</Link>
                  </Button>
                </CardContent>
              </Card>
            ))}
          </CardContent>
        </Card>

        <Card className="border-border/60 shadow-sm">
          <CardHeader>
            <CardTitle>Seu perfil neste evento</CardTitle>
            <CardDescription>
              Resumo rápido do que este acesso pode fazer.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-2xl border border-border/60 bg-muted/30 p-4">
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4 text-primary" />
                <span className="text-sm font-medium text-foreground">{workspace.role_label}</span>
              </div>
              <p className="mt-2 text-sm text-muted-foreground">
                Seu acesso está limitado a este evento e fica registrado em log individual.
              </p>
            </div>

            <div className="flex flex-wrap gap-2">
              {workspace.capabilities.map((capability) => (
                <Badge key={capability} variant="secondary">
                  {capabilityLabel(capability)}
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

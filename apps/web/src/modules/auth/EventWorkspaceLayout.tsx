import { useMemo, useState } from 'react';
import { Link, Navigate, Outlet, useLocation, useNavigate, useParams } from 'react-router-dom';
import { CalendarDays, ChevronLeft, LogOut, PanelsTopLeft, User } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAuth } from '@/app/providers/AuthProvider';
import type { MeEventAccessWorkspace } from '@/lib/api-types';
import { formatRoleLabel } from '@/shared/auth/labels';
import { UserAvatar } from '@/shared/components/UserAvatar';
import { formatEventDate } from './workspace-utils';

export interface EventWorkspaceOutletContext {
  workspace: MeEventAccessWorkspace | null;
}

export default function EventWorkspaceLayout() {
  const {
    meUser,
    workspaces,
    activeContext,
    isEventOnlySession,
    preferredHomePath,
    setEventContext,
    logout,
  } = useAuth();
  const params = useParams<{ eventId?: string }>();
  const navigate = useNavigate();
  const location = useLocation();
  const [isSwitching, setIsSwitching] = useState(false);

  const eventAccesses = useMemo(
    () => [...workspaces.event_accesses].sort((left, right) => {
      if (left.organization_name === right.organization_name) {
        return left.event_title.localeCompare(right.event_title, 'pt-BR');
      }

      return left.organization_name.localeCompare(right.organization_name, 'pt-BR');
    }),
    [workspaces.event_accesses],
  );

  if (!meUser) {
    return <Navigate to="/login" replace />;
  }

  if (eventAccesses.length === 0) {
    return <Navigate to={preferredHomePath || '/'} replace />;
  }

  const routeEventId = params.eventId ? Number(params.eventId) : null;
  const currentEventId = routeEventId ?? activeContext?.event_id ?? eventAccesses[0]?.event_id ?? null;
  const currentWorkspace = eventAccesses.find((item) => item.event_id === currentEventId) ?? null;

  const handleSelectEvent = async (value: string) => {
    const eventId = Number(value);
    setIsSwitching(true);
    try {
      await setEventContext(eventId);
      const sectionMatch = location.pathname.match(/\/my-events\/\d+\/(.+)$/);
      const suffix = sectionMatch?.[1] ? `/${sectionMatch[1]}` : '';
      navigate(`/my-events/${eventId}${suffix}`);
    } finally {
      setIsSwitching(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="min-h-[100dvh] bg-background">
      <header className="border-b border-border bg-background/90 backdrop-blur-xl">
        <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <CalendarDays className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-foreground">Evento Vivo</p>
                <p className="text-xs text-muted-foreground">
                  Área segura de acesso por evento
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              {!isEventOnlySession ? (
                <Button variant="outline" asChild className="hidden sm:inline-flex">
                  <Link to="/">
                    <PanelsTopLeft className="mr-2 h-4 w-4" />
                    Voltar ao painel
                  </Link>
                </Button>
              ) : null}

              <Button variant="ghost" asChild className="hidden sm:inline-flex">
                <Link to="/profile">
                  <User className="mr-2 h-4 w-4" />
                  Meu perfil
                </Link>
              </Button>

              <Button variant="ghost" onClick={handleLogout}>
                <LogOut className="mr-2 h-4 w-4" />
                Sair
              </Button>
            </div>
          </div>

          <Card className="border-border/60 shadow-sm">
            <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex items-start gap-3">
                <UserAvatar name={meUser.name} avatarUrl={meUser.avatar_url} size="sm" />
                <div>
                  <p className="text-sm font-medium text-foreground">{meUser.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {formatRoleLabel(meUser.role.key, meUser.role.name)}
                  </p>
                </div>
              </div>

              <div className="grid gap-3 lg:min-w-[420px] lg:grid-cols-[1fr_auto]">
                <Select
                  disabled={isSwitching}
                  value={currentWorkspace ? String(currentWorkspace.event_id) : undefined}
                  onValueChange={handleSelectEvent}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Escolha um evento" />
                  </SelectTrigger>
                  <SelectContent>
                    {eventAccesses.map((workspace) => (
                      <SelectItem key={workspace.event_id} value={String(workspace.event_id)}>
                        {workspace.organization_name} · {workspace.event_title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {location.pathname !== '/my-events' ? (
                  <Button variant="outline" asChild>
                    <Link to="/my-events">
                      <ChevronLeft className="mr-2 h-4 w-4" />
                      Trocar evento
                    </Link>
                  </Button>
                ) : null}
              </div>

              {currentWorkspace ? (
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant="secondary">{currentWorkspace.organization_name}</Badge>
                  <Badge variant="outline">{currentWorkspace.role_label}</Badge>
                  <Badge variant="outline">{formatEventDate(currentWorkspace.event_date)}</Badge>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <Outlet context={{ workspace: currentWorkspace } satisfies EventWorkspaceOutletContext} />
      </main>
    </div>
  );
}

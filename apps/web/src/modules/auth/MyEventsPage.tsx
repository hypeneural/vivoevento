import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CalendarDays, Filter, Search, Sparkles } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuth } from '@/app/providers/AuthProvider';
import { cn } from '@/lib/utils';
import {
  capabilityLabel,
  eventWorkspaceActions,
  filterEventWorkspaces,
  formatEventDate,
  groupEventWorkspacesByPartner,
  type MyEventsFilters,
} from './workspace-utils';

const DEFAULT_FILTERS: MyEventsFilters = {
  search: '',
  capability: 'all',
  partner: 'all',
  tab: 'active',
  sort: 'event_date_asc',
};

export default function MyEventsPage() {
  const { workspaces, activeContext, setEventContext, meOrganization } = useAuth();
  const navigate = useNavigate();
  const [filters, setFilters] = useState<MyEventsFilters>(DEFAULT_FILTERS);
  const [showFilters, setShowFilters] = useState(false);
  const [loadingEventId, setLoadingEventId] = useState<number | null>(null);

  const partners = useMemo(
    () => Array.from(new Set(workspaces.event_accesses.map((item) => item.organization_name))).sort((left, right) => left.localeCompare(right, 'pt-BR')),
    [workspaces.event_accesses],
  );

  const filtered = useMemo(
    () => filterEventWorkspaces(workspaces.event_accesses, filters),
    [filters, workspaces.event_accesses],
  );

  const grouped = useMemo(
    () => groupEventWorkspacesByPartner(filtered),
    [filtered],
  );

  const handleOpenWorkspace = async (eventId: number) => {
    setLoadingEventId(eventId);
    try {
      await setEventContext(eventId);
      navigate(`/my-events/${eventId}`);
    } finally {
      setLoadingEventId(null);
    }
  };

  if (workspaces.event_accesses.length === 0) {
    return (
      <Card className="border-dashed">
        <CardHeader>
          <CardTitle>Nenhum evento disponível</CardTitle>
          <CardDescription>
            Este acesso ainda não possui convites ativos por evento.
          </CardDescription>
        </CardHeader>
        {meOrganization ? (
          <CardContent>
            <Button onClick={() => navigate('/')}>Voltar ao painel</Button>
          </CardContent>
        ) : null}
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <Card className="border-border/60 bg-gradient-to-br from-primary/5 via-background to-background shadow-sm">
        <CardContent className="flex flex-col gap-4 p-6 lg:flex-row lg:items-center lg:justify-between">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
              <Sparkles className="h-3.5 w-3.5" />
              Seus acessos por evento
            </div>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Meus eventos</h1>
              <p className="text-sm text-muted-foreground">
                Escolha o evento correto e entre apenas nas áreas liberadas para o seu acesso.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">{workspaces.event_accesses.length} acessos</Badge>
            <Button variant="outline" onClick={() => setShowFilters((current) => !current)}>
              <Filter className="mr-2 h-4 w-4" />
              Filtros e ordenação
            </Button>
          </div>
        </CardContent>
      </Card>

      <Tabs
        value={filters.tab}
        onValueChange={(value) => setFilters((current) => ({ ...current, tab: value as MyEventsFilters['tab'] }))}
      >
        <TabsList className="grid w-full grid-cols-4 lg:w-auto">
          <TabsTrigger value="active">Ativos hoje</TabsTrigger>
          <TabsTrigger value="upcoming">Próximos</TabsTrigger>
          <TabsTrigger value="ended">Encerrados</TabsTrigger>
          <TabsTrigger value="all">Todos</TabsTrigger>
        </TabsList>
      </Tabs>

      {showFilters ? (
        <Card className="border-border/60">
          <CardContent className="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
            <div className="relative xl:col-span-2">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={filters.search}
                onChange={(event) => setFilters((current) => ({ ...current, search: event.target.value }))}
                placeholder="Buscar por parceiro, evento ou perfil"
                className="pl-9"
              />
            </div>

            <Select
              value={filters.partner}
              onValueChange={(value) => setFilters((current) => ({ ...current, partner: value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Parceiro" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os parceiros</SelectItem>
                {partners.map((partner) => (
                  <SelectItem key={partner} value={partner}>{partner}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              value={filters.capability}
              onValueChange={(value) => setFilters((current) => ({ ...current, capability: value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Capacidade" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas as ações</SelectItem>
                <SelectItem value="media">Mídias</SelectItem>
                <SelectItem value="moderation">Moderação</SelectItem>
                <SelectItem value="wall">Telão</SelectItem>
                <SelectItem value="play">Jogos</SelectItem>
              </SelectContent>
            </Select>

            <Select
              value={filters.sort}
              onValueChange={(value) => setFilters((current) => ({ ...current, sort: value as MyEventsFilters['sort'] }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Ordenar por" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="event_date_asc">Próximos primeiro</SelectItem>
                <SelectItem value="event_date_desc">Mais recentes</SelectItem>
                <SelectItem value="partner_name">Parceiro A-Z</SelectItem>
                <SelectItem value="event_title">Evento A-Z</SelectItem>
              </SelectContent>
            </Select>
          </CardContent>
        </Card>
      ) : null}

      {grouped.length === 0 ? (
        <Card className="border-dashed">
          <CardContent className="flex min-h-[220px] flex-col items-center justify-center gap-3 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
              <CalendarDays className="h-5 w-5" />
            </div>
            <div className="space-y-1">
              <p className="font-medium text-foreground">Nenhum evento encontrado</p>
              <p className="text-sm text-muted-foreground">
                Ajuste os filtros para localizar outro acesso disponível.
              </p>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <div className="space-y-6">
        {grouped.map((group) => (
          <section key={group.organizationName} className="space-y-3">
            <div className="flex items-center justify-between gap-2">
              <div>
                <h2 className="text-base font-semibold text-foreground">{group.organizationName}</h2>
                <p className="text-sm text-muted-foreground">
                  {group.items.length} evento{group.items.length > 1 ? 's' : ''} disponível{group.items.length > 1 ? 's' : ''}
                </p>
              </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              {group.items.map((workspace) => {
                const actions = eventWorkspaceActions(workspace);
                const primaryAction = actions.find((action) => action.primary) ?? actions[0];
                const isActiveContext = activeContext?.event_id === workspace.event_id;

                return (
                  <Card
                    key={workspace.event_id}
                    className={cn(
                      'border-border/60 shadow-sm transition-all',
                      isActiveContext && 'border-primary/40 shadow-primary/10',
                    )}
                  >
                    <CardHeader className="space-y-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={isActiveContext ? 'default' : 'secondary'}>
                          {isActiveContext ? 'Em uso' : workspace.role_label}
                        </Badge>
                        {!isActiveContext ? <Badge variant="outline">{workspace.role_label}</Badge> : null}
                        <Badge variant="outline">{formatEventDate(workspace.event_date)}</Badge>
                      </div>
                      <div>
                        <CardTitle className="text-lg">{workspace.event_title}</CardTitle>
                        <CardDescription>
                          {workspace.organization_name} · {workspace.event_status ?? 'status não informado'}
                        </CardDescription>
                      </div>
                    </CardHeader>

                    <CardContent className="space-y-4">
                      <div className="flex flex-wrap gap-2">
                        {workspace.capabilities
                          .filter((capability) => capability !== 'overview')
                          .map((capability) => (
                            <Badge key={capability} variant="secondary">
                              {capabilityLabel(capability)}
                            </Badge>
                          ))}
                      </div>

                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                          <p className="text-sm font-medium text-foreground">
                            {primaryAction ? primaryAction.label : 'Abrir evento'}
                          </p>
                          <p className="text-sm text-muted-foreground">
                            {primaryAction?.description ?? 'Entrar no resumo deste evento.'}
                          </p>
                        </div>

                        <Button
                          onClick={() => handleOpenWorkspace(workspace.event_id)}
                          disabled={loadingEventId === workspace.event_id}
                        >
                          {loadingEventId === workspace.event_id ? 'Abrindo...' : 'Abrir evento'}
                        </Button>
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
}

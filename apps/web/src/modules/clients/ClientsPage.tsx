import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  Building2,
  Loader2,
  MoreHorizontal,
  Plus,
  Search,
  SlidersHorizontal,
  Trash2,
  UserCheck,
} from 'lucide-react';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useAuth } from '@/app/providers/AuthProvider';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { usePermissions } from '@/shared/hooks/usePermissions';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';

import { buildOrganizationOptions, clientsService } from './api';
import { ClientFormDialog } from './components/ClientFormDialog';
import { ClientsPagination } from './components/ClientsPagination';
import {
  CLIENT_SORT_OPTIONS,
  CLIENT_TYPE_LABELS,
  CLIENT_TYPE_OPTIONS,
  type ClientFormPayload,
  type ClientItem,
  type ClientSortBy,
  type SortDirection,
} from './types';

function formatDate(value?: string | null) {
  if (!value) return 'Sem registro';

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function formatContact(client: ClientItem) {
  if (client.email && client.phone) {
    return `${client.email} · ${client.phone}`;
  }

  return client.email || client.phone || 'Sem contato';
}

function organizationBillingLabel(client: ClientItem) {
  return client.organization_billing?.plan_name || client.plan_name || 'Sem assinatura da conta';
}

function organizationBillingStatusLabel(client: ClientItem) {
  return client.organization_billing?.subscription_status || client.subscription_status || null;
}

export default function ClientsPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { meOrganization, can } = useAuth();
  const { isPlatformAdmin } = usePermissions();

  const canCreate = can('clients.create');
  const canEdit = can('clients.update');
  const canDelete = can('clients.delete');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [hasEventsFilter, setHasEventsFilter] = useState<string>('all');
  const [planFilter, setPlanFilter] = useState<string>('all');
  const [organizationFilter, setOrganizationFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<ClientSortBy>('created_at');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
  const [selectedClient, setSelectedClient] = useState<ClientItem | null>(null);
  const [clientToDelete, setClientToDelete] = useState<ClientItem | null>(null);

  const deferredSearch = useDeferredValue(search);

  useEffect(() => {
    setPage(1);
  }, [deferredSearch, hasEventsFilter, organizationFilter, planFilter, sortBy, sortDirection, typeFilter]);

  const filters = useMemo(() => ({
    search: deferredSearch || undefined,
    type: typeFilter === 'all' ? undefined : typeFilter,
    plan_code: planFilter === 'all' ? undefined : planFilter,
    has_events: hasEventsFilter === 'all' ? undefined : hasEventsFilter === 'with_events',
    organization_id: organizationFilter === 'all' ? undefined : Number(organizationFilter),
    sort_by: sortBy,
    sort_direction: sortDirection,
    page,
    per_page: 12,
  }), [deferredSearch, hasEventsFilter, organizationFilter, page, planFilter, sortBy, sortDirection, typeFilter]);

  const clientsQuery = useQuery({
    queryKey: queryKeys.clients.list(filters),
    queryFn: () => clientsService.list(filters),
    enabled: can('clients.view'),
  });

  const organizationsQuery = useQuery({
    queryKey: [...queryKeys.organizations.all(), 'options'],
    queryFn: () => clientsService.listOrganizations(),
    enabled: isPlatformAdmin,
  });

  const plansQuery = useQuery({
    queryKey: [...queryKeys.plans.all(), 'catalog'],
    queryFn: () => clientsService.listPlans(),
    enabled: can('billing.view') || can('plans.view'),
  });

  const organizationOptions = useMemo(() => {
    const options = organizationsQuery.data ? buildOrganizationOptions(organizationsQuery.data) : [];

    if (meOrganization && !options.some((option) => option.id === meOrganization.id)) {
      options.unshift({
        id: meOrganization.id,
        label: meOrganization.name,
        type: meOrganization.type,
        status: meOrganization.status,
      });
    }

    return options;
  }, [meOrganization, organizationsQuery.data]);

  const createMutation = useMutation({
    mutationFn: (payload: ClientFormPayload) => clientsService.create(payload),
    onSuccess: async (client) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.clients.all() });
      await queryClient.invalidateQueries({ queryKey: ['events', 'form', 'clients'] });

      setIsDialogOpen(false);
      setSelectedClient(null);

      toast({
        title: 'Cliente cadastrado',
        description: `"${client.name}" foi criado com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao cadastrar cliente',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ clientId, payload }: { clientId: number; payload: ClientFormPayload }) => (
      clientsService.update(clientId, payload)
    ),
    onSuccess: async (client) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.clients.all() });
      await queryClient.invalidateQueries({ queryKey: ['events', 'form', 'clients'] });

      setIsDialogOpen(false);
      setSelectedClient(null);

      toast({
        title: 'Cliente atualizado',
        description: `"${client.name}" foi atualizado com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar cliente',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (clientId: number) => clientsService.remove(clientId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.clients.all() });
      await queryClient.invalidateQueries({ queryKey: ['events', 'form', 'clients'] });

      const deletedName = clientToDelete?.name;
      setClientToDelete(null);

      toast({
        title: 'Cliente removido',
        description: deletedName ? `"${deletedName}" foi removido da base.` : 'Cliente removido com sucesso.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao remover cliente',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const clients = clientsQuery.data?.data ?? [];
  const pagination = clientsQuery.data?.meta;
  const total = pagination?.total ?? clients.length;

  const openCreateDialog = () => {
    setDialogMode('create');
    setSelectedClient(null);
    setIsDialogOpen(true);
  };

  const openEditDialog = (client: ClientItem) => {
    setDialogMode('edit');
    setSelectedClient(client);
    setIsDialogOpen(true);
  };

  const handleFormSubmit = (payload: ClientFormPayload) => {
    if (dialogMode === 'create') {
      createMutation.mutate(payload);
      return;
    }

    if (!selectedClient) {
      return;
    }

    updateMutation.mutate({
      clientId: selectedClient.id,
      payload,
    });
  };

  const resetFilters = () => {
    setSearch('');
    setTypeFilter('all');
    setHasEventsFilter('all');
    setPlanFilter('all');
    setOrganizationFilter('all');
    setSortBy('created_at');
    setSortDirection('desc');
    setPage(1);
  };

  if (!can('clients.view')) {
    return (
      <EmptyState
        icon={UserCheck}
        title="Acesso indisponível"
        description="Sua sessão atual não possui permissão para visualizar clientes."
      />
    );
  }

  return (
    <>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-5">
        <PageHeader
          title="Clientes"
          description={clientsQuery.isLoading ? 'Carregando clientes...' : `${total} clientes encontrados`}
          actions={canCreate ? (
            <Button className="gradient-primary border-0" onClick={openCreateDialog}>
              <Plus className="h-4 w-4" />
              Novo cliente
            </Button>
          ) : undefined}
        />

        <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
          <div className="flex items-center gap-2 text-sm font-semibold">
            <SlidersHorizontal className="h-4 w-4 text-primary" />
            Filtros e ordenação
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
            <div className="relative xl:col-span-4">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar por nome, contato, documento ou parceiro"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="pl-9"
              />
            </div>

            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Tipo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os tipos</SelectItem>
                {CLIENT_TYPE_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={hasEventsFilter} onValueChange={setHasEventsFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Eventos" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="with_events">Com eventos</SelectItem>
                <SelectItem value="without_events">Sem eventos</SelectItem>
              </SelectContent>
            </Select>

            {plansQuery.data && plansQuery.data.length > 0 ? (
              <Select value={planFilter} onValueChange={setPlanFilter}>
                <SelectTrigger className="xl:col-span-2">
                  <SelectValue placeholder="Plano da conta" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os planos da conta</SelectItem>
                  {plansQuery.data.map((plan) => (
                    <SelectItem key={plan.id} value={plan.code}>
                      {plan.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : null}

            {isPlatformAdmin ? (
              <Select value={organizationFilter} onValueChange={setOrganizationFilter}>
                <SelectTrigger className="xl:col-span-2">
                  <SelectValue placeholder="Parceiro" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os parceiros</SelectItem>
                  {organizationOptions.map((organization) => (
                    <SelectItem key={organization.id} value={String(organization.id)}>
                      {organization.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ) : null}

            <Select value={sortBy} onValueChange={(value) => setSortBy(value as ClientSortBy)}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Ordenar por" />
              </SelectTrigger>
              <SelectContent>
                {CLIENT_SORT_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={sortDirection} onValueChange={(value) => setSortDirection(value as SortDirection)}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Direção" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="desc">Mais recentes primeiro</SelectItem>
                <SelectItem value="asc">Mais antigos primeiro</SelectItem>
              </SelectContent>
            </Select>

            <div className="xl:col-span-2">
              <Button variant="outline" className="w-full" onClick={resetFilters}>
                Limpar filtros
              </Button>
            </div>
          </div>
        </section>

        {clientsQuery.isLoading && clients.length === 0 ? (
          <div className="glass rounded-3xl border border-border/60 px-4 py-16 text-center text-sm text-muted-foreground">
            <div className="flex items-center justify-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              Carregando clientes...
            </div>
          </div>
        ) : clientsQuery.isError ? (
          <div className="glass rounded-3xl border border-destructive/30 px-4 py-16 text-center text-sm text-destructive">
            Não foi possível carregar os clientes agora.
          </div>
        ) : clients.length === 0 ? (
          <div className="glass rounded-3xl border border-border/60">
            <EmptyState
              icon={UserCheck}
              title="Nenhum cliente encontrado"
              description="Ajuste os filtros ou cadastre um novo cliente para começar."
              action={canCreate ? <Button onClick={openCreateDialog}>Cadastrar cliente</Button> : undefined}
            />
          </div>
        ) : (
          <>
            <div className="grid gap-3 lg:hidden">
              {clients.map((client) => (
                <article key={client.id} className="glass rounded-3xl border border-border/60 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <h2 className="truncate text-base font-semibold">{client.name}</h2>
                        <Badge variant="outline">{CLIENT_TYPE_LABELS[client.type ?? 'pessoa_fisica']}</Badge>
                      </div>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {client.organization_name || meOrganization?.name || 'Parceiro não informado'}
                      </p>
                    </div>

                    {(canEdit || canDelete) ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          {canEdit ? (
                            <DropdownMenuItem onClick={() => openEditDialog(client)}>
                              Editar
                            </DropdownMenuItem>
                          ) : null}
                          {canDelete ? (
                            <DropdownMenuItem
                              className="text-destructive focus:text-destructive"
                              onClick={() => setClientToDelete(client)}
                            >
                              Remover
                            </DropdownMenuItem>
                          ) : null}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    ) : null}
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Contato</p>
                      <p className="mt-1 line-clamp-2 font-medium">{formatContact(client)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Eventos</p>
                      <p className="mt-1 font-medium">{client.events_count ?? 0}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Plano da organizacao</p>
                      <p className="mt-1 font-medium">{organizationBillingLabel(client)}</p>
                      {organizationBillingStatusLabel(client) ? (
                        <p className="mt-1 text-xs text-muted-foreground">
                          Status {organizationBillingStatusLabel(client)}
                        </p>
                      ) : null}
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Cadastro</p>
                      <p className="mt-1 font-medium">{formatDate(client.created_at)}</p>
                    </div>
                  </div>
                </article>
              ))}
            </div>

            <div className="glass hidden overflow-hidden rounded-3xl border border-border/60 lg:block">
              <Table>
                <TableHeader>
                  <TableRow className="border-border/50">
                    <TableHead>Cliente</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Contato</TableHead>
                    <TableHead>Parceiro</TableHead>
                    <TableHead>Eventos</TableHead>
                    <TableHead>Plano da organizacao</TableHead>
                    <TableHead>Cadastro</TableHead>
                    {(canEdit || canDelete) ? <TableHead className="w-[120px] text-right">Ações</TableHead> : null}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {clients.map((client) => (
                    <TableRow key={client.id} className="border-border/30 hover:bg-muted/20">
                      <TableCell>
                        <div className="min-w-0">
                          <p className="truncate font-medium">{client.name}</p>
                          <p className="truncate text-xs text-muted-foreground">
                            {client.document_number || 'Documento não informado'}
                          </p>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{CLIENT_TYPE_LABELS[client.type ?? 'pessoa_fisica']}</Badge>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {formatContact(client)}
                      </TableCell>
                      <TableCell>
                        <div className="flex min-w-0 items-center gap-2">
                          <Building2 className="h-4 w-4 text-muted-foreground" />
                          <span className="truncate text-sm">
                            {client.organization_name || meOrganization?.name || 'Não informado'}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell className="text-sm">{client.events_count ?? 0}</TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          <Badge variant="outline">{organizationBillingLabel(client)}</Badge>
                          {organizationBillingStatusLabel(client) ? (
                            <Badge variant="secondary">{organizationBillingStatusLabel(client)}</Badge>
                          ) : null}
                        </div>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {formatDate(client.created_at)}
                      </TableCell>
                      {(canEdit || canDelete) ? (
                        <TableCell>
                          <div className="flex items-center justify-end gap-2">
                            {canEdit ? (
                              <Button variant="outline" size="sm" onClick={() => openEditDialog(client)}>
                                Editar
                              </Button>
                            ) : null}
                            {canDelete ? (
                              <Button variant="ghost" size="sm" onClick={() => setClientToDelete(client)}>
                                <Trash2 className="h-4 w-4" />
                                Remover
                              </Button>
                            ) : null}
                          </div>
                        </TableCell>
                      ) : null}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {pagination ? (
                <ClientsPagination
                  currentPage={pagination.page}
                  lastPage={pagination.last_page}
                  perPage={pagination.per_page}
                  total={pagination.total}
                  isFetching={clientsQuery.isFetching}
                  onPageChange={setPage}
                />
              ) : null}
            </div>

            {pagination ? (
              <div className="glass rounded-3xl border border-border/60 lg:hidden">
                <ClientsPagination
                  currentPage={pagination.page}
                  lastPage={pagination.last_page}
                  perPage={pagination.per_page}
                  total={pagination.total}
                  isFetching={clientsQuery.isFetching}
                  onPageChange={setPage}
                />
              </div>
            ) : null}
          </>
        )}
      </motion.div>

      <ClientFormDialog
        open={isDialogOpen}
        mode={dialogMode}
        client={selectedClient}
        canSelectOrganization={isPlatformAdmin}
        organizationLabel={meOrganization?.name}
        defaultOrganizationId={meOrganization?.id}
        organizationOptions={organizationOptions}
        isSubmitting={createMutation.isPending || updateMutation.isPending}
        onOpenChange={setIsDialogOpen}
        onSubmit={handleFormSubmit}
      />

      <AlertDialog open={!!clientToDelete} onOpenChange={(open) => !open && setClientToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover cliente</AlertDialogTitle>
            <AlertDialogDescription>
              {clientToDelete
                ? `Você está removendo "${clientToDelete.name}". Essa ação faz soft delete e pode impactar vínculos futuros de eventos.`
                : 'Confirme a remoção do cliente.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (!clientToDelete) {
                  return;
                }

                deleteMutation.mutate(clientToDelete.id);
              }}
            >
              Remover cliente
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

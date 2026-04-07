import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  DollarSign,
  Eye,
  Loader2,
  MoreHorizontal,
  PauseCircle,
  Pencil,
  Plus,
  Search,
  SlidersHorizontal,
  Trash2,
  TrendingUp,
  Users,
} from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { EmptyState } from '@/shared/components/EmptyState';
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
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { queryKeys } from '@/lib/query-client';
import { useToast } from '@/hooks/use-toast';

import { partnersService } from './api';
import { PartnerDetailSheet } from './components/PartnerDetailSheet';
import { PartnerFormDialog } from './components/PartnerFormDialog';
import { PartnerGrantDialog } from './components/PartnerGrantDialog';
import { PartnerStaffDialog } from './components/PartnerStaffDialog';
import { PartnerSuspendDialog } from './components/PartnerSuspendDialog';
import {
  PARTNER_SORT_OPTIONS,
  PARTNER_STATUS_LABELS,
  PARTNER_SUBSCRIPTION_STATUS_LABELS,
  type PartnerFormPayload,
  type PartnerGrantPayload,
  type PartnerListItem,
  type PartnerSortBy,
  type PartnerStaffPayload,
  type PartnerStatus,
  type PartnerSuspendPayload,
  type PartnerSubscriptionStatus,
  type SortDirection,
} from './types';

function formatCurrencyFromCents(cents: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(cents / 100);
}

function partnerStatusLabel(status: string) {
  return PARTNER_STATUS_LABELS[status as PartnerStatus] ?? status;
}

function subscriptionStatusLabel(status?: string | null) {
  if (!status) return 'Sem assinatura';

  return PARTNER_SUBSCRIPTION_STATUS_LABELS[status as PartnerSubscriptionStatus] ?? status;
}

function partnerStatusClass(status: string) {
  if (status === 'active') return 'text-success border-success/20 bg-success/10';
  if (status === 'suspended') return 'text-warning border-warning/20 bg-warning/10';

  return 'text-muted-foreground';
}

export default function PartnersPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { can } = useAuth();
  const canViewPartners = can('partners.view.any') || can('partners.manage.any');
  const canManagePartners = can('partners.manage.any');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [isFiltersOpen, setIsFiltersOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [planFilter, setPlanFilter] = useState('');
  const [subscriptionStatusFilter, setSubscriptionStatusFilter] = useState<string>('all');
  const [hasActiveEventsFilter, setHasActiveEventsFilter] = useState<string>('all');
  const [hasClientsFilter, setHasClientsFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<PartnerSortBy>('created_at');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
  const [detailPartnerId, setDetailPartnerId] = useState<number | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
  const [partnerForForm, setPartnerForForm] = useState<PartnerListItem | null>(null);
  const [partnerForSuspend, setPartnerForSuspend] = useState<PartnerListItem | null>(null);
  const [partnerForStaff, setPartnerForStaff] = useState<PartnerListItem | null>(null);
  const [partnerForGrant, setPartnerForGrant] = useState<PartnerListItem | null>(null);
  const [partnerForDelete, setPartnerForDelete] = useState<PartnerListItem | null>(null);

  const deferredSearch = useDeferredValue(search);

  useEffect(() => {
    setPage(1);
  }, [deferredSearch, hasActiveEventsFilter, hasClientsFilter, planFilter, sortBy, sortDirection, statusFilter, subscriptionStatusFilter]);

  const filters = useMemo(() => ({
    search: deferredSearch || undefined,
    status: statusFilter === 'all' ? undefined : statusFilter as PartnerStatus,
    plan_code: planFilter || undefined,
    subscription_status: subscriptionStatusFilter === 'all' ? undefined : subscriptionStatusFilter,
    has_active_events: hasActiveEventsFilter === 'all' ? undefined : hasActiveEventsFilter === 'with_active_events',
    has_clients: hasClientsFilter === 'all' ? undefined : hasClientsFilter === 'with_clients',
    sort_by: sortBy,
    sort_direction: sortDirection,
    page,
    per_page: 15,
  }), [deferredSearch, hasActiveEventsFilter, hasClientsFilter, page, planFilter, sortBy, sortDirection, statusFilter, subscriptionStatusFilter]);

  const partnersQuery = useQuery({
    queryKey: queryKeys.partners.list(filters),
    queryFn: () => partnersService.list(filters),
    enabled: canViewPartners,
  });

  const partners = partnersQuery.data?.data ?? [];
  const pagination = partnersQuery.data?.meta;
  const totalPartners = pagination?.total ?? partners.length;
  const activePartnersOnPage = partners.filter((partner) => partner.status === 'active').length;
  const totalRevenueOnPage = partners.reduce((total, partner) => total + partner.revenue.total_cents, 0);
  const activeEventsOnPage = partners.reduce((total, partner) => total + partner.active_events_count, 0);

  const invalidatePartners = async () => {
    await queryClient.invalidateQueries({ queryKey: queryKeys.partners.all() });
  };

  const createPartnerMutation = useMutation({
    mutationFn: (payload: PartnerFormPayload) => partnersService.create(payload),
    onSuccess: async (partner) => {
      await invalidatePartners();
      setIsFormOpen(false);
      setPartnerForForm(null);
      setDetailPartnerId(partner.id);
      setIsDetailOpen(true);
      toast({
        title: 'Parceiro criado',
        description: `"${partner.name}" foi cadastrado.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao criar parceiro',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const updatePartnerMutation = useMutation({
    mutationFn: ({ partnerId, payload }: { partnerId: number; payload: PartnerFormPayload }) => (
      partnersService.update(partnerId, payload)
    ),
    onSuccess: async (partner) => {
      await invalidatePartners();
      setIsFormOpen(false);
      setPartnerForForm(null);
      toast({
        title: 'Parceiro atualizado',
        description: `"${partner.name}" foi atualizado.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar parceiro',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const suspendPartnerMutation = useMutation({
    mutationFn: ({ partnerId, payload }: { partnerId: number; payload: PartnerSuspendPayload }) => (
      partnersService.suspend(partnerId, payload)
    ),
    onSuccess: async (partner) => {
      await invalidatePartners();
      setPartnerForSuspend(null);
      toast({
        title: 'Parceiro suspenso',
        description: `"${partner.name}" foi suspenso sem apagar historico.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao suspender parceiro',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const deletePartnerMutation = useMutation({
    mutationFn: (partnerId: number) => partnersService.remove(partnerId),
    onSuccess: async () => {
      await invalidatePartners();
      setPartnerForDelete(null);
      setIsDetailOpen(false);
      setDetailPartnerId(null);
      toast({
        title: 'Parceiro removido',
        description: 'Parceiro vazio removido com soft delete.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao remover parceiro',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const inviteStaffMutation = useMutation({
    mutationFn: ({ partnerId, payload }: { partnerId: number; payload: PartnerStaffPayload }) => (
      partnersService.inviteStaff(partnerId, payload)
    ),
    onSuccess: async () => {
      await invalidatePartners();
      setPartnerForStaff(null);
      toast({
        title: 'Membro adicionado',
        description: 'Membro vinculado ao parceiro.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao adicionar membro',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const createGrantMutation = useMutation({
    mutationFn: ({ partnerId, payload }: { partnerId: number; payload: PartnerGrantPayload }) => (
      partnersService.createGrant(partnerId, payload)
    ),
    onSuccess: async () => {
      await invalidatePartners();
      setPartnerForGrant(null);
      toast({
        title: 'Concessao criada',
        description: 'Concessao comercial criada para o evento do parceiro.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao criar concessao',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const resetFilters = () => {
    setSearch('');
    setStatusFilter('all');
    setPlanFilter('');
    setSubscriptionStatusFilter('all');
    setHasActiveEventsFilter('all');
    setHasClientsFilter('all');
    setSortBy('created_at');
    setSortDirection('desc');
    setPage(1);
  };

  const openCreateDialog = () => {
    setFormMode('create');
    setPartnerForForm(null);
    setIsFormOpen(true);
  };

  const openEditDialog = (partner: PartnerListItem) => {
    setFormMode('edit');
    setPartnerForForm(partner);
    setIsFormOpen(true);
  };

  const openDetail = (partner: PartnerListItem) => {
    setDetailPartnerId(partner.id);
    setIsDetailOpen(true);
  };

  const handlePartnerFormSubmit = (payload: PartnerFormPayload) => {
    if (formMode === 'create') {
      createPartnerMutation.mutate(payload);
      return;
    }

    if (!partnerForForm) {
      return;
    }

    updatePartnerMutation.mutate({
      partnerId: partnerForForm.id,
      payload,
    });
  };

  const handleSuspendSubmit = (payload: PartnerSuspendPayload) => {
    if (!partnerForSuspend) {
      return;
    }

    suspendPartnerMutation.mutate({
      partnerId: partnerForSuspend.id,
      payload,
    });
  };

  const handleStaffSubmit = (payload: PartnerStaffPayload) => {
    if (!partnerForStaff) {
      return;
    }

    inviteStaffMutation.mutate({
      partnerId: partnerForStaff.id,
      payload,
    });
  };

  const handleGrantSubmit = (payload: PartnerGrantPayload) => {
    if (!partnerForGrant) {
      return;
    }

    createGrantMutation.mutate({
      partnerId: partnerForGrant.id,
      payload,
    });
  };

  if (!canViewPartners) {
    return (
      <EmptyState
        icon={Users}
        title="Acesso indisponivel"
        description="Sua sessao atual nao possui permissao para visualizar parceiros globais."
      />
    );
  }

  return (
    <>
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Parceiros"
        description={partnersQuery.isLoading ? 'Carregando parceiros...' : `${totalPartners} parceiros encontrados`}
        actions={canManagePartners ? (
          <Button className="gradient-primary border-0" onClick={openCreateDialog}>
            <Plus className="h-4 w-4" />
            Novo parceiro
          </Button>
        ) : undefined}
      />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatsCard title="Total de Parceiros" value={totalPartners} icon={Users} />
        <StatsCard title="Ativos na pagina" value={activePartnersOnPage} icon={TrendingUp} changeType="positive" />
        <StatsCard title="Receita na pagina" value={formatCurrencyFromCents(totalRevenueOnPage)} icon={DollarSign} />
        <StatsCard title="Eventos ativos" value={activeEventsOnPage} icon={Users} />
      </div>

      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
        <Collapsible open={isFiltersOpen} onOpenChange={setIsFiltersOpen}>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2 text-sm font-semibold">
              <SlidersHorizontal className="h-4 w-4 text-primary" />
              Lista administrativa
            </div>

            <CollapsibleTrigger asChild>
              <Button variant="outline" size="sm">
                <SlidersHorizontal className="h-4 w-4" />
                Filtros e ordenacao
              </Button>
            </CollapsibleTrigger>
          </div>

          <CollapsibleContent className="mt-4">
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
              <div className="relative xl:col-span-4">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  placeholder="Buscar por nome, segmento, email ou documento"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  className="pl-9"
                />
              </div>

              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger className="xl:col-span-2" aria-label="Status do parceiro">
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os status</SelectItem>
                  <SelectItem value="active">Ativos</SelectItem>
                  <SelectItem value="inactive">Inativos</SelectItem>
                  <SelectItem value="suspended">Suspensos</SelectItem>
                </SelectContent>
              </Select>

              <Select value={subscriptionStatusFilter} onValueChange={setSubscriptionStatusFilter}>
                <SelectTrigger className="xl:col-span-2" aria-label="Status da assinatura">
                  <SelectValue placeholder="Assinatura" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todas as assinaturas</SelectItem>
                  <SelectItem value="active">Assinatura ativa</SelectItem>
                  <SelectItem value="trialing">Periodo de teste</SelectItem>
                  <SelectItem value="canceled">Cancelada</SelectItem>
                  <SelectItem value="suspended">Suspensa</SelectItem>
                </SelectContent>
              </Select>

              <Input
                aria-label="Codigo do plano"
                placeholder="Plano (codigo)"
                value={planFilter}
                onChange={(event) => setPlanFilter(event.target.value)}
                className="xl:col-span-2"
              />

              <Select value={hasActiveEventsFilter} onValueChange={setHasActiveEventsFilter}>
                <SelectTrigger className="xl:col-span-2" aria-label="Eventos ativos">
                  <SelectValue placeholder="Eventos" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os eventos</SelectItem>
                  <SelectItem value="with_active_events">Com eventos ativos</SelectItem>
                  <SelectItem value="without_active_events">Sem eventos ativos</SelectItem>
                </SelectContent>
              </Select>

              <Select value={hasClientsFilter} onValueChange={setHasClientsFilter}>
                <SelectTrigger className="xl:col-span-2" aria-label="Clientes">
                  <SelectValue placeholder="Clientes" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os clientes</SelectItem>
                  <SelectItem value="with_clients">Com clientes</SelectItem>
                  <SelectItem value="without_clients">Sem clientes</SelectItem>
                </SelectContent>
              </Select>

              <Select value={sortBy} onValueChange={(value) => setSortBy(value as PartnerSortBy)}>
                <SelectTrigger className="xl:col-span-2" aria-label="Ordenar parceiros por">
                  <SelectValue placeholder="Ordenar por" />
                </SelectTrigger>
                <SelectContent>
                  {PARTNER_SORT_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={sortDirection} onValueChange={(value) => setSortDirection(value as SortDirection)}>
                <SelectTrigger className="xl:col-span-2" aria-label="Direcao da ordenacao">
                  <SelectValue placeholder="Direcao" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="desc">Decrescente</SelectItem>
                  <SelectItem value="asc">Crescente</SelectItem>
                </SelectContent>
              </Select>

              <div className="xl:col-span-2">
                <Button variant="outline" className="w-full" onClick={resetFilters}>
                  Limpar filtros
                </Button>
              </div>
            </div>
          </CollapsibleContent>
        </Collapsible>
      </section>

      {partnersQuery.isLoading && partners.length === 0 ? (
        <div className="glass rounded-3xl border border-border/60 px-4 py-16 text-center text-sm text-muted-foreground">
          <div className="flex items-center justify-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando parceiros...
          </div>
        </div>
      ) : partnersQuery.isError ? (
        <div className="glass rounded-3xl border border-destructive/30 px-4 py-16 text-center text-sm text-destructive">
          Nao foi possivel carregar os parceiros agora.
        </div>
      ) : partners.length === 0 ? (
        <div className="glass rounded-3xl border border-border/60">
          <EmptyState
            icon={Users}
            title="Nenhum parceiro encontrado"
            description="Ajuste os filtros para localizar uma organizacao parceira."
          />
        </div>
      ) : (
        <div className="glass rounded-xl overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="border-border/50">
                <TableHead>Parceiro</TableHead>
                <TableHead>Segmento</TableHead>
                <TableHead>Plano</TableHead>
                <TableHead className="hidden md:table-cell">Eventos</TableHead>
                <TableHead className="hidden md:table-cell">Clientes</TableHead>
                <TableHead className="hidden md:table-cell">Receita</TableHead>
                <TableHead className="hidden md:table-cell">Equipe</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-[120px] text-right">Acoes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {partners.map((partner) => (
                <TableRow key={partner.id} className="border-border/30">
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                        {partner.name.slice(0, 2).toUpperCase()}
                      </div>
                      <div className="min-w-0">
                        <p className="truncate font-medium text-sm">{partner.name}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          {partner.email || partner.owner?.email || 'Sem contato'}
                        </p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="text-sm text-muted-foreground">{partner.segment || 'Nao informado'}</TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-1">
                      <Badge variant="outline" className="text-xs">
                        {partner.current_subscription.plan_name || 'Sem plano'}
                      </Badge>
                      <Badge variant="secondary" className="text-xs">
                        {subscriptionStatusLabel(partner.current_subscription.status)}
                      </Badge>
                    </div>
                  </TableCell>
                  <TableCell className="hidden md:table-cell text-sm">
                    {partner.active_events_count} ativos / {partner.events_count} total
                  </TableCell>
                  <TableCell className="hidden md:table-cell text-sm">{partner.clients_count}</TableCell>
                  <TableCell className="hidden md:table-cell text-sm">
                    {formatCurrencyFromCents(partner.revenue.total_cents)}
                  </TableCell>
                  <TableCell className="hidden md:table-cell text-sm">{partner.team_size}</TableCell>
                  <TableCell>
                    <Badge variant="outline" className={`text-xs ${partnerStatusClass(partner.status)}`}>
                      {partnerStatusLabel(partner.status)}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center justify-end gap-2">
                      <Button variant="outline" size="sm" onClick={() => openDetail(partner)}>
                        <Eye className="h-4 w-4" />
                        Detalhe
                      </Button>
                      {canManagePartners ? (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" aria-label={`Acoes de ${partner.name}`}>
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => openEditDialog(partner)}>
                              <Pencil className="mr-2 h-4 w-4" />
                              Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setPartnerForSuspend(partner)}>
                              <PauseCircle className="mr-2 h-4 w-4" />
                              Suspender
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              className="text-destructive focus:text-destructive"
                              onClick={() => setPartnerForDelete(partner)}
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Remover vazio
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {pagination && pagination.last_page > 1 ? (
            <div className="flex items-center justify-between border-t border-border/60 px-4 py-3 text-sm text-muted-foreground">
              <span>
                Pagina {pagination.page} de {pagination.last_page}
              </span>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={pagination.page <= 1 || partnersQuery.isFetching}
                  onClick={() => setPage((current) => Math.max(1, current - 1))}
                >
                  Anterior
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={pagination.page >= pagination.last_page || partnersQuery.isFetching}
                  onClick={() => setPage((current) => current + 1)}
                >
                  Proxima
                </Button>
              </div>
            </div>
          ) : null}
        </div>
      )}
    </motion.div>

    <PartnerDetailSheet
      open={isDetailOpen}
      partnerId={detailPartnerId}
      canManage={canManagePartners}
      onOpenChange={setIsDetailOpen}
      onEdit={openEditDialog}
      onSuspend={setPartnerForSuspend}
      onDelete={setPartnerForDelete}
      onAddStaff={setPartnerForStaff}
      onCreateGrant={setPartnerForGrant}
    />

    <PartnerFormDialog
      open={isFormOpen}
      mode={formMode}
      partner={partnerForForm}
      isSubmitting={createPartnerMutation.isPending || updatePartnerMutation.isPending}
      onOpenChange={setIsFormOpen}
      onSubmit={handlePartnerFormSubmit}
    />

    <PartnerSuspendDialog
      open={!!partnerForSuspend}
      partner={partnerForSuspend}
      isSubmitting={suspendPartnerMutation.isPending}
      onOpenChange={(open) => !open && setPartnerForSuspend(null)}
      onSubmit={handleSuspendSubmit}
    />

    <PartnerStaffDialog
      open={!!partnerForStaff}
      partner={partnerForStaff}
      isSubmitting={inviteStaffMutation.isPending}
      onOpenChange={(open) => !open && setPartnerForStaff(null)}
      onSubmit={handleStaffSubmit}
    />

    <PartnerGrantDialog
      open={!!partnerForGrant}
      partner={partnerForGrant}
      isSubmitting={createGrantMutation.isPending}
      onOpenChange={(open) => !open && setPartnerForGrant(null)}
      onSubmit={handleGrantSubmit}
    />

    <AlertDialog open={!!partnerForDelete} onOpenChange={(open) => !open && setPartnerForDelete(null)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Remover parceiro vazio</AlertDialogTitle>
          <AlertDialogDescription>
            {partnerForDelete
              ? `Voce esta tentando remover "${partnerForDelete.name}". O backend so permite remover parceiro sem historico operacional; caso contrario, use suspensao.`
              : 'Confirme a remocao do parceiro vazio.'}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            onClick={() => {
              if (partnerForDelete) {
                deletePartnerMutation.mutate(partnerForDelete.id);
              }
            }}
          >
            Remover vazio
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
    </>
  );
}

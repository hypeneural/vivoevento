import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import {
  Edit3,
  Loader2,
  MessageCircle,
  MoreHorizontal,
  Plus,
  Search,
  ShieldCheck,
  Sparkles,
  Star,
  Trash2,
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
import { cn } from '@/lib/utils';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';

import { whatsappService } from './api';
import { buildWhatsAppInstancePath, WHATSAPP_SETTINGS_PATH } from './paths';
import { WhatsAppInstanceFormDialog } from './components/WhatsAppInstanceFormDialog';
import type {
  WhatsAppInstanceFormPayload,
  WhatsAppInstanceItem,
  WhatsAppInstanceStatus,
  WhatsAppProviderKey,
} from './types';
import { WHATSAPP_PROVIDER_OPTIONS, WHATSAPP_STATUS_LABELS } from './types';

function formatDateTime(value?: string | null) {
  if (!value) {
    return 'Sem registro';
  }

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function buildStatusClass(status: WhatsAppInstanceStatus) {
  switch (status) {
    case 'connected':
      return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600';
    case 'configured':
      return 'border-blue-500/20 bg-blue-500/10 text-blue-600';
    case 'invalid_credentials':
    case 'error':
      return 'border-destructive/20 bg-destructive/10 text-destructive';
    case 'disconnected':
      return 'border-amber-500/20 bg-amber-500/10 text-amber-600';
    default:
      return 'border-border bg-muted text-muted-foreground';
  }
}

function buildHealthLabel(instance: WhatsAppInstanceItem) {
  if (instance.last_error) {
    return 'Com erro';
  }

  if (instance.last_health_status) {
    return instance.last_health_status;
  }

  return 'Sem checagem';
}

interface ListPaginationProps {
  currentPage: number;
  lastPage: number;
  total: number;
  isFetching: boolean;
  onPageChange: (page: number) => void;
}

function ListPagination({
  currentPage,
  lastPage,
  total,
  isFetching,
  onPageChange,
}: ListPaginationProps) {
  return (
    <div className="flex flex-col gap-3 border-t border-border/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm text-muted-foreground">
        {total} instância{total !== 1 ? 's' : ''} no total
      </p>

      <div className="flex items-center gap-2">
        <span className="text-sm text-muted-foreground">
          Página {currentPage} de {lastPage}
        </span>
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1 || isFetching}
        >
          Anterior
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= lastPage || isFetching}
        >
          Próxima
        </Button>
      </div>
    </div>
  );
}

export default function WhatsAppInstancesPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { can } = useAuth();

  const canView = can('channels.view') || can('channels.manage');
  const canManage = can('channels.manage');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [providerFilter, setProviderFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [activeFilter, setActiveFilter] = useState<string>('all');
  const [favoriteFilter, setFavoriteFilter] = useState<string>('all');
  const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedInstance, setSelectedInstance] = useState<WhatsAppInstanceItem | null>(null);
  const [instanceToDelete, setInstanceToDelete] = useState<WhatsAppInstanceItem | null>(null);

  const deferredSearch = useDeferredValue(search);

  useEffect(() => {
    setPage(1);
  }, [activeFilter, deferredSearch, favoriteFilter, providerFilter, statusFilter]);

  const filters = useMemo(() => ({
    search: deferredSearch || undefined,
    provider_key: providerFilter === 'all' ? undefined : providerFilter as WhatsAppProviderKey,
    status: statusFilter === 'all' ? undefined : statusFilter as WhatsAppInstanceStatus,
    is_active: activeFilter === 'all' ? undefined : activeFilter === 'active',
    is_default: favoriteFilter === 'all' ? undefined : favoriteFilter === 'favorite',
    page,
    per_page: 12,
  }), [activeFilter, deferredSearch, favoriteFilter, page, providerFilter, statusFilter]);

  const instancesQuery = useQuery({
    queryKey: queryKeys.whatsapp.list(filters),
    queryFn: () => whatsappService.list(filters),
    enabled: canView,
    placeholderData: keepPreviousData,
  });

  const createMutation = useMutation({
    mutationFn: (payload: WhatsAppInstanceFormPayload) => whatsappService.create(payload),
    onSuccess: async (instance) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.all() });
      setIsDialogOpen(false);
      setSelectedInstance(null);

      toast({
        title: 'Instância criada',
        description: `"${instance.name}" foi cadastrada com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao criar instância',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ instanceId, payload }: { instanceId: number; payload: WhatsAppInstanceFormPayload }) => (
      whatsappService.update(instanceId, payload)
    ),
    onSuccess: async (instance) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.all() });
      setIsDialogOpen(false);
      setSelectedInstance(null);

      toast({
        title: 'Instância atualizada',
        description: `"${instance.name}" foi atualizada com sucesso.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar instância',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (instanceId: number) => whatsappService.remove(instanceId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.all() });

      const deletedName = instanceToDelete?.name;
      setInstanceToDelete(null);

      toast({
        title: 'Instância removida',
        description: deletedName
          ? `"${deletedName}" foi removida do painel.`
          : 'A instância foi removida com sucesso.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao remover instância',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const favoriteMutation = useMutation({
    mutationFn: (instance: WhatsAppInstanceItem) => whatsappService.setDefault(instance.id),
    onSuccess: async (instance) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.all() });

      toast({
        title: 'Instância favorita atualizada',
        description: `"${instance.name}" agora é a instância padrão da organização.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar favorita',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const instances = instancesQuery.data?.data ?? [];
  const pagination = instancesQuery.data?.meta;
  const total = pagination?.total ?? instances.length;

  const summary = useMemo(() => {
    const connectedCount = instances.filter((instance) => instance.status === 'connected').length;
    const defaultInstance = instances.find((instance) => instance.is_default) ?? null;
    const providerCount = new Set(instances.map((instance) => instance.provider_key)).size;

    return {
      connectedCount,
      defaultInstance,
      providerCount,
      visibleCount: instances.length,
    };
  }, [instances]);

  const openCreateDialog = () => {
    setDialogMode('create');
    setSelectedInstance(null);
    setIsDialogOpen(true);
  };

  const openEditDialog = (instance: WhatsAppInstanceItem) => {
    setDialogMode('edit');
    setSelectedInstance(instance);
    setIsDialogOpen(true);
  };

  const handleFormSubmit = (payload: WhatsAppInstanceFormPayload) => {
    if (dialogMode === 'create') {
      createMutation.mutate(payload);
      return;
    }

    if (!selectedInstance) {
      return;
    }

    updateMutation.mutate({
      instanceId: selectedInstance.id,
      payload,
    });
  };

  const resetFilters = () => {
    setSearch('');
    setProviderFilter('all');
    setStatusFilter('all');
    setActiveFilter('all');
    setFavoriteFilter('all');
    setPage(1);
  };

  if (!canView) {
    return (
      <EmptyState
        icon={MessageCircle}
        title="Acesso indisponível"
        description="Sua sessão atual não possui permissão para visualizar instâncias de WhatsApp."
      />
    );
  }

  return (
    <>
      <div className="space-y-5">
        <PageHeader
          title="WhatsApp"
          description="Gerencie provedores, conexão e a instância favorita usada como padrão."
          actions={canManage ? (
            <Button className="gradient-primary border-0" onClick={openCreateDialog}>
              <Plus className="h-4 w-4" />
              Nova instância
            </Button>
          ) : undefined}
        />

        <section className="glass overflow-hidden rounded-3xl border border-border/60">
          <div className="grid gap-5 p-5 xl:grid-cols-[1.3fr_0.9fr]">
            <div className="space-y-4">
              <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                <Sparkles className="h-4 w-4" />
                Configurações / Canais
              </div>

              <div>
                <h2 className="text-xl font-semibold tracking-tight">Central de instâncias WhatsApp</h2>
                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                  Cadastre Z-API ou Evolution, conecte por QR Code e marque uma instância como favorita para virar o padrão operacional da organização.
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <Badge variant="outline" className="border-primary/20 bg-primary/10 text-primary">
                  URL: {WHATSAPP_SETTINGS_PATH}
                </Badge>
                <Badge variant="outline">CRUD completo</Badge>
                <Badge variant="outline">QR + pareamento</Badge>
                <Badge variant="outline">{summary.providerCount || 0} provider(es) nesta página</Badge>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
              <div className="rounded-3xl border border-border/60 bg-background/75 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Resultados</p>
                <p className="mt-2 text-2xl font-semibold">{total}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {summary.visibleCount} visíveis nesta página
                </p>
              </div>

              <div className="rounded-3xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-emerald-700">Conectadas</p>
                <p className="mt-2 text-2xl font-semibold text-emerald-700">{summary.connectedCount}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  Sessões prontas para uso
                </p>
              </div>

              <div className="rounded-3xl border border-amber-500/20 bg-amber-500/5 p-4">
                <div className="flex items-center gap-2">
                  <Star className="h-4 w-4 text-amber-600" />
                  <p className="text-[11px] uppercase tracking-[0.16em] text-amber-700">Favorita</p>
                </div>
                <p className="mt-2 text-base font-semibold">
                  {summary.defaultInstance?.name || 'Ainda não definida'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {summary.defaultInstance
                    ? 'Usada como instância padrão da organização.'
                    : 'Use a ação de favoritar para escolher a instância padrão.'}
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
          <div className="flex items-center gap-2 text-sm font-semibold">
            <ShieldCheck className="h-4 w-4 text-primary" />
            Filtros operacionais
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
            <div className="relative xl:col-span-3">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por nome, slug, telefone ou id externo"
                className="pl-9"
              />
            </div>

            <Select value={providerFilter} onValueChange={setProviderFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Provider" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os providers</SelectItem>
                {WHATSAPP_PROVIDER_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os status</SelectItem>
                {Object.entries(WHATSAPP_STATUS_LABELS).map(([value, label]) => (
                  <SelectItem key={value} value={value}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={activeFilter} onValueChange={setActiveFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Operação" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas</SelectItem>
                <SelectItem value="active">Ativas</SelectItem>
                <SelectItem value="inactive">Inativas</SelectItem>
              </SelectContent>
            </Select>

            <Select value={favoriteFilter} onValueChange={setFavoriteFilter}>
              <SelectTrigger className="xl:col-span-2">
                <SelectValue placeholder="Favorita" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas</SelectItem>
                <SelectItem value="favorite">Somente favorita</SelectItem>
                <SelectItem value="others">Somente não favoritas</SelectItem>
              </SelectContent>
            </Select>

            <div className="xl:col-span-1">
              <Button variant="outline" className="w-full" onClick={resetFilters}>
                Limpar
              </Button>
            </div>
          </div>
        </section>

        {instancesQuery.isLoading && instances.length === 0 ? (
          <div className="glass rounded-3xl border border-border/60 px-4 py-16 text-center text-sm text-muted-foreground">
            <div className="flex items-center justify-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              Carregando instâncias...
            </div>
          </div>
        ) : instancesQuery.isError ? (
          <div className="glass rounded-3xl border border-destructive/30 px-4 py-16 text-center text-sm text-destructive">
            Não foi possível carregar as instâncias agora.
          </div>
        ) : instances.length === 0 ? (
          <div className="glass rounded-3xl border border-border/60">
            <EmptyState
              icon={MessageCircle}
              title="Nenhuma instância encontrada"
              description="Cadastre uma instância ou ajuste os filtros para continuar."
              action={canManage ? <Button onClick={openCreateDialog}>Cadastrar instância</Button> : undefined}
            />
          </div>
        ) : (
          <>
            <div className="grid gap-3 lg:hidden">
              {instances.map((instance) => {
                const detailPath = buildWhatsAppInstancePath(instance.id);

                return (
                  <article
                    key={instance.id}
                    className={cn(
                      'glass rounded-3xl border p-4',
                      instance.is_default ? 'border-primary/30 bg-primary/5' : 'border-border/60',
                    )}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h2 className="truncate text-base font-semibold">{instance.name}</h2>
                          {instance.is_default ? (
                            <Badge variant="outline" className="border-primary/20 bg-primary/10 text-primary">
                              <Star className="mr-1 h-3 w-3" />
                              Favorita
                            </Badge>
                          ) : null}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">{instance.instance_name}</p>
                      </div>

                      <Badge variant="outline" className={buildStatusClass(instance.status)}>
                        {WHATSAPP_STATUS_LABELS[instance.status]}
                      </Badge>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                      <Badge variant="secondary">{instance.provider.label}</Badge>
                      <Badge
                        variant="outline"
                        className={instance.is_active ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600' : ''}
                      >
                        {instance.is_active ? 'Ativa' : 'Inativa'}
                      </Badge>
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                      <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Telefone</p>
                        <p className="mt-1 font-medium">{instance.formatted_phone || instance.phone_number || 'Não informado'}</p>
                      </div>
                      <div className="rounded-2xl border border-border/60 bg-background/70 p-3">
                        <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Saúde</p>
                        <p className="mt-1 font-medium">{buildHealthLabel(instance)}</p>
                      </div>
                    </div>

                    <p className="mt-4 text-xs text-muted-foreground">
                      Atualizada em {formatDateTime(instance.updated_at || instance.last_status_sync_at)}
                    </p>

                    <div className="mt-4 flex flex-wrap gap-2">
                      <Button asChild size="sm" className="gradient-primary border-0">
                        <Link to={detailPath}>Abrir painel</Link>
                      </Button>

                      {canManage && !instance.is_default ? (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => favoriteMutation.mutate(instance)}
                          disabled={favoriteMutation.isPending}
                        >
                          <Star className="h-4 w-4" />
                          Favoritar
                        </Button>
                      ) : null}

                      {canManage ? (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button size="sm" variant="outline">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => openEditDialog(instance)}>
                              <Edit3 className="mr-2 h-4 w-4" />
                              Editar
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              className="text-destructive focus:text-destructive"
                              onClick={() => setInstanceToDelete(instance)}
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Remover
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      ) : null}
                    </div>
                  </article>
                );
              })}
            </div>

            <div className="glass hidden overflow-hidden rounded-3xl border border-border/60 lg:block">
              <Table>
                <TableHeader>
                  <TableRow className="border-border/50">
                    <TableHead>Instância</TableHead>
                    <TableHead>Provider</TableHead>
                    <TableHead>Operação</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Atualização</TableHead>
                    <TableHead className="w-[240px] text-right">Ações</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {instances.map((instance) => {
                    const detailPath = buildWhatsAppInstancePath(instance.id);

                    return (
                      <TableRow
                        key={instance.id}
                        className={cn(
                          'border-border/30 hover:bg-muted/20',
                          instance.is_default && 'bg-primary/5',
                        )}
                      >
                        <TableCell>
                          <div className="min-w-0">
                            <div className="flex items-center gap-2">
                              <p className="truncate font-medium">{instance.name}</p>
                              {instance.is_default ? (
                                <Badge variant="outline" className="border-primary/20 bg-primary/10 text-primary">
                                  <Star className="mr-1 h-3 w-3" />
                                  Favorita
                                </Badge>
                              ) : null}
                            </div>
                            <p className="truncate text-xs text-muted-foreground">{instance.instance_name}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">{instance.provider.label}</Badge>
                            <Badge
                              variant="outline"
                              className={instance.is_active ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600' : ''}
                            >
                              {instance.is_active ? 'Ativa' : 'Inativa'}
                            </Badge>
                          </div>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          <p>{instance.formatted_phone || instance.phone_number || 'Não informado'}</p>
                          <p className="mt-1 text-xs">{buildHealthLabel(instance)}</p>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className={buildStatusClass(instance.status)}>
                            {WHATSAPP_STATUS_LABELS[instance.status]}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDateTime(instance.updated_at || instance.last_status_sync_at)}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center justify-end gap-2">
                            <Button asChild size="sm" className="gradient-primary border-0">
                              <Link to={detailPath}>Abrir painel</Link>
                            </Button>

                            {canManage && !instance.is_default ? (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => favoriteMutation.mutate(instance)}
                                disabled={favoriteMutation.isPending}
                              >
                                <Star className="h-4 w-4" />
                                Favoritar
                              </Button>
                            ) : null}

                            {canManage ? (
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <Button variant="ghost" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                  <DropdownMenuItem onClick={() => openEditDialog(instance)}>
                                    <Edit3 className="mr-2 h-4 w-4" />
                                    Editar
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => setInstanceToDelete(instance)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Remover
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            ) : null}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>

              {pagination ? (
                <ListPagination
                  currentPage={pagination.page}
                  lastPage={pagination.last_page}
                  total={pagination.total}
                  isFetching={instancesQuery.isFetching}
                  onPageChange={setPage}
                />
              ) : null}
            </div>

            {pagination ? (
              <div className="glass rounded-3xl border border-border/60 lg:hidden">
                <ListPagination
                  currentPage={pagination.page}
                  lastPage={pagination.last_page}
                  total={pagination.total}
                  isFetching={instancesQuery.isFetching}
                  onPageChange={setPage}
                />
              </div>
            ) : null}
          </>
        )}
      </div>

      <WhatsAppInstanceFormDialog
        open={isDialogOpen}
        mode={dialogMode}
        instance={selectedInstance}
        isSubmitting={createMutation.isPending || updateMutation.isPending}
        onOpenChange={setIsDialogOpen}
        onSubmit={handleFormSubmit}
      />

      <AlertDialog open={!!instanceToDelete} onOpenChange={(open) => !open && setInstanceToDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover instância</AlertDialogTitle>
            <AlertDialogDescription>
              {instanceToDelete
                ? `Você está removendo "${instanceToDelete.name}". Esta ação apaga o cadastro da instância no painel.`
                : 'Confirme a remoção da instância.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={() => {
                if (!instanceToDelete) {
                  return;
                }

                deleteMutation.mutate(instanceToDelete.id);
              }}
            >
              Remover instância
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

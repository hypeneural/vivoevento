import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  Edit3,
  Loader2,
  MessageCircle,
  ShieldCheck,
  Smartphone,
  Star,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/app/providers/AuthProvider';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';

import { whatsappService } from './api';
import { WHATSAPP_SETTINGS_PATH } from './paths';
import { WhatsAppConnectionPanel } from './components/WhatsAppConnectionPanel';
import { WhatsAppInstanceFormDialog } from './components/WhatsAppInstanceFormDialog';
import { WhatsAppRemoteExplorerPanel } from './components/WhatsAppRemoteExplorerPanel';
import type {
  WhatsAppInstanceFormPayload,
  WhatsAppInstanceItem,
  WhatsAppInstanceStatus,
} from './types';
import { WHATSAPP_STATUS_LABELS } from './types';

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

function buildConfigEntries(instance: WhatsAppInstanceItem) {
  return [
    { label: 'Provider', value: instance.provider.label },
    { label: 'Instance ID', value: instance.provider_config.instance_id },
    { label: 'Base URL', value: instance.provider_config.base_url },
    {
      label: 'Client token',
      value: instance.provider_config.client_token_configured
        ? instance.provider_config.client_token_masked || 'Configurado'
        : null,
    },
    { label: 'Server URL', value: instance.provider_config.server_url },
    { label: 'Auth type', value: instance.provider_config.auth_type },
    {
      label: 'API key',
      value: instance.provider_config.api_key_configured
        ? instance.provider_config.api_key_masked || 'Configurada'
        : null,
    },
    { label: 'Integracao', value: instance.provider_config.integration },
    { label: 'Instancia externa', value: instance.provider_config.external_instance_name },
    {
      label: 'Token da instancia',
      value: instance.provider_config.instance_token_configured
        ? instance.provider_config.instance_token_masked || 'Configurado'
        : null,
    },
  ].filter((entry) => entry.value);
}

export default function WhatsAppInstanceDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { can } = useAuth();

  const canView = can('channels.view') || can('channels.manage');
  const canManage = can('channels.manage');

  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const instanceQuery = useQuery({
    queryKey: queryKeys.whatsapp.detail(id ?? 'missing'),
    queryFn: () => whatsappService.get(id as string),
    enabled: canView && !!id,
  });

  const updateMutation = useMutation({
    mutationFn: (payload: WhatsAppInstanceFormPayload) => whatsappService.update(Number(id), payload),
    onSuccess: async (instance) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.detail(instance.id) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.lists() });
      setIsDialogOpen(false);

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

  const favoriteMutation = useMutation({
    mutationFn: (instanceId: number) => whatsappService.setDefault(instanceId),
    onSuccess: async (instance) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.detail(instance.id) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.lists() });

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

  const instance = instanceQuery.data;

  const configEntries = useMemo(() => {
    if (!instance) {
      return [];
    }

    return buildConfigEntries(instance);
  }, [instance]);

  if (!canView) {
    return (
      <EmptyState
        icon={MessageCircle}
        title="Acesso indisponível"
        description="Sua sessão atual não possui permissão para visualizar esta instância."
      />
    );
  }

  if (instanceQuery.isLoading) {
    return (
      <div className="space-y-5">
        <PageHeader title="WhatsApp" description="Carregando detalhes da instância..." />
        <div className="glass rounded-3xl border border-border/60 px-4 py-16 text-center text-sm text-muted-foreground">
          <div className="flex items-center justify-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" />
            Carregando instância...
          </div>
        </div>
      </div>
    );
  }

  if (instanceQuery.isError || !instance) {
    return (
      <div className="space-y-5">
        <PageHeader title="WhatsApp" description="Não foi possível abrir esta instância." />
        <div className="glass rounded-3xl border border-destructive/30">
          <EmptyState
            icon={MessageCircle}
            title="Instância indisponível"
            description="Verifique se a instância ainda existe ou tente novamente em instantes."
            action={(
              <Button asChild variant="outline">
                <Link to={WHATSAPP_SETTINGS_PATH}>Voltar para listagem</Link>
              </Button>
            )}
          />
        </div>
      </div>
    );
  }

  return (
    <>
      <div className="space-y-5">
        <PageHeader
          title={instance.name}
          description="Painel administrativo da instância dentro de Configurações > WhatsApp."
          actions={(
            <>
              <Button asChild variant="outline">
                <Link to={WHATSAPP_SETTINGS_PATH}>
                  <ArrowLeft className="h-4 w-4" />
                  Voltar
                </Link>
              </Button>

              {canManage && !instance.is_default ? (
                <Button
                  variant="outline"
                  onClick={() => favoriteMutation.mutate(instance.id)}
                  disabled={favoriteMutation.isPending}
                >
                  <Star className="h-4 w-4" />
                  Favoritar
                </Button>
              ) : null}

              {canManage ? (
                <Button className="gradient-primary border-0" onClick={() => setIsDialogOpen(true)}>
                  <Edit3 className="h-4 w-4" />
                  Editar
                </Button>
              ) : null}
            </>
          )}
        />

        <section className="glass overflow-hidden rounded-3xl border border-border/60">
          <div className="grid gap-5 p-5 xl:grid-cols-[1.25fr_0.95fr]">
            <div className="space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{instance.provider.label}</Badge>
                <Badge variant="outline" className={buildStatusClass(instance.status)}>
                  {WHATSAPP_STATUS_LABELS[instance.status]}
                </Badge>
                {instance.is_default ? (
                  <Badge variant="outline" className="border-primary/20 bg-primary/10 text-primary">
                    <Star className="mr-1 h-3 w-3" />
                    Favorita da organização
                  </Badge>
                ) : null}
                <Badge
                  variant="outline"
                  className={instance.is_active ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600' : ''}
                >
                  {instance.is_active ? 'Ativa' : 'Inativa'}
                </Badge>
              </div>

              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                  Configurações / WhatsApp / {instance.instance_name}
                </p>
                <h2 className="mt-3 text-xl font-semibold tracking-tight">Operação e conexão da instância</h2>
                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                  Controle credenciais, status, conexão por QR Code e recursos remotos do provider sem expor segredos no painel.
                </p>
              </div>

              {instance.last_error ? (
                <div className="rounded-2xl border border-destructive/20 bg-destructive/5 p-4">
                  <p className="text-sm font-medium text-destructive">Último erro operacional</p>
                  <p className="mt-1 text-sm text-muted-foreground">{instance.last_error}</p>
                </div>
              ) : null}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-3xl border border-border/60 bg-background/75 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Telefone</p>
                <p className="mt-2 font-semibold">{instance.formatted_phone || instance.phone_number || 'Não informado'}</p>
              </div>

              <div className="rounded-3xl border border-border/60 bg-background/75 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">ID externo</p>
                <p className="mt-2 font-semibold">{instance.external_instance_id || 'Não informado'}</p>
              </div>

              <div className="rounded-3xl border border-border/60 bg-background/75 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Última sincronização</p>
                <p className="mt-2 font-semibold">{formatDateTime(instance.last_status_sync_at)}</p>
              </div>

              <div className="rounded-3xl border border-border/60 bg-background/75 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Última checagem</p>
                <p className="mt-2 font-semibold">{formatDateTime(instance.last_health_check_at)}</p>
              </div>
            </div>
          </div>
        </section>

        <section className="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
          <div className="glass rounded-3xl border border-border/60 p-5">
            <div className="flex items-center gap-2">
              <ShieldCheck className="h-4 w-4 text-primary" />
              <h2 className="text-sm font-semibold">Contexto operacional</h2>
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Slug da instância</p>
                <p className="mt-1 font-medium">{instance.instance_name}</p>
              </div>

              <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Webhook</p>
                <p className="mt-1 text-sm text-muted-foreground">{instance.settings.webhook_url || 'Não configurado'}</p>
              </div>

              <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Timeout</p>
                <p className="mt-1 font-medium">
                  {instance.settings.timeout_seconds ? `${instance.settings.timeout_seconds}s` : 'Padrão'}
                </p>
              </div>

              <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
                <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Tags</p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {(instance.settings.tags ?? []).length > 0 ? (
                    instance.settings.tags?.map((tag) => (
                      <Badge key={tag} variant="outline">
                        {tag}
                      </Badge>
                    ))
                  ) : (
                    <span className="text-sm text-muted-foreground">Nenhuma tag configurada.</span>
                  )}
                </div>
              </div>
            </div>

            {instance.notes ? (
              <div className="mt-4 rounded-2xl border border-border/60 bg-background/70 p-4">
                <p className="text-sm font-medium">Observações internas</p>
                <p className="mt-1 text-sm text-muted-foreground">{instance.notes}</p>
              </div>
            ) : null}
          </div>

          <div className="glass rounded-3xl border border-border/60 p-5">
            <div className="flex items-center gap-2">
              <Smartphone className="h-4 w-4 text-primary" />
              <h2 className="text-sm font-semibold">Credenciais mascaradas</h2>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              {configEntries.length > 0 ? (
                configEntries.map((entry) => (
                  <div key={entry.label} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                    <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{entry.label}</p>
                    <p className="mt-1 break-all font-medium">{entry.value}</p>
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-border/60 bg-background/70 p-4 text-sm text-muted-foreground sm:col-span-2">
                  Nenhum detalhe adicional do provider foi retornado.
                </div>
              )}
            </div>
          </div>
        </section>

        <WhatsAppConnectionPanel instance={instance} />
        <WhatsAppRemoteExplorerPanel instance={instance} />
      </div>

      <WhatsAppInstanceFormDialog
        open={isDialogOpen}
        mode="edit"
        instance={instance}
        isSubmitting={updateMutation.isPending}
        onOpenChange={setIsDialogOpen}
        onSubmit={(payload) => updateMutation.mutate(payload)}
      />
    </>
  );
}

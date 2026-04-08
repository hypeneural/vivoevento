import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Camera,
  CheckCircle,
  Clock,
  Edit3,
  ExternalLink,
  Globe,
  Image,
  Link2,
  Loader2,
  Monitor,
  Gamepad2,
  RefreshCw,
  Save,
  Settings,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventCommercialStatus, ApiEventDetail, ApiEventMediaItem } from '@/lib/api-types';
import { resolveSenderBlockExpiration } from '@/lib/sender-blocking';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge, MediaStatusBadge, ChannelBadge } from '@/shared/components/StatusBadges';
import { StatsCard } from '@/shared/components/StatsCard';
import { EVENT_TYPE_LABELS, type EventStatus, type EventType, type MediaChannel, type MediaStatus } from '@/shared/types';
import { usePermissions } from '@/shared/hooks/usePermissions';
import {
  EVENT_COMMERCIAL_MODE_HINTS,
  EVENT_COMMERCIAL_MODE_LABELS,
  EVENT_COMMERCIAL_SCOPE_LABELS,
  EVENT_MODERATION_LABELS,
} from './types';

import {
  deleteEventIntakeBlacklistEntry,
  getEventCommercialStatus,
  getEventDetail,
  listEventMedia,
  regenerateEventPublicIdentifiers,
  upsertEventIntakeBlacklistEntry,
  updateEventPublicIdentifiers,
} from './api';
import { EventContentModerationSettingsCard } from './components/content-moderation/EventContentModerationSettingsCard';
import { EventSenderDirectoryCard } from './components/EventSenderDirectoryCard';
import { EventFaceSearchSettingsCard } from './components/face-search/EventFaceSearchSettingsCard';
import { EventMediaIntelligenceSettingsCard } from './components/media-intelligence/EventMediaIntelligenceSettingsCard';
import { PublicLinkCard } from './components/PublicLinkCard';
import { EventFaceSearchSearchCard } from '@/modules/face-search/components/EventFaceSearchSearchCard';

const MODULE_CARD_CONFIG = [
  { key: 'live', name: 'Galeria ao vivo', icon: Image },
  { key: 'wall', name: 'Telao', icon: Monitor },
  { key: 'play', name: 'Jogos', icon: Gamepad2 },
  { key: 'hub', name: 'Links', icon: Globe },
] as const;

const TAB_LABEL_OVERRIDES: Record<string, string> = {
  wall: 'Telao',
  play: 'Jogos',
  hub: 'Links',
  analytics: 'Relatorios',
};

const TAB_PERMISSION_CHECKS: Record<string, (can: (permission: string) => boolean) => boolean> = {
  overview: () => true,
  uploads: (can) => can('media.view'),
  moderation: (can) => can('media.moderate'),
  gallery: (can) => can('gallery.view') || can('gallery.manage'),
  wall: (can) => can('wall.view') || can('wall.manage'),
  play: (can) => can('play.view') || can('play.manage'),
  hub: (can) => can('hub.view') || can('hub.manage'),
  analytics: (can) => can('analytics.view'),
};

function formatDateRange(event: ApiEventDetail) {
  if (!event.starts_at) return 'Sem data definida';

  const start = new Date(event.starts_at).toLocaleDateString('pt-BR');

  if (!event.ends_at) {
    return start;
  }

  const end = new Date(event.ends_at).toLocaleDateString('pt-BR');
  return start === end ? start : `${start} ate ${end}`;
}

function filterPublished(media: ApiEventMediaItem[]) {
  return media.filter((item) => item.status === 'published' || item.status === 'approved');
}

function formatWallStatus(status?: string | null) {
  switch (status) {
    case 'live':
      return 'Ao vivo';
    case 'paused':
      return 'Pausado';
    case 'stopped':
      return 'Parado';
    case 'expired':
      return 'Encerrado';
    default:
      return 'Nao iniciado';
  }
}

type EventResolvedEntitlements = {
  modules?: Partial<Record<'live' | 'wall' | 'play' | 'hub', boolean>>;
  limits?: {
    retention_days?: number | null;
    max_active_events?: number | null;
    max_photos?: number | null;
  };
  branding?: {
    white_label?: boolean;
    watermark?: boolean;
  };
  source_summary?: Array<{
    source_type?: string | null;
    plan_name?: string | null;
    active?: boolean;
  }>;
};

function formatCommercialSource(source?: {
  source_type?: string | null;
  plan_name?: string | null;
  package_name?: string | null;
  active?: boolean;
}) {
  if (!source?.source_type) return 'Sem origem definida';

  const sourceLabelMap: Record<string, string> = {
    subscription: 'Assinatura da conta',
    event_purchase: 'Compra do evento',
    trial: 'Trial',
    bonus: 'Bonificacao',
    manual_override: 'Override manual',
  };

  const label = sourceLabelMap[source.source_type] || source.source_type;
  if (source.package_name) {
    return `${label} · ${source.package_name}`;
  }

  return source.plan_name ? `${label} · ${source.plan_name}` : label;
}

function formatSubscriptionCoverage(commercialStatus?: ApiEventCommercialStatus | null) {
  const subscription = commercialStatus?.subscription_summary;

  if (!subscription) {
    return 'Sem cobertura da conta';
  }

  return formatCommercialSource({
    source_type: subscription.source_type,
    plan_name: subscription.plan_name,
  });
}

function formatEventActivation(commercialStatus?: ApiEventCommercialStatus | null) {
  const purchase = commercialStatus?.purchase_summary;

  if (purchase) {
    return formatCommercialSource({
      source_type: purchase.source_type,
      plan_name: purchase.plan_name,
      package_name: purchase.package_name,
    });
  }

  const grant = commercialStatus?.grants_summary.find((item) => item.active)
    ?? commercialStatus?.grants_summary[0];

  if (!grant?.source_type || grant.source_type === 'subscription') {
    return 'Sem grant proprio';
  }

  return formatCommercialSource({
    source_type: grant.source_type,
  });
}

function getPrimaryCommercialSource(commercialStatus?: ApiEventCommercialStatus | null) {
  if (commercialStatus?.purchase_summary) {
    return {
      source_type: commercialStatus.purchase_summary.source_type,
      plan_name: commercialStatus.purchase_summary.plan_name,
      package_name: commercialStatus.purchase_summary.package_name,
      active: true,
    };
  }

  const activeGrant = commercialStatus?.grants_summary.find((item) => item.active)
    ?? commercialStatus?.grants_summary[0];

  if (activeGrant?.source_type && activeGrant.source_type !== 'subscription') {
    return {
      source_type: activeGrant.source_type,
      active: activeGrant.active,
    };
  }

  if (commercialStatus?.subscription_summary) {
    return {
      source_type: commercialStatus.subscription_summary.source_type,
      plan_name: commercialStatus.subscription_summary.plan_name,
      active: true,
    };
  }

  return null;
}

export default function EventDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const { can } = usePermissions();

  const [slug, setSlug] = useState('');
  const [uploadSlug, setUploadSlug] = useState('');
  const [activeTab, setActiveTab] = useState('overview');

  const eventQuery = useQuery({
    queryKey: ['event-detail', id],
    enabled: !!id,
    queryFn: () => getEventDetail(id as string),
  });

  const event = eventQuery.data;
  const commercialStatusQuery = useQuery({
    queryKey: ['event-commercial-status', id],
    enabled: !!id,
    queryFn: () => getEventCommercialStatus(id as string),
    staleTime: 60_000,
  });
  const commercialStatus = commercialStatusQuery.data;
  const canSeeMedia = can('media.view');

  const mediaQuery = useQuery({
    queryKey: ['event-media', id],
    enabled: !!id && !!event?.module_flags?.live && canSeeMedia,
    queryFn: () => listEventMedia(id as string, 24),
  });

  useEffect(() => {
    setSlug(event?.public_identifiers?.slug?.value ?? '');
    setUploadSlug(event?.public_identifiers?.upload_slug?.value ?? '');
  }, [
    event?.public_identifiers?.slug?.value,
    event?.public_identifiers?.upload_slug?.value,
  ]);

  const visibleTabs = useMemo(() => {
    if (!event) return [];

    return (event.menu ?? []).filter((item) => {
      const checker = TAB_PERMISSION_CHECKS[item.key];
      return item.visible && (checker ? checker(can) : true);
    });
  }, [can, event]);

  useEffect(() => {
    if (visibleTabs.length === 0) return;

    if (!visibleTabs.some((item) => item.key === activeTab)) {
      setActiveTab(visibleTabs[0].key);
    }
  }, [activeTab, visibleTabs]);

  const saveIdentifiersMutation = useMutation({
    mutationFn: () => updateEventPublicIdentifiers(id as string, {
      slug,
      upload_slug: uploadSlug,
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['event-detail', id] });
      toast({
        title: 'Links atualizados',
        description: 'Os enderecos publicos foram salvos com sucesso.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao salvar',
        description: error.message || 'Nao foi possivel atualizar os enderecos publicos.',
        variant: 'destructive',
      });
    },
  });

  const regenerateIdentifiersMutation = useMutation({
    mutationFn: (fields: Array<'slug' | 'upload_slug' | 'wall_code'>) =>
      regenerateEventPublicIdentifiers(id as string, { fields }),
    onSuccess: async (_, fields) => {
      await queryClient.invalidateQueries({ queryKey: ['event-detail', id] });
      toast({
        title: 'Identificador regenerado',
        description: `Atualizacao concluida para: ${fields.join(', ')}.`,
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao regenerar',
        description: error.message || 'Nao foi possivel regenerar o identificador.',
        variant: 'destructive',
      });
    },
  });

  const senderBlacklistMutation = useMutation({
    mutationFn: (payload: {
      sender: NonNullable<ApiEventDetail['intake_blacklist']>['senders'][number];
      checked: boolean;
      duration: string;
    }) => {
      if (!id) {
        throw new Error('Evento invalido para bloquear remetente.');
      }

      if (payload.checked) {
        return upsertEventIntakeBlacklistEntry(id, {
          identity_type: payload.sender.recommended_identity_type,
          identity_value: payload.sender.recommended_identity_value,
          normalized_phone: payload.sender.recommended_normalized_phone ?? payload.sender.sender_phone ?? null,
          reason: 'Bloqueado pelo detalhe do evento',
          expires_at: resolveSenderBlockExpiration(payload.duration),
          is_active: true,
        });
      }

      if (!payload.sender.blocking_entry_id) {
        throw new Error('Nao existe um bloqueio ativo para remover desse remetente.');
      }

      return deleteEventIntakeBlacklistEntry(id, payload.sender.blocking_entry_id);
    },
    onSuccess: async (_response, payload) => {
      await queryClient.invalidateQueries({ queryKey: ['event-detail', id] });
      toast({
        title: payload.checked ? 'Remetente bloqueado' : 'Remetente desbloqueado',
        description: payload.checked
          ? 'O remetente deixa de abrir sessao e de injetar novas midias no evento.'
          : 'O remetente voltou a ficar apto para enviar novas midias ao evento.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar blacklist do evento',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const eventMedia = mediaQuery.data?.data ?? [];
  const publishedMedia = filterPublished(eventMedia);
  const shareLinks = event?.public_links ? Object.values(event.public_links).filter((link) => link.enabled) : [];
  const resolvedEntitlements = (
    commercialStatus?.resolved_entitlements ?? event?.current_entitlements ?? null
  ) as EventResolvedEntitlements | null;
  const primaryCommercialSource = getPrimaryCommercialSource(commercialStatus);
  const canManageEvent = can('events.update');

  function copyToClipboard(value: string, label: string) {
    navigator.clipboard.writeText(value);
    toast({
      title: 'Link copiado',
      description: `${label} copiado para a area de transferencia.`,
    });
  }

  if (!id) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Evento invalido.
      </div>
    );
  }

  if (eventQuery.isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  if (eventQuery.isError || !event) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-destructive">
        Nao foi possivel carregar os detalhes do evento.
      </div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title={event.title}
        description={`${EVENT_TYPE_LABELS[(event.event_type || 'other') as EventType] || event.event_type} · ${formatDateRange(event)}`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button asChild size="sm">
              <Link to={`/events/${event.id}/edit`}>
                <Edit3 className="mr-1.5 h-3.5 w-3.5" />
                Editar dados
              </Link>
            </Button>
            {event.public_links.gallery.enabled && event.public_links.gallery.url ? (
              <Button variant="outline" size="sm" onClick={() => window.open(event.public_links.gallery.url!, '_blank', 'noopener,noreferrer')}>
                <Link2 className="mr-1.5 h-3.5 w-3.5" />
                Galeria publica
              </Button>
            ) : null}
            {event.public_links.upload.enabled && event.public_links.upload.url ? (
              <Button variant="outline" size="sm" onClick={() => copyToClipboard(event.public_links.upload.url!, 'Controle remoto')}>
                <Camera className="mr-1.5 h-3.5 w-3.5" />
                Copiar envio
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="relative overflow-hidden rounded-3xl border border-white/50 bg-slate-950">
        {event.cover_image_url ? (
          <img src={event.cover_image_url} alt={event.title} className="absolute inset-0 h-full w-full object-cover opacity-30" />
        ) : null}
        <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900/92 to-slate-800/80" />

        <div className="relative grid gap-6 px-5 py-6 md:grid-cols-[1fr_auto] md:px-8">
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary" className="bg-white/10 text-white hover:bg-white/10">
                {event.organization_name || 'Organizacao'}
              </Badge>
              <EventStatusBadge status={(event.status || 'draft') as EventStatus} />
              <Badge variant="outline" className="border-white/20 bg-white/10 text-white">
                {EVENT_COMMERCIAL_MODE_LABELS[event.commercial_mode || 'none']}
              </Badge>
            </div>

            <div className="space-y-1">
              <h1 className="text-2xl font-semibold text-white md:text-3xl">{event.title}</h1>
              <p className="text-sm text-white/75">
                {event.location_name || 'Local a definir'} · {formatDateRange(event)}
              </p>
            </div>

            <p className="max-w-3xl text-sm text-white/75">
              {event.description || 'Sem descricao cadastrada para este evento.'}
            </p>
          </div>

          <div className="grid min-w-[220px] gap-3 sm:grid-cols-2 md:grid-cols-1">
            <div className="rounded-3xl border border-white/15 bg-white/10 p-4 backdrop-blur">
              <p className="text-xs uppercase tracking-[0.16em] text-white/60">Endereco publico</p>
              <p className="mt-2 font-mono text-sm text-white">{event.public_identifiers.slug.value || 'Nao gerado'}</p>
            </div>
            <div className="rounded-3xl border border-white/15 bg-white/10 p-4 backdrop-blur">
              <p className="text-xs uppercase tracking-[0.16em] text-white/60">Endereco de envio</p>
              <p className="mt-2 font-mono text-sm text-white">{event.public_identifiers.upload_slug.value || 'Nao gerado'}</p>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
        <StatsCard title="Fotos recebidas" value={event.stats.media_total} icon={Camera} />
        <StatsCard title="Aprovadas" value={event.stats.media_approved} icon={CheckCircle} />
        <StatsCard title="Pendentes" value={event.stats.media_pending} icon={Clock} />
        <StatsCard title="Publicadas" value={event.stats.media_published} icon={Image} />
        <StatsCard title="Modulos ativos" value={event.stats.active_modules} icon={Settings} />
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatsCard
          title="Modo comercial"
          value={EVENT_COMMERCIAL_MODE_LABELS[event.commercial_mode || 'none']}
          icon={Globe}
          description={EVENT_COMMERCIAL_MODE_HINTS[event.commercial_mode || 'none']}
        />
        <StatsCard
          title="Assinatura da conta"
          value={formatSubscriptionCoverage(commercialStatus)}
          icon={Link2}
          description={commercialStatus?.subscription_summary?.status
            ? `Status ${commercialStatus.subscription_summary.status}`
            : 'Sem cobertura recorrente aplicada a partir da conta.'}
        />
        <StatsCard
          title="Ativacao do evento"
          value={formatEventActivation(commercialStatus)}
          icon={ExternalLink}
          description={event.commercial_mode === 'subscription_covered'
            ? 'Este evento esta ancorado na assinatura da organizacao.'
            : 'Este evento possui ativacao propria ou grant dedicado.'}
        />
        <StatsCard
          title="Origem ativa"
          value={EVENT_COMMERCIAL_SCOPE_LABELS[event.commercial_mode || 'none']}
          icon={Settings}
          description={formatCommercialSource(primaryCommercialSource ?? undefined)}
        />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <StatsCard
          title="Retencao"
          value={resolvedEntitlements?.limits?.retention_days ? `${resolvedEntitlements.limits.retention_days} dias` : 'Padrao'}
          icon={Clock}
          description="Limite final resolvido para este evento."
        />
        <StatsCard
          title="Limite de fotos"
          value={resolvedEntitlements?.limits?.max_photos ?? 'Nao definido'}
          icon={Image}
          description="Capacidade comercial efetiva aplicada ao evento."
        />
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="flex h-auto flex-wrap gap-2 bg-muted/50 p-1">
          {visibleTabs.map((tab) => (
            <TabsTrigger key={tab.key} value={tab.key}>
              {TAB_LABEL_OVERRIDES[tab.key] ?? tab.label}
            </TabsTrigger>
          ))}
        </TabsList>

        <TabsContent value="overview" className="mt-6 space-y-6">
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {MODULE_CARD_CONFIG.map((module) => {
              const Icon = module.icon;
              const active = event.module_flags[module.key];

              return (
                <Card key={module.key} className={active ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-white'}>
                  <CardContent className="flex items-center gap-3 p-4">
                    <div className={`rounded-2xl p-3 ${active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                      <Icon className="h-5 w-5" />
                    </div>
                    <div>
                      <p className="text-sm font-semibold">{module.name}</p>
                      <p className="text-xs text-muted-foreground">{active ? 'Ativado para este evento' : 'Nao ativado'}</p>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>

          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Links publicos e QR Code</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
              {shareLinks.length > 0 ? (
                shareLinks.map((link) => (
                  <PublicLinkCard key={link.key} link={link} onCopy={copyToClipboard} />
                ))
              ) : (
                <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-muted-foreground">
                  Nenhum link publico esta ativo para este evento.
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Gestao de enderecos publicos</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
              <div className="grid gap-4 lg:grid-cols-2">
                <div className="space-y-2">
                  <label className="text-sm font-medium">Endereco publico</label>
                  <Input value={slug} onChange={(evt) => setSlug(evt.target.value)} placeholder="casamento-ana-e-pedro" />
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => regenerateIdentifiersMutation.mutate(['slug'])}
                      disabled={regenerateIdentifiersMutation.isPending}
                    >
                      <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                      Regenerar
                    </Button>
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium">Endereco de envio</label>
                  <Input value={uploadSlug} onChange={(evt) => setUploadSlug(evt.target.value)} placeholder="envio-casamento" />
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => regenerateIdentifiersMutation.mutate(['upload_slug'])}
                      disabled={regenerateIdentifiersMutation.isPending}
                    >
                      <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                      Regenerar
                    </Button>
                  </div>
                </div>
              </div>

              {event.module_flags.wall ? (
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p className="text-sm font-medium">Codigo do telao</p>
                      <p className="mt-1 font-mono text-sm text-muted-foreground">
                        {event.public_identifiers.wall_code.value || 'Nao gerado'}
                      </p>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => regenerateIdentifiersMutation.mutate(['wall_code'])}
                      disabled={regenerateIdentifiersMutation.isPending}
                    >
                      <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                      Regenerar codigo
                    </Button>
                  </div>
                </div>
              ) : null}

              <div className="flex flex-wrap gap-2">
                <Button
                  onClick={() => saveIdentifiersMutation.mutate()}
                  disabled={saveIdentifiersMutation.isPending}
                >
                  {saveIdentifiersMutation.isPending ? (
                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="mr-1.5 h-4 w-4" />
                  )}
                  Salvar enderecos
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Ultimos uploads</CardTitle>
            </CardHeader>
            <CardContent>
              {!canSeeMedia ? (
                <p className="text-sm text-muted-foreground">Seu perfil nao possui acesso para visualizar as midias deste evento.</p>
              ) : mediaQuery.isLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando midias...
                </div>
              ) : eventMedia.length === 0 ? (
                <p className="text-sm text-muted-foreground">Ainda nao existem uploads para este evento.</p>
              ) : (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                  {eventMedia.slice(0, 12).map((media) => (
                    <div key={media.id} className="space-y-2">
                      <div className="overflow-hidden rounded-2xl bg-slate-100">
                        {media.thumbnail_url ? (
                          <img src={media.thumbnail_url} alt={media.caption || media.sender_name} className="h-28 w-full object-cover" />
                        ) : (
                          <div className="flex h-28 items-center justify-center text-xs text-muted-foreground">Sem preview</div>
                        )}
                      </div>
                      <div className="space-y-1">
                        <div className="flex items-center justify-between gap-2">
                          <ChannelBadge channel={media.channel as MediaChannel} />
                          <MediaStatusBadge status={media.status as MediaStatus} />
                        </div>
                        <p className="truncate text-xs text-muted-foreground">{media.sender_name}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="uploads" className="mt-6">
          <div className="space-y-6">
            <Card className="border-white/70 bg-white/90 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Uploads do evento</CardTitle>
              </CardHeader>
              <CardContent>
                {!canSeeMedia ? (
                  <p className="text-sm text-muted-foreground">Seu perfil nao possui acesso a este modulo.</p>
                ) : mediaQuery.isLoading ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando uploads...
                  </div>
                ) : eventMedia.length === 0 ? (
                  <p className="text-sm text-muted-foreground">Nenhuma midia recebida ate o momento.</p>
                ) : (
                  <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                    {eventMedia.map((media) => (
                      <div key={media.id} className="space-y-2">
                        <div className="overflow-hidden rounded-2xl bg-slate-100">
                          {media.thumbnail_url ? (
                            <img src={media.thumbnail_url} alt={media.caption || media.sender_name} className="h-32 w-full object-cover" />
                          ) : (
                            <div className="flex h-32 items-center justify-center text-xs text-muted-foreground">Sem preview</div>
                          )}
                        </div>
                        <div className="space-y-1">
                          <div className="flex items-center justify-between gap-2">
                            <ChannelBadge channel={media.channel as MediaChannel} />
                            <MediaStatusBadge status={media.status as MediaStatus} />
                          </div>
                          <p className="truncate text-xs text-muted-foreground">{media.sender_name}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>

            {event?.intake_blacklist ? (
              <EventSenderDirectoryCard
                eventId={event.id}
                senders={event.intake_blacklist.senders}
                canManageBlacklist={canManageEvent}
                isBusy={(sender) => senderBlacklistMutation.isPending
                  && senderBlacklistMutation.variables?.sender.recommended_identity_type === sender.recommended_identity_type
                  && senderBlacklistMutation.variables?.sender.recommended_identity_value === sender.recommended_identity_value}
                onToggleBlock={(sender, checked, duration) => senderBlacklistMutation.mutate({ sender, checked, duration })}
              />
            ) : null}
          </div>
        </TabsContent>

        <TabsContent value="moderation" className="mt-6">
          <div className="space-y-6">
            <Card className="border-white/70 bg-white/90 shadow-sm">
              <CardContent className="space-y-4 p-6">
                <div className="grid gap-4 md:grid-cols-3">
                  <StatsCard title="Pendentes" value={event.stats.media_pending} icon={Clock} />
                  <StatsCard title="Aprovadas" value={event.stats.media_approved} icon={CheckCircle} />
                  <StatsCard
                    title="Modo"
                    value={EVENT_MODERATION_LABELS[event.moderation_mode as keyof typeof EVENT_MODERATION_LABELS] ?? event.moderation_mode ?? 'Manual'}
                    icon={Settings}
                  />
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
                    <p>
                      Busca por selfie: <span className="font-medium text-foreground">{event.face_search?.enabled ? 'ativada' : 'desligada'}</span>
                    </p>
                    <p className="mt-1">
                      Exposicao publica: <span className="font-medium text-foreground">{event.face_search?.allow_public_selfie_search ? 'permitida' : 'fechada'}</span>
                    </p>
                    <p className="mt-1">
                      Threshold/top K:{' '}
                      <span className="font-medium text-foreground">
                        {event.face_search
                          ? `${event.face_search.search_threshold} · ${event.face_search.top_k}`
                          : '0.5 · 50'}
                      </span>
                    </p>
                  </div>
                  <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
                    <p>
                      Safety salvo: <span className="font-medium text-foreground">{event.content_moderation?.enabled ? 'ativo' : 'desligado'}</span>
                    </p>
                    <p className="mt-1">
                      Provider: <span className="font-medium text-foreground">{event.content_moderation?.provider_key ?? 'openai'}</span>
                    </p>
                  </div>
                  <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
                    <p>
                      VLM salvo: <span className="font-medium text-foreground">{event.media_intelligence?.enabled ? 'ativo' : 'desligado'}</span>
                    </p>
                    <p className="mt-1">
                      Provider/modelo:{' '}
                      <span className="font-medium text-foreground">
                        {event.media_intelligence
                          ? `${event.media_intelligence.provider_key} · ${event.media_intelligence.model_key}`
                          : 'vllm · Qwen/Qwen2.5-VL-3B-Instruct'}
                      </span>
                    </p>
                    <p className="mt-1">
                      Modo:{' '}
                      <span className="font-medium text-foreground">{event.media_intelligence?.mode ?? 'enrich_only'}</span>
                    </p>
                    <p className="mt-1">
                      Resposta automatica:{' '}
                      <span className="font-medium text-foreground">
                        {event.media_intelligence?.reply_text_mode === 'fixed_random'
                          ? 'texto fixo aleatorio'
                          : event.media_intelligence?.reply_text_mode === 'ai'
                            ? 'por IA'
                            : 'desligada'}
                      </span>
                    </p>
                  </div>
                </div>
                <p className="text-sm text-muted-foreground">
                  Safety e configurado por evento e continua separado de `FaceSearch`, mantendo o pipeline aderente ao fast lane e ao heavy lane definidos na arquitetura.
                </p>
              </CardContent>
            </Card>

            <EventContentModerationSettingsCard
              eventId={event.id}
              eventModerationMode={event.moderation_mode}
            />

            <EventFaceSearchSettingsCard
              eventId={event.id}
              eventModerationMode={event.moderation_mode}
            />

            <EventFaceSearchSearchCard
              eventId={event.id}
              enabled={Boolean(event.face_search?.enabled)}
              publicSearchUrl={event.public_links.find_me?.enabled ? event.public_links.find_me.url : null}
            />

            <EventMediaIntelligenceSettingsCard
              eventId={event.id}
              eventModerationMode={event.moderation_mode}
            />
          </div>
        </TabsContent>

        <TabsContent value="gallery" className="mt-6">
          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Galeria publica</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {event.public_links.gallery.enabled ? (
                <PublicLinkCard link={event.public_links.gallery} onCopy={copyToClipboard} />
              ) : null}

              {publishedMedia.length > 0 ? (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
                  {publishedMedia.map((media) => (
                    <div key={media.id} className="overflow-hidden rounded-2xl bg-slate-100">
                      {media.thumbnail_url ? (
                        <img src={media.thumbnail_url} alt={media.caption || media.sender_name} className="h-28 w-full object-cover" />
                      ) : (
                        <div className="flex h-28 items-center justify-center text-xs text-muted-foreground">Sem preview</div>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">Nenhuma midia aprovada/publicada disponivel para a galeria.</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="wall" className="mt-6">
          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardContent className="space-y-4 p-6">
              {event.public_links.wall.enabled ? (
                <PublicLinkCard link={event.public_links.wall} onCopy={copyToClipboard} />
              ) : (
                <p className="text-sm text-muted-foreground">O telao ainda nao esta disponivel para este evento.</p>
              )}

              <div className="grid gap-4 md:grid-cols-3">
                <StatsCard title="Situacao" value={formatWallStatus(event.wall?.status)} icon={Monitor} />
                <StatsCard title="Codigo do telao" value={event.wall?.wall_code || 'Nao gerado'} icon={Settings} />
                <StatsCard title="Link publico" value={event.public_links.wall.enabled ? 'Pronto' : 'Pendente'} icon={Link2} />
              </div>

              <div className="flex flex-wrap gap-2">
                <Button asChild>
                  <Link to={`/events/${event.id}/wall`}>Configurar telao</Link>
                </Button>
                {event.wall?.public_url ? (
                  <Button asChild variant="outline">
                    <a href={event.wall.public_url} target="_blank" rel="noreferrer">
                      <ExternalLink className="mr-1.5 h-4 w-4" />
                      Abrir telao
                    </a>
                  </Button>
                ) : null}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="play" className="mt-6">
          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardContent className="space-y-4 p-6">
              <div className="grid gap-4 md:grid-cols-3">
                <StatsCard title="Jogos ativos" value={event.play?.is_enabled ? 'Sim' : 'Nao'} icon={Gamepad2} />
                <StatsCard title="Jogo da memoria" value={event.play?.memory_enabled ? 'Ativo' : 'Inativo'} icon={Image} />
                <StatsCard title="Quebra-cabeca" value={event.play?.puzzle_enabled ? 'Ativo' : 'Inativo'} icon={Settings} />
              </div>

              {event.public_links.play.enabled ? (
                <PublicLinkCard link={event.public_links.play} onCopy={copyToClipboard} />
              ) : null}

              <div className="flex flex-wrap gap-2">
                <Button asChild>
                  <Link to={`/events/${event.id}/play`}>
                    <Gamepad2 className="mr-1.5 h-4 w-4" />
                    Gerenciar jogos
                  </Link>
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="hub" className="mt-6">
          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardContent className="space-y-4 p-6">
              <div className="grid gap-4 md:grid-cols-2">
                <StatsCard title="Pagina de links ativa" value={event.hub?.is_enabled ? 'Ativa' : 'Inativa'} icon={Globe} />
                <StatsCard title="Acessos visiveis" value={[
                  event.hub?.show_gallery_button ? 'Galeria' : null,
                  event.hub?.show_upload_button ? 'Upload' : null,
                  event.hub?.show_wall_button ? 'Telao' : null,
                  event.hub?.show_play_button ? 'Jogos' : null,
                ].filter(Boolean).length} icon={Link2} />
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="analytics" className="mt-6">
          <Card className="border-white/70 bg-white/90 shadow-sm">
            <CardContent className="space-y-4 p-6">
              <div className="grid gap-4 md:grid-cols-4">
                <StatsCard title="Recebidas" value={event.stats.media_total} icon={Camera} />
                <StatsCard title="Aprovadas" value={event.stats.media_approved} icon={CheckCircle} />
                <StatsCard title="Publicadas" value={event.stats.media_published} icon={Image} />
                <StatsCard title="Pendentes" value={event.stats.media_pending} icon={Clock} />
              </div>
              <p className="text-sm text-muted-foreground">
                O detalhe do evento exibe os indicadores essenciais. Analises mais profundas continuam na tela dedicada.
              </p>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}

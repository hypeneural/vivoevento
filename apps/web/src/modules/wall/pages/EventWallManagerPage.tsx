import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  AlertTriangle,
  ArrowLeft,
  ExternalLink,
  Loader2,
  Pause,
  Play,
  Power,
  RefreshCw,
  RotateCcw,
  Save,
  Square,
  Trash2,
} from 'lucide-react';
import { Link, useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import type {
  ApiWallAdItem,
  ApiWallDiagnosticsSummary,
  ApiWallPlayerCommand,
  ApiWallSelectionPolicy,
  ApiWallSettings,
} from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';
import { PageHeader } from '@/shared/components/PageHeader';

import { getEventDetail } from '@/modules/events/api';
import { EVENT_STATUS_LABELS } from '@/modules/events/types';

import { HelpTooltip } from '../components/WallManagerHelp';
import { WallManagerSection } from '../components/WallManagerSection';
import { WallAdsTab } from '../components/manager/inspector/WallAdsTab';
import { WallAppearanceTab } from '../components/manager/inspector/WallAppearanceTab';
import { WallPlayerDetailsSheet } from '../components/manager/diagnostics/WallPlayerDetailsSheet';
import { WallPlayerRuntimeCard } from '../components/manager/diagnostics/WallPlayerRuntimeCard';
import { WallCommandToolbar } from '../components/manager/layout/WallCommandToolbar';
import { WallInspectorTabs } from '../components/manager/inspector/WallInspectorTabs';
import { WallQueueTab } from '../components/manager/inspector/WallQueueTab';
import { WallRecentMediaDetailsSheet } from '../components/manager/recent/WallRecentMediaDetailsSheet';
import { WallHeroStage } from '../components/manager/stage/WallHeroStage';
import { WallTopInsightsRail } from '../components/manager/top/WallTopInsightsRail';
import {
  fallbackOptions,
  WALL_COOLDOWN_OPTIONS,
  WALL_EVENT_PHASE_OPTIONS,
  WALL_REPLAY_MINUTE_OPTIONS,
  WALL_SELECTION_MODE_OPTIONS,
  WALL_VOLUME_THRESHOLD_OPTIONS,
  WALL_WINDOW_MINUTE_OPTIONS,
} from '../manager-config';
import {
  createEventWallAd,
  deleteEventWallAd,
  getEventWallDiagnostics,
  getEventWallAds,
  getEventWallSettings,
  getWallOptions,
  reorderEventWallAds,
  runEventWallAction,
  runEventWallPlayerCommand,
  simulateEventWall,
  updateEventWallSettings,
  type EventWallAction,
} from '../api';
import { useWallPollingFallback } from '../hooks/useWallPollingFallback';
import { useWallLiveSnapshot } from '../hooks/useWallLiveSnapshot';
import { useWallSelectedMedia } from '../hooks/useWallSelectedMedia';
import { useWallTopInsights } from '../hooks/useWallTopInsights';
import { realtimeLabel, useWallRealtimeSync } from '../hooks/useWallRealtimeSync';
import { wallQueryOptions } from '../wall-query-options';
import {
  applyWallSelectionPreset,
  areWallSettingsEqual,
  buildWallSelectionSummary,
  cloneWallSettings,
  markWallSelectionAsCustom,
  prepareWallSettingsPayload,
  resolveWallSelectionModeOption,
} from '../wall-settings';

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-neutral-500/20 text-neutral-300 border-neutral-500/30',
  live: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
  paused: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
  stopped: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
  expired: 'bg-neutral-500/20 text-neutral-400 border-neutral-500/30',
};

const REALTIME_COLORS: Record<string, string> = {
  connecting: 'border-amber-500/30 bg-amber-500/10 text-amber-700',
  connected: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700',
  disconnected: 'border-slate-500/30 bg-slate-500/10 text-slate-600',
  offline: 'border-rose-500/30 bg-rose-500/10 text-rose-700',
};

type SaveDraftOptions = {
  quiet?: boolean;
};

function formatEventSchedule(startsAt?: string | null, locationName?: string | null) {
  return [
    startsAt ? new Date(startsAt).toLocaleString('pt-BR') : null,
    locationName,
  ].filter(Boolean).join(' - ') || 'Sem agenda definida';
}

function sortWallAds(ads: ApiWallAdItem[]): ApiWallAdItem[] {
  return [...ads].sort((left, right) => left.position - right.position);
}

function moveWallAd(ads: ApiWallAdItem[], adId: number, direction: -1 | 1): ApiWallAdItem[] | null {
  const orderedAds = sortWallAds(ads);
  const currentIndex = orderedAds.findIndex((item) => item.id === adId);

  if (currentIndex < 0) {
    return null;
  }

  const nextIndex = currentIndex + direction;
  if (nextIndex < 0 || nextIndex >= orderedAds.length) {
    return null;
  }

  const nextAds = [...orderedAds];
  const [moved] = nextAds.splice(currentIndex, 1);
  nextAds.splice(nextIndex, 0, moved);

  return nextAds.map((item, index) => ({
    ...item,
    position: index,
  }));
}

function isVideoAdFile(file: File): boolean {
  if (file.type.startsWith('video/')) {
    return true;
  }

  return /\.mp4$/i.test(file.name);
}

function clampIntegerInput(value: string | number | undefined, fallback: number, min: number, max: number) {
  const parsed = typeof value === 'number' ? value : Number(value);

  if (!Number.isFinite(parsed)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.trunc(parsed)));
}

export default function EventWallManagerPage() {
  const { id } = useParams<{ id: string }>();
  const eventId = id ?? '';
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const [draft, setDraft] = useState<ApiWallSettings | null>(null);
  const [copied, setCopied] = useState(false);
  const [simulationDraft, setSimulationDraft] = useState<ApiWallSettings | null>(null);
  const [simulationFingerprint, setSimulationFingerprint] = useState('');
  const [selectedAdFile, setSelectedAdFile] = useState<File | null>(null);
  const [selectedAdDuration, setSelectedAdDuration] = useState('10');
  const [isRecentDetailsOpen, setIsRecentDetailsOpen] = useState(false);
  const [selectedPlayerId, setSelectedPlayerId] = useState<string | null>(null);
  const [isPlayerDetailsOpen, setIsPlayerDetailsOpen] = useState(false);
  const [activeStageTab, setActiveStageTab] = useState<'live' | 'upcoming'>('live');
  const [activeInspectorTab, setActiveInspectorTab] = useState<'queue' | 'appearance' | 'ads'>('queue');
  const adFileInputRef = useRef<HTMLInputElement | null>(null);
  const realtimeState = useWallRealtimeSync(eventId);
  const pollingFallback = useWallPollingFallback(realtimeState);

  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventDetail(eventId),
    ...wallQueryOptions.event,
    refetchInterval: pollingFallback.eventIntervalMs,
  });

  const settingsQuery = useQuery({
    queryKey: queryKeys.wall.settings(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallSettings(eventId),
    ...wallQueryOptions.settings,
    refetchInterval: pollingFallback.settingsIntervalMs,
  });

  const optionsQuery = useQuery({
    queryKey: queryKeys.wall.options(),
    queryFn: getWallOptions,
    ...wallQueryOptions.options,
  });

  const diagnosticsQuery = useQuery({
    queryKey: queryKeys.wall.diagnostics(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallDiagnostics(eventId),
    ...wallQueryOptions.diagnostics,
    refetchInterval: pollingFallback.diagnosticsIntervalMs,
  });

  const adsQuery = useQuery({
    queryKey: queryKeys.wall.ads(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallAds(eventId),
  });
  const insightsQuery = useWallTopInsights(eventId, {
    refetchInterval: pollingFallback.insightsIntervalMs,
  });
  const liveSnapshotQuery = useWallLiveSnapshot(eventId, {
    refetchInterval: pollingFallback.liveSnapshotIntervalMs,
  });

  useEffect(() => {
    if (settingsQuery.data?.settings) {
      setDraft(cloneWallSettings(settingsQuery.data.settings));
    }
  }, [settingsQuery.data]);

  const saveMutation = useMutation({
    mutationFn: (payload: ApiWallSettings) => updateEventWallSettings(eventId, payload),
  });

  const actionMutation = useMutation({
    mutationFn: (action: EventWallAction) => runEventWallAction(eventId, action),
  });

  const playerCommandMutation = useMutation({
    mutationFn: ({ command, reason }: { command: ApiWallPlayerCommand; reason?: string | null }) => (
      runEventWallPlayerCommand(eventId, command, reason)
    ),
  });

  const uploadAdMutation = useMutation({
    mutationFn: ({
      file,
      durationSeconds,
    }: {
      file: File;
      durationSeconds?: number | null;
    }) => createEventWallAd(eventId, { file, durationSeconds }),
  });

  const deleteAdMutation = useMutation({
    mutationFn: (adId: number) => deleteEventWallAd(eventId, adId),
  });

  const reorderAdsMutation = useMutation({
    mutationFn: (order: number[]) => reorderEventWallAds(eventId, order),
  });

  const event = eventQuery.data;
  const settings = settingsQuery.data;
  const persistedSettings = settings?.settings ?? null;
  const wallSettings = draft ?? persistedSettings ?? null;
  const options = optionsQuery.data ?? fallbackOptions;
  const selectionModes = options.selection_modes?.length
    ? options.selection_modes
    : WALL_SELECTION_MODE_OPTIONS;
  const eventPhases = options.event_phases?.length
    ? options.event_phases
    : WALL_EVENT_PHASE_OPTIONS;
  const wallAds = useMemo(
    () => sortWallAds(adsQuery.data ?? []),
    [adsQuery.data],
  );
  const selectedAdIsVideo = selectedAdFile ? isVideoAdFile(selectedAdFile) : false;

  const status = settings?.status ?? 'draft';
  const isLive = status === 'live';
  const isPaused = status === 'paused';
  const isTerminal = status === 'expired' || status === 'stopped';
  const isBusy =
    saveMutation.isPending
    || actionMutation.isPending
    || playerCommandMutation.isPending
    || uploadAdMutation.isPending
    || deleteAdMutation.isPending
    || reorderAdsMutation.isPending;
  const hasUnsavedChanges = useMemo(
    () => !areWallSettingsEqual(draft, persistedSettings),
    [draft, persistedSettings],
  );

  const headerDescription = useMemo(() => {
    if (!event) return 'Carregando dados do evento e do telao.';

    return [
      EVENT_STATUS_LABELS[event.status] ?? event.status,
      event.location_name,
    ].filter(Boolean).join(' - ');
  }, [event]);

  const selectionSummary = useMemo(
    () => (wallSettings ? buildWallSelectionSummary(wallSettings, selectionModes) : ''),
    [selectionModes, wallSettings],
  );
  const normalizedWallSettings = useMemo(
    () => (wallSettings ? prepareWallSettingsPayload(cloneWallSettings(wallSettings)) : null),
    [wallSettings],
  );
  const liveSimulationFingerprint = useMemo(
    () => (normalizedWallSettings ? JSON.stringify(normalizedWallSettings) : ''),
    [normalizedWallSettings],
  );

  useEffect(() => {
    if (!normalizedWallSettings || !liveSimulationFingerprint) {
      setSimulationDraft(null);
      setSimulationFingerprint('');
      return;
    }

    const timer = window.setTimeout(() => {
      setSimulationDraft(normalizedWallSettings);
      setSimulationFingerprint(liveSimulationFingerprint);
    }, 650);

    return () => window.clearTimeout(timer);
  }, [liveSimulationFingerprint, normalizedWallSettings]);

  const simulationQuery = useQuery({
    queryKey: queryKeys.wall.simulation(eventId, simulationFingerprint || 'draft'),
    enabled: eventId !== '' && simulationDraft !== null && simulationFingerprint !== '',
    queryFn: () => simulateEventWall(eventId, simulationDraft as ApiWallSettings),
  });

  const diagnosticsSummary = diagnosticsQuery.data?.summary ?? settings?.diagnostics_summary ?? null;
  const diagnosticsPlayers = diagnosticsQuery.data?.players ?? [];
  const selectedPlayer = diagnosticsPlayers.find((player) => player.player_instance_id === selectedPlayerId) ?? null;
  const insights = insightsQuery.data ?? null;
  const insightsRecentItems = insights?.recentItems ?? [];
  const liveSnapshot = liveSnapshotQuery.data ?? null;
  const simulationSummary = simulationQuery.data?.summary ?? null;
  const simulationPreview = simulationQuery.data?.sequence_preview ?? [];
  const simulationExplanation = simulationQuery.data?.explanation ?? [];
  const isSimulationDraftPending = Boolean(
    liveSimulationFingerprint
    && simulationFingerprint
    && liveSimulationFingerprint !== simulationFingerprint,
  );
  const {
    selectedMediaId,
    selectedMedia,
    selectMedia,
  } = useWallSelectedMedia(insightsRecentItems);

  function handleSelectRecentMedia(mediaId: string) {
    selectMedia(mediaId);
  }

  function handleOpenRecentMediaDetails(mediaId: string) {
    selectMedia(mediaId);
    setIsRecentDetailsOpen(true);
  }

  function updateDraft<K extends keyof ApiWallSettings>(key: K, value: ApiWallSettings[K]) {
    setDraft((current) => (current ? { ...current, [key]: value } : current));
  }

  function updateSelectionPolicy<K extends keyof ApiWallSelectionPolicy>(
    key: K,
    value: ApiWallSelectionPolicy[K],
  ) {
    setDraft((current) => {
      if (!current) {
        return current;
      }

      return markWallSelectionAsCustom(current, { [key]: value });
    });
  }

  function handleSelectionModeChange(value: string) {
    setDraft((current) => {
      if (!current) {
        return current;
      }

      const preset = resolveWallSelectionModeOption(value as ApiWallSettings['selection_mode'], selectionModes);
      return applyWallSelectionPreset({
        ...current,
        selection_mode: value as ApiWallSettings['selection_mode'],
      }, preset);
    });
  }

  function resetDraft() {
    if (!persistedSettings) return;
    setDraft(cloneWallSettings(persistedSettings));
  }

  async function saveDraft(options: SaveDraftOptions = {}) {
    if (!draft) return false;

    try {
      const payload = await saveMutation.mutateAsync(prepareWallSettingsPayload(draft));
      setDraft(cloneWallSettings(payload.settings));
      queryClient.setQueryData(queryKeys.wall.settings(eventId), payload);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) }),
      ]);

      if (!options.quiet) {
        toast({
          title: 'Telao atualizado',
          description: 'As alteracoes foram salvas e enviadas para a exibicao.',
        });
      }

      return true;
    } catch (error) {
      await queryClient.invalidateQueries({ queryKey: queryKeys.wall.settings(eventId) });

      if (!options.quiet) {
        toast({
          title: 'Falha ao salvar',
          description: error instanceof Error ? error.message : 'Nao foi possivel salvar as alteracoes.',
          variant: 'destructive',
        });
      }

      return false;
    }
  }

  async function handleAction(action: EventWallAction) {
    if (hasUnsavedChanges) {
      const saved = await saveDraft({ quiet: true });
      if (!saved) {
        toast({
          title: 'Salve as alteracoes primeiro',
          description: 'Nao foi possivel salvar os ajustes antes de executar esta acao.',
          variant: 'destructive',
        });
        return;
      }
    }

    try {
      const payload = await actionMutation.mutateAsync(action);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.settings(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(eventId) }),
      ]);

      toast({
        title: 'Telao atualizado',
        description: payload.message,
      });
    } catch (error) {
      toast({
        title: 'Falha na acao',
        description: error instanceof Error ? error.message : 'Nao foi possivel executar esta acao.',
        variant: 'destructive',
      });
    }
  }

  async function handlePlayerCommand(command: ApiWallPlayerCommand, reason?: string) {
    try {
      const payload = await playerCommandMutation.mutateAsync({
        command,
        reason: reason ?? null,
      });

      toast({
        title: 'Comando enviado para a tela',
        description: payload.message,
      });
    } catch (error) {
      toast({
        title: 'Falha ao enviar comando',
        description: error instanceof Error ? error.message : 'Nao foi possivel enviar o comando para a tela.',
        variant: 'destructive',
      });
    }
  }

  function copyWallCode() {
    if (!settings?.wall_code) return;

    void navigator.clipboard.writeText(settings.wall_code);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  }

  function openScreen() {
    if (!settings?.public_url) return;
    window.open(settings.public_url, '_blank', 'noopener,noreferrer');
  }

  function resetAdUploadForm() {
    setSelectedAdFile(null);
    setSelectedAdDuration('10');

    if (adFileInputRef.current) {
      adFileInputRef.current.value = '';
    }
  }

  async function handleAdUpload() {
    if (!selectedAdFile) {
      toast({
        title: 'Selecione um arquivo',
        description: 'Escolha uma imagem ou video antes de enviar o anuncio.',
        variant: 'destructive',
      });
      return;
    }

    try {
      const createdAd = await uploadAdMutation.mutateAsync({
        file: selectedAdFile,
        durationSeconds: selectedAdIsVideo
          ? null
          : clampIntegerInput(selectedAdDuration, 10, 3, 120),
      });

      queryClient.setQueryData<ApiWallAdItem[]>(
        queryKeys.wall.ads(eventId),
        (current) => sortWallAds([...(current ?? []), createdAd]),
      );
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.ads(eventId) });

      resetAdUploadForm();

      toast({
        title: 'Anuncio enviado',
        description: 'O patrocinador foi salvo e o player ja pode receber a atualizacao realtime.',
      });
    } catch (error) {
      toast({
        title: 'Falha ao enviar anuncio',
        description: error instanceof Error ? error.message : 'Nao foi possivel enviar o anuncio para o telao.',
        variant: 'destructive',
      });
    }
  }

  async function handleDeleteAd(ad: ApiWallAdItem) {
    const confirmed = window.confirm('Remover este anuncio do telao?');
    if (!confirmed) {
      return;
    }

    try {
      await deleteAdMutation.mutateAsync(ad.id);

      queryClient.setQueryData<ApiWallAdItem[]>(
        queryKeys.wall.ads(eventId),
        (current) => sortWallAds((current ?? []).filter((item) => item.id !== ad.id)),
      );
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.ads(eventId) });

      toast({
        title: 'Anuncio removido',
        description: 'O patrocinador foi removido e o player sera atualizado.',
      });
    } catch (error) {
      toast({
        title: 'Falha ao remover anuncio',
        description: error instanceof Error ? error.message : 'Nao foi possivel remover o anuncio.',
        variant: 'destructive',
      });
    }
  }

  async function handleMoveAd(adId: number, direction: -1 | 1) {
    const nextAds = moveWallAd(wallAds, adId, direction);

    if (!nextAds) {
      return;
    }

    try {
      await reorderAdsMutation.mutateAsync(nextAds.map((item) => item.id));

      queryClient.setQueryData<ApiWallAdItem[]>(
        queryKeys.wall.ads(eventId),
        nextAds,
      );
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.ads(eventId) });

      toast({
        title: 'Ordem atualizada',
        description: 'A sequencia dos anuncios foi salva para o telao.',
      });
    } catch (error) {
      toast({
        title: 'Falha ao reordenar',
        description: error instanceof Error ? error.message : 'Nao foi possivel atualizar a ordem dos anuncios.',
        variant: 'destructive',
      });
    }
  }

  if (eventQuery.isLoading || settingsQuery.isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="h-6 w-6 animate-spin text-primary" />
      </div>
    );
  }

  if (eventQuery.isError || settingsQuery.isError || !event || !settings || !wallSettings) {
    return (
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
        <PageHeader
          title="Telao"
          description="Nao foi possivel carregar a configuracao do telao para este evento."
        />
        <div className="rounded-3xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center text-sm text-muted-foreground">
          Revise as permissoes do evento ou recarregue a pagina.
        </div>
      </motion.div>
    );
  }

  if (!event.module_flags.wall) {
    return (
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
        <PageHeader title={`Telao / ${event.title}`} description="Este evento ainda nao tem o modulo Telao habilitado." />
        <div className="rounded-3xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center text-sm text-muted-foreground">
          Habilite o modulo Telao no evento antes de configurar esta exibicao.
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6 pb-28">
      <PageHeader
        title={`Telao / ${event.title}`}
        description={headerDescription}
        actions={(
          <div className="flex flex-col gap-2 sm:items-end">
            <div className="flex flex-wrap items-center gap-2 sm:justify-end">
              <span className={`inline-flex items-center justify-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-wider ${STATUS_COLORS[status] || STATUS_COLORS.draft}`}>
                <span className={`h-1.5 w-1.5 rounded-full ${isLive ? 'animate-pulse bg-emerald-400' : 'bg-current opacity-50'}`} />
                {settings.status_label}
              </span>
              <span className={`inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-medium ${REALTIME_COLORS[realtimeState]}`}>
                {realtimeLabel(realtimeState)}
              </span>
              <HelpTooltip helpKey="realtime" />
              <span className="inline-flex items-center justify-center rounded-full border border-border/60 bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                {hasUnsavedChanges ? 'Alteracoes pendentes' : 'Tudo salvo'}
              </span>
            </div>

            <WallCommandToolbar
              ariaLabel="Comandos principais do telao"
              className="w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end"
            >
              <Button asChild variant="ghost" size="sm">
                <Link to="/wall">
                  <ArrowLeft className="mr-1.5 h-4 w-4" />
                  Voltar
                </Link>
              </Button>
              <Button variant="outline" size="sm" onClick={openScreen} disabled={!settings.public_url}>
                <ExternalLink className="mr-1.5 h-4 w-4" />
                Abrir telao
              </Button>
              {isLive ? (
                <Button variant="destructive" size="sm" onClick={() => void handleAction('pause')} disabled={isBusy}>
                  <Pause className="mr-1 h-4 w-4" />
                  Pausar
                </Button>
              ) : isPaused ? (
                <Button size="sm" onClick={() => void handleAction('start')} disabled={isBusy}>
                  <Play className="mr-1 h-4 w-4" />
                  Resumir
                </Button>
              ) : !isTerminal ? (
                <Button size="sm" onClick={() => void handleAction('start')} disabled={isBusy}>
                  <Play className="mr-1 h-4 w-4" />
                  Iniciar telao
                </Button>
              ) : (
                <Button variant="outline" size="sm" onClick={() => void handleAction('reset')} disabled={isBusy}>
                  <RefreshCw className="mr-1 h-4 w-4" />
                  Resetar
                </Button>
              )}
            </WallCommandToolbar>
          </div>
        )}
      />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.12fr)_420px]">
        <div className="space-y-4">
          <WallManagerSection
            title="Pulso do evento"
            description="Veja rapido quem mais enviou, o volume do evento e o que acabou de chegar agora."
          >
            <WallTopInsightsRail
              insights={insights}
              isLoading={insightsQuery.isLoading}
              selectedMediaId={selectedMediaId}
              onSelectMedia={handleSelectRecentMedia}
              onOpenMedia={handleOpenRecentMediaDetails}
            />
          </WallManagerSection>

          <WallHeroStage
            activeTab={activeStageTab}
            onTabChange={setActiveStageTab}
            isLive={isLive}
            isPaused={isPaused}
            status={status}
            selectedMedia={selectedMedia}
            liveSnapshot={liveSnapshot}
            eventTitle={event.title}
            eventSchedule={formatEventSchedule(event.starts_at, event.location_name)}
            wallCode={settings.wall_code}
            copied={copied}
            onCopyWallCode={copyWallCode}
            hasUnsavedChanges={hasUnsavedChanges}
            onOpenSelectedMediaDetails={() => setIsRecentDetailsOpen(true)}
            wallSettings={wallSettings}
            selectionSummary={selectionSummary}
            simulationSummary={simulationSummary}
            simulationPreview={simulationPreview}
            simulationExplanation={simulationExplanation}
            isSimulationLoading={simulationQuery.isLoading}
            isSimulationError={simulationQuery.isError}
            isSimulationRefreshing={simulationQuery.isFetching}
            isSimulationDraftPending={isSimulationDraftPending}
          />

          <WallManagerSection
            title="Diagnostico operacional"
            description="Resumo do que esta acontecendo no telao e nas telas conectadas."
          >
            <div className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <CompactMetricCard
                  label="Situacao do telao"
                  value={formatWallHealthLabel(diagnosticsSummary?.health_status ?? 'idle')}
                  detail={diagnosticsQuery.isFetching ? 'Atualizando status...' : 'Resumo consolidado das telas conectadas.'}
                  tone={healthTone(diagnosticsSummary?.health_status ?? 'idle')}
                />
                <CompactMetricCard
                  label="Telas conectadas"
                  value={`${diagnosticsSummary?.online_players ?? 0}/${diagnosticsSummary?.total_players ?? 0}`}
                  detail={`${diagnosticsSummary?.offline_players ?? 0} sem conexao e ${diagnosticsSummary?.degraded_players ?? 0} com instabilidade.`}
                />
                <CompactMetricCard
                  label="Fotos prontas"
                  value={String(diagnosticsSummary?.ready_count ?? 0)}
                  detail={`Carregando ${diagnosticsSummary?.loading_count ?? 0} | Com erro ${diagnosticsSummary?.error_count ?? 0} | Desatualizadas ${diagnosticsSummary?.stale_count ?? 0}`}
                />
                <CompactMetricCard
                  label="Ultimo sinal"
                  value={formatTimestampLabel(diagnosticsSummary?.last_seen_at)}
                  detail={diagnosticsSummary?.updated_at ? `Atualizado em ${formatTimestampLabel(diagnosticsSummary.updated_at)}` : 'Aguardando o primeiro sinal da tela.'}
                />
                <CompactMetricCard
                  label="Cache local"
                  value={formatPercentLabel(diagnosticsSummary?.cache_hit_rate_avg ?? 0)}
                  detail={`Maior uso ${formatBytes(diagnosticsSummary?.cache_usage_bytes_max)} de ${formatBytes(diagnosticsSummary?.cache_quota_bytes_max)} | Desatualizadas ${diagnosticsSummary?.cache_stale_fallback_count ?? 0}`}
                  tone={diagnosticsSummary && (diagnosticsSummary.cache_hit_rate_avg ?? 0) < 35 ? 'degraded' : 'default'}
                />
              </div>

              <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                  <div className="space-y-1">
                    <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Acoes de manutencao</p>
                    <p className="text-sm text-muted-foreground">
                      Use estes comandos quando a exibicao travar, demorar para atualizar ou mostrar fotos antigas.
                    </p>
                  </div>
                  <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void handlePlayerCommand('clear-cache', 'manager_clear_cache')}
                      disabled={isBusy}
                    >
                      <Trash2 className="mr-1.5 h-4 w-4" />
                      Limpar cache
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void handlePlayerCommand('revalidate-assets', 'manager_revalidate_assets')}
                      disabled={isBusy}
                    >
                      <RefreshCw className="mr-1.5 h-4 w-4" />
                      Atualizar fotos
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void handlePlayerCommand('reinitialize-engine', 'manager_reinitialize_engine')}
                      disabled={isBusy}
                    >
                      <RotateCcw className="mr-1.5 h-4 w-4" />
                      Reiniciar exibicao
                    </Button>
                  </div>
                </div>
              </div>

              {diagnosticsQuery.isLoading && !diagnosticsSummary ? (
                <div className="flex min-h-[96px] items-center justify-center rounded-2xl border border-dashed border-border/60 bg-muted/20 text-sm text-muted-foreground">
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Carregando status do telao...
                </div>
              ) : null}

              {diagnosticsPlayers.length > 0 ? (
                <div className="grid gap-3">
                  {diagnosticsPlayers.map((player) => (
                    <WallPlayerRuntimeCard
                      key={player.player_instance_id}
                      player={player}
                      onOpenDetails={(currentPlayer) => {
                        setSelectedPlayerId(currentPlayer.player_instance_id);
                        setIsPlayerDetailsOpen(true);
                      }}
                    />
                  ))}
                </div>
              ) : (
                <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-sm text-muted-foreground">
                  Ainda nao recebemos sinal desta tela. Abra o telao em um navegador para acompanhar o status por aparelho.
                </div>
              )}
            </div>
          </WallManagerSection>

          {!isTerminal && (isLive || isPaused) ? (
            <WallManagerSection
              title={(
                <span className="flex items-center gap-2 text-destructive">
                  <AlertTriangle className="h-4 w-4" />
                  Acoes avancadas
                  <HelpTooltip helpKey="advancedActions" />
                </span>
              )}
              description="Use estes comandos apenas quando precisar interromper ou encerrar a exibicao."
              className="border-destructive/20"
            >
              <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <Button variant="destructive" size="sm" onClick={() => void handleAction('full-stop')} disabled={isBusy}>
                  <Square className="mr-1 h-4 w-4" />
                  Parar completamente
                </Button>
                <Button variant="destructive" size="sm" onClick={() => void handleAction('expire')} disabled={isBusy}>
                  <Power className="mr-1 h-4 w-4" />
                  Encerrar telao
                </Button>
              </div>
            </WallManagerSection>
          ) : null}
        </div>

        <div className="space-y-4">
          <WallInspectorTabs activeTab={activeInspectorTab} onTabChange={setActiveInspectorTab} />

          {activeInspectorTab === 'queue' ? (
            <WallQueueTab
              wallSettings={wallSettings}
              selectionModes={selectionModes}
              eventPhases={eventPhases}
              selectionSummary={selectionSummary}
              onSelectionModeChange={handleSelectionModeChange}
              onDraftChange={updateDraft}
              onSelectionPolicyChange={updateSelectionPolicy}
            />
          ) : null}

          {activeInspectorTab === 'appearance' ? (
            <WallAppearanceTab
              wallSettings={wallSettings}
              options={options}
              onDraftChange={updateDraft}
            />
          ) : null}

          {activeInspectorTab === 'ads' ? (
            <WallAdsTab
              wallSettings={wallSettings}
              wallAds={wallAds}
              adsLoading={adsQuery.isLoading}
              uploadPending={uploadAdMutation.isPending}
              deletePending={deleteAdMutation.isPending}
              reorderPending={reorderAdsMutation.isPending}
              selectedAdFile={selectedAdFile}
              selectedAdDuration={selectedAdDuration}
              selectedAdIsVideo={selectedAdIsVideo}
              adFileInputRef={adFileInputRef}
              onDraftChange={updateDraft}
              onAdFileChange={setSelectedAdFile}
              onAdDurationChange={setSelectedAdDuration}
              onUploadAd={() => void handleAdUpload()}
              onResetAdUploadForm={resetAdUploadForm}
              onDeleteAd={(ad) => void handleDeleteAd(ad)}
              onMoveAd={(adId, direction) => void handleMoveAd(adId, direction)}
            />
          ) : null}
        </div>
      </div>

      {hasUnsavedChanges ? (
        <div className="sticky bottom-4 z-40">
          <div className="rounded-3xl border border-primary/20 bg-background/95 p-4 shadow-[0_24px_80px_rgba(0,0,0,0.18)] backdrop-blur">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <p className="text-sm font-semibold">Alteracoes prontas para salvar</p>
                  <HelpTooltip helpKey="saving" />
                </div>
                <p className="text-sm text-muted-foreground">
                  Salve antes de iniciar ou retomar o telao para garantir que a exibicao use estes ajustes.
                </p>
              </div>
              <div className="flex flex-col gap-2 sm:flex-row">
                <Button variant="outline" onClick={resetDraft} disabled={isBusy}>
                  Descartar
                </Button>
                <Button onClick={() => void saveDraft()} disabled={isBusy}>
                  {saveMutation.isPending ? (
                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="mr-1.5 h-4 w-4" />
                  )}
                  Salvar alteracoes
                </Button>
              </div>
            </div>
          </div>
        </div>
      ) : null}

      <WallRecentMediaDetailsSheet
        open={isRecentDetailsOpen && selectedMedia !== null}
        onOpenChange={setIsRecentDetailsOpen}
        item={selectedMedia}
      />
      <WallPlayerDetailsSheet
        open={isPlayerDetailsOpen && selectedPlayer !== null}
        onOpenChange={setIsPlayerDetailsOpen}
        player={selectedPlayer}
      />
    </motion.div>
  );
}

function CompactMetricCard({
  label,
  value,
  detail,
  tone = 'default',
}: {
  label: string;
  value: string;
  detail: string;
  tone?: 'default' | 'healthy' | 'degraded' | 'offline' | 'idle';
}) {
  return (
    <div className={`rounded-2xl border p-4 ${metricToneClass(tone)}`}>
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-2 text-lg font-semibold">{value}</p>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>
    </div>
  );
}

function formatPercentLabel(value?: number | null) {
  if (value == null) {
    return 'Sem dado';
  }

  return `${Math.max(0, Math.round(value))}%`;
}

function formatBytes(value?: number | null) {
  if (value == null || value <= 0) {
    return 'Sem dado';
  }

  if (value < 1024) {
    return `${value} B`;
  }

  const units = ['KB', 'MB', 'GB', 'TB'];
  let size = value / 1024;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  const rounded = size >= 100 ? Math.round(size) : Math.round(size * 10) / 10;
  return `${rounded} ${units[unitIndex]}`;
}

function formatTimestampLabel(value?: string | null) {
  if (!value) {
    return 'Sem sinal';
  }

  try {
    return new Date(value).toLocaleTimeString('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return 'Sem sinal';
  }
}

function formatWallHealthLabel(value: ApiWallDiagnosticsSummary['health_status']) {
  switch (value) {
    case 'healthy':
      return 'Saudavel';
    case 'degraded':
      return 'Com instabilidade';
    case 'offline':
      return 'Sem conexao';
    default:
      return 'Nenhuma tela conectada';
  }
}

function healthTone(value: ApiWallDiagnosticsSummary['health_status']): 'default' | 'healthy' | 'degraded' | 'offline' | 'idle' {
  switch (value) {
    case 'healthy':
      return 'healthy';
    case 'degraded':
      return 'degraded';
    case 'offline':
      return 'offline';
    case 'idle':
      return 'idle';
    default:
      return 'default';
  }
}

function metricToneClass(tone: 'default' | 'healthy' | 'degraded' | 'offline' | 'idle') {
  switch (tone) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/5';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/5';
    case 'offline':
      return 'border-rose-500/30 bg-rose-500/5';
    case 'idle':
      return 'border-slate-500/30 bg-slate-500/5';
    default:
      return 'border-border/60 bg-background/70';
  }
}

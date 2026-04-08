import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  AlertTriangle,
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Check,
  Copy,
  ExternalLink,
  Loader2,
  Monitor,
  Pause,
  Play,
  Power,
  RefreshCw,
  RotateCcw,
  Save,
  Settings,
  Square,
  Trash2,
} from 'lucide-react';
import { Link, useParams } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import type {
  ApiWallAdItem,
  ApiWallDiagnosticsPlayer,
  ApiWallDiagnosticsSummary,
  ApiWallPlayerCommand,
  ApiWallPersistentStorage,
  ApiWallSelectionPolicy,
  ApiWallSettings,
} from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';
import { PageHeader } from '@/shared/components/PageHeader';

import { getEventDetail } from '@/modules/events/api';
import { EVENT_STATUS_LABELS } from '@/modules/events/types';

import { HelpLabel, HelpTooltip } from '../components/WallManagerHelp';
import { WallManagerSection } from '../components/WallManagerSection';
import { WallTopInsightsRail } from '../components/manager/top/WallTopInsightsRail';
import {
  fallbackOptions,
  WALL_COOLDOWN_OPTIONS,
  WALL_EVENT_PHASE_OPTIONS,
  WALL_REPLAY_MINUTE_OPTIONS,
  WALL_SELECTION_MODE_OPTIONS,
  WALL_SLIDER_FIELDS,
  WALL_TOGGLE_FIELDS,
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
import { realtimeLabel, useWallManagerRealtime } from '../hooks/useWallManagerRealtime';
import { useWallSelectedMedia } from '../hooks/useWallSelectedMedia';
import { useWallTopInsights } from '../hooks/useWallTopInsights';
import { WALL_INSIGHTS_COPY, formatWallRecentStatusLabel } from '../wall-copy';
import {
  applyWallSelectionPreset,
  areWallSettingsEqual,
  buildWallSelectionSummary,
  cloneWallSettings,
  markWallSelectionAsCustom,
  prepareWallSettingsPayload,
  resolveWallSelectionModeOption,
} from '../wall-settings';
import { getWallSourceMeta } from '../wall-source-meta';
import { formatWallRelativeTime } from '../wall-view-models';

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

function formatFileSize(sizeBytes: number): string {
  if (sizeBytes < 1024) {
    return `${sizeBytes} B`;
  }

  if (sizeBytes < 1024 * 1024) {
    return `${Math.round(sizeBytes / 102.4) / 10} KB`;
  }

  return `${Math.round(sizeBytes / 104857.6) / 10} MB`;
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
  const adFileInputRef = useRef<HTMLInputElement | null>(null);

  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventDetail(eventId),
  });

  const settingsQuery = useQuery({
    queryKey: queryKeys.wall.settings(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallSettings(eventId),
  });

  const optionsQuery = useQuery({
    queryKey: queryKeys.wall.options(),
    queryFn: getWallOptions,
  });

  const diagnosticsQuery = useQuery({
    queryKey: queryKeys.wall.diagnostics(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallDiagnostics(eventId),
  });

  const adsQuery = useQuery({
    queryKey: queryKeys.wall.ads(eventId),
    enabled: eventId !== '',
    queryFn: () => getEventWallAds(eventId),
  });
  const insightsQuery = useWallTopInsights(eventId);

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
  const realtimeState = useWallManagerRealtime(eventId);
  const adMode = wallSettings?.ad_mode ?? 'disabled';
  const adFrequency = wallSettings?.ad_frequency ?? 5;
  const adIntervalMinutes = wallSettings?.ad_interval_minutes ?? 3;
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
  const insights = insightsQuery.data ?? null;
  const insightsRecentItems = insights?.recentItems ?? [];
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
  const selectedMediaSourceMeta = selectedMedia ? getWallSourceMeta(selectedMedia.source) : null;

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
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
            <Button asChild variant="ghost" size="sm">
              <Link to="/wall">
                <ArrowLeft className="mr-1.5 h-4 w-4" />
                Voltar
              </Link>
            </Button>
            <span className={`inline-flex items-center justify-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium uppercase tracking-wider ${STATUS_COLORS[status] || STATUS_COLORS.draft}`}>
              <span className={`h-1.5 w-1.5 rounded-full ${isLive ? 'animate-pulse bg-emerald-400' : 'bg-current opacity-50'}`} />
              {settings.status_label}
            </span>
            <span className={`inline-flex items-center justify-center rounded-full border px-3 py-1 text-xs font-medium ${REALTIME_COLORS[realtimeState]}`}>
              {realtimeLabel(realtimeState)}
            </span>
            <HelpTooltip helpKey="realtime" />
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
              onSelectMedia={selectMedia}
            />
          </WallManagerSection>

          <WallManagerSection
            title="Estado do telao"
            description="Veja rapidamente se o telao esta ativo, pausado ou aguardando inicio."
          >
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
              <div className="overflow-hidden rounded-3xl border border-border/60 bg-muted/30">
                <div className="aspect-[4/3] sm:aspect-video">
                  {selectedMedia ? (
                    <div className="relative h-full overflow-hidden bg-neutral-950">
                      {selectedMedia.previewUrl ? (
                        <img
                          src={selectedMedia.previewUrl}
                          alt={`Midia recente enviada por ${selectedMedia.senderName || 'Convidado'}`}
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full items-center justify-center px-6 text-center text-sm text-white/70">
                          Essa midia chegou sem miniatura pronta para o palco.
                        </div>
                      )}

                      <div className="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/40 to-transparent" />
                      <div className="absolute inset-x-0 bottom-0 space-y-3 p-5 text-white">
                        <div className="space-y-1">
                          <p className="text-xs uppercase tracking-[0.18em] text-white/60">
                            {WALL_INSIGHTS_COPY.selectedMedia}
                          </p>
                          <h3 className="text-xl font-semibold">
                            {selectedMedia.senderName || 'Convidado'}
                          </h3>
                        </div>

                        <div className="flex flex-wrap gap-2 text-xs">
                          {selectedMediaSourceMeta ? (
                            <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 font-medium ${selectedMediaSourceMeta.chipClassName}`}>
                              <selectedMediaSourceMeta.Icon className="h-3.5 w-3.5" />
                              {selectedMediaSourceMeta.label}
                            </span>
                          ) : null}
                          <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/80">
                            {formatWallRecentStatusLabel(selectedMedia.status)}
                          </span>
                          <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/80">
                            {formatWallRelativeTime(selectedMedia.createdAt, 'Agora')}
                          </span>
                        </div>
                      </div>
                    </div>
                  ) : isLive || isPaused ? (
                    <div className="flex h-full items-center justify-center bg-gradient-to-br from-neutral-900 via-neutral-950 to-neutral-900 px-6 text-center">
                      <div className="space-y-3">
                        <Monitor className="mx-auto h-14 w-14 text-orange-400/80" />
                        <p className="text-base font-medium text-white/80 sm:text-lg">
                          {isLive ? 'Telao ativo exibindo as midias em tempo real.' : 'Telao pausado aguardando novo comando.'}
                        </p>
                        <Button variant="outline" size="sm" onClick={openScreen}>
                          <ExternalLink className="mr-1.5 h-4 w-4" />
                          Abrir telao
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex h-full items-center justify-center px-6 text-center">
                      <div className="space-y-3">
                        <Monitor className="mx-auto h-12 w-12 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                          O telao ainda nao esta em exibicao. Inicie o telao para comecar a mostrar as midias.
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                {selectedMedia ? (
                  <InfoCard
                    label={WALL_INSIGHTS_COPY.selectedMedia}
                    value={selectedMedia.senderName || 'Convidado'}
                    detail={[
                      selectedMediaSourceMeta?.label,
                      formatWallRecentStatusLabel(selectedMedia.status),
                      formatWallRelativeTime(selectedMedia.createdAt, 'Agora'),
                    ].filter(Boolean).join(' - ')}
                  />
                ) : null}
                <InfoCard label="Evento" value={event.title} detail={formatEventSchedule(event.starts_at, event.location_name)} />
                <InfoCard
                  label="Codigo do telao"
                  value={settings.wall_code}
                  detail="Use este codigo para identificar o telao em suporte ou operacao."
                  action={(
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={copyWallCode}>
                      {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                    </Button>
                  )}
                  helpKey="wallCode"
                />
                <InfoCard
                  label="Alteracoes pendentes"
                  value={hasUnsavedChanges ? 'Sim' : 'Nao'}
                  detail={hasUnsavedChanges ? 'Existe ajuste local esperando salvar.' : 'Tudo salvo e sincronizado.'}
                />
              </div>
            </div>
          </WallManagerSection>

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
                    <PlayerRuntimeCard key={player.player_instance_id} player={player} />
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
          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                <RefreshCw className="h-4 w-4" />
                Previsao da fila
              </span>
            )}
            description="Mostra a ordem mais provavel de exibicao com a fila atual do evento e as configuracoes que voce esta ajustando."
          >
            <div className="space-y-4">
              <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="space-y-2">
                    <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Resumo da politica</p>
                    <p className="text-sm leading-relaxed text-foreground/90">{selectionSummary}</p>
                  </div>
                  <span className={`rounded-full border px-3 py-1 text-[11px] font-medium ${isSimulationDraftPending || simulationQuery.isFetching ? 'border-amber-500/30 bg-amber-500/10 text-amber-700' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'}`}>
                    {isSimulationDraftPending || simulationQuery.isFetching ? 'Atualizando' : 'Fila real'}
                  </span>
                </div>
              </div>

              {simulationQuery.isLoading && !simulationSummary ? (
                <div className="flex min-h-[120px] items-center justify-center rounded-2xl border border-dashed border-border/60 bg-muted/20 text-sm text-muted-foreground">
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Calculando a proxima ordem de exibicao...
                </div>
              ) : simulationQuery.isError ? (
                <div className="rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-4 text-sm text-destructive">
                  Nao foi possivel gerar a simulacao com a fila atual do evento.
                </div>
              ) : simulationSummary ? (
                <>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <CompactMetricCard
                      label="ETA media da primeira aparicao"
                      value={formatDurationSeconds(simulationSummary.estimated_first_appearance_seconds)}
                      detail={`${simulationSummary.active_senders} remetentes ativos na amostra atual.`}
                    />
                    <CompactMetricCard
                      label="Risco de monopolizacao"
                      value={formatLevelLabel(simulationSummary.monopolization_risk)}
                      detail={`Modo ${simulationSummary.selection_mode_label.toLowerCase()} em ${simulationSummary.event_phase_label.toLowerCase()} com ${simulationSummary.queue_items} itens na fila real.`}
                    />
                    <CompactMetricCard
                      label="Intensidade do frescor"
                      value={formatLevelLabel(simulationSummary.freshness_intensity)}
                      detail="Quanto o telao tende a parecer realmente ao vivo com a configuracao atual."
                    />
                    <CompactMetricCard
                      label="Nivel de fairness"
                      value={formatLevelLabel(simulationSummary.fairness_level)}
                      detail="Quanto a fila protege contra monopolizacao por remetente."
                    />
                  </div>

                  <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                    <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">
                      Ordem mais provavel das proximas {simulationPreview.length} exibicoes
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {simulationPreview.map((slide) => (
                        <span key={`${slide.position}-${slide.item_id}`} className="rounded-full border border-border/60 bg-background px-3 py-1.5 text-xs text-foreground/85">
                          {slide.eta_seconds}s | {slide.sender_name}{slide.is_replay ? ' | reprise' : ''}
                        </span>
                      ))}
                    </div>
                  </div>

                  {simulationExplanation.length > 0 ? (
                    <div className="space-y-2 rounded-2xl border border-border/60 bg-muted/20 p-4">
                      {simulationExplanation.map((line) => (
                        <p key={line} className="text-sm leading-relaxed text-muted-foreground">{formatSimulationExplanation(line)}</p>
                      ))}
                    </div>
                  ) : null}
                </>
              ) : (
                <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-sm text-muted-foreground">
                  Ajuste as configuracoes do telao para ver a previsao com a fila atual do evento.
                </div>
              )}
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                <Settings className="h-4 w-4" />
                Modo do telao
                <HelpTooltip helpKey="selectionMode" />
              </span>
            )}
            description="Escolha primeiro o comportamento base da fila. Os controles abaixo podem refinar esse preset."
          >
            <div className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <HelpLabel helpKey="selectionMode" className="text-sm">Comportamento base</HelpLabel>
                  <Select value={wallSettings.selection_mode} onValueChange={handleSelectionModeChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o modo" />
                    </SelectTrigger>
                    <SelectContent>
                      {selectionModes.map((mode) => (
                        <SelectItem key={mode.value} value={mode.value}>{mode.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <HelpLabel helpKey="eventPhase" className="text-sm">Fase do evento</HelpLabel>
                  <Select value={wallSettings.event_phase} onValueChange={(value) => updateDraft('event_phase', value as ApiWallSettings['event_phase'])}>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione a fase" />
                    </SelectTrigger>
                    <SelectContent>
                      {eventPhases.map((phase) => (
                        <SelectItem key={phase.value} value={phase.value}>{phase.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs leading-relaxed text-muted-foreground">
                    {eventPhases.find((phase) => phase.value === wallSettings.event_phase)?.description
                      ?? 'A fase aplica contexto operacional por cima do modo escolhido.'}
                  </p>
                </div>
              </div>

              <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
                <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Resumo do comportamento</p>
                <p className="mt-2 text-sm leading-relaxed text-foreground/90">{selectionSummary}</p>
              </div>
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                Fila e justica
                <HelpTooltip helpKey="fairnessSection" />
              </span>
            )}
            description="Essas regras evitam que uma unica pessoa domine o telao ao enviar muitas fotos."
          >
            <div className="space-y-5">
              <div>
                <HelpLabel helpKey="maxEligibleItems">Backlog elegivel por remetente</HelpLabel>
                <div className="mt-2 flex items-center gap-3">
                  <Slider
                    value={[wallSettings.selection_policy.max_eligible_items_per_sender]}
                    min={1}
                    max={12}
                    step={1}
                    onValueChange={([value]) => updateSelectionPolicy('max_eligible_items_per_sender', value)}
                    className="flex-1"
                  />
                  <span className="w-12 text-right text-sm font-medium">
                    {wallSettings.selection_policy.max_eligible_items_per_sender}
                  </span>
                </div>
              </div>

              <div>
                <HelpLabel helpKey="maxReplaysPerItem">Maximo de repeticoes por foto</HelpLabel>
                <div className="mt-2 flex items-center gap-3">
                  <Slider
                    value={[wallSettings.selection_policy.max_replays_per_item]}
                    min={0}
                    max={6}
                    step={1}
                    onValueChange={([value]) => updateSelectionPolicy('max_replays_per_item', value)}
                    className="flex-1"
                  />
                  <span className="w-12 text-right text-sm font-medium">
                    {wallSettings.selection_policy.max_replays_per_item}
                  </span>
                </div>
                <p className="mt-2 text-[11px] text-muted-foreground">
                  Se todas as fotos atingirem esse limite, a tela libera novas reprises para a exibicao nao ficar vazia.
                </p>
              </div>

              <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
                <div>
                  <HelpLabel helpKey="replayAdaptiveSection">Repeticao por volume da fila</HelpLabel>
                  <p className="text-[11px] text-muted-foreground">
                    Esse ajuste fica salvo no telao para manter o mesmo comportamento em qualquer aparelho conectado.
                  </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <HelpLabel helpKey="lowVolumeThreshold" className="text-sm">Fila baixa ate</HelpLabel>
                    <Select
                      value={String(wallSettings.selection_policy.low_volume_max_items)}
                      onValueChange={(value) => updateSelectionPolicy('low_volume_max_items', Number(value))}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o limite" />
                      </SelectTrigger>
                      <SelectContent>
                        {WALL_VOLUME_THRESHOLD_OPTIONS.map((value) => (
                          <SelectItem key={`low-${value}`} value={String(value)}>{value} itens</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <HelpLabel helpKey="mediumVolumeThreshold" className="text-sm">Fila media ate</HelpLabel>
                    <Select
                      value={String(wallSettings.selection_policy.medium_volume_max_items)}
                      onValueChange={(value) => updateSelectionPolicy('medium_volume_max_items', Number(value))}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o limite" />
                      </SelectTrigger>
                      <SelectContent>
                        {WALL_VOLUME_THRESHOLD_OPTIONS
                          .filter((option) => option > wallSettings.selection_policy.low_volume_max_items)
                          .map((value) => (
                            <SelectItem key={`medium-${value}`} value={String(value)}>{value} itens</SelectItem>
                          ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                  <div className="space-y-2">
                    <HelpLabel helpKey="replayIntervalLow" className="text-sm">Repeticao com fila curta</HelpLabel>
                    <Select
                      value={String(wallSettings.selection_policy.replay_interval_low_minutes)}
                      onValueChange={(value) => updateSelectionPolicy('replay_interval_low_minutes', Number(value))}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Tempo" />
                      </SelectTrigger>
                      <SelectContent>
                        {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                          <SelectItem key={`replay-low-${value}`} value={String(value)}>{value} min</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <HelpLabel helpKey="replayIntervalMedium" className="text-sm">Repeticao com fila media</HelpLabel>
                    <Select
                      value={String(wallSettings.selection_policy.replay_interval_medium_minutes)}
                      onValueChange={(value) => updateSelectionPolicy('replay_interval_medium_minutes', Number(value))}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Tempo" />
                      </SelectTrigger>
                      <SelectContent>
                        {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                          <SelectItem key={`replay-medium-${value}`} value={String(value)}>{value} min</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <HelpLabel helpKey="replayIntervalHigh" className="text-sm">Repeticao com fila cheia</HelpLabel>
                    <Select
                      value={String(wallSettings.selection_policy.replay_interval_high_minutes)}
                      onValueChange={(value) => updateSelectionPolicy('replay_interval_high_minutes', Number(value))}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Tempo" />
                      </SelectTrigger>
                      <SelectContent>
                        {WALL_REPLAY_MINUTE_OPTIONS.map((value) => (
                          <SelectItem key={`replay-high-${value}`} value={String(value)}>{value} min</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="senderCooldown" className="text-sm">Tempo minimo entre aparicoes</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.sender_cooldown_seconds)}
                  onValueChange={(value) => updateSelectionPolicy('sender_cooldown_seconds', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione o cooldown" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_COOLDOWN_OPTIONS.map((value) => (
                      <SelectItem key={value} value={String(value)}>
                        {value === 0 ? 'Sem cooldown' : `${value}s`}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <HelpLabel helpKey="senderWindowLimit">Limite por remetente na janela</HelpLabel>
                <div className="mt-2 flex items-center gap-3">
                  <Slider
                    value={[wallSettings.selection_policy.sender_window_limit]}
                    min={1}
                    max={6}
                    step={1}
                    onValueChange={([value]) => updateSelectionPolicy('sender_window_limit', value)}
                    className="flex-1"
                  />
                  <span className="w-12 text-right text-sm font-medium">
                    {wallSettings.selection_policy.sender_window_limit}
                  </span>
                </div>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="senderWindowMinutes" className="text-sm">Janela de controle</HelpLabel>
                <Select
                  value={String(wallSettings.selection_policy.sender_window_minutes)}
                  onValueChange={(value) => updateSelectionPolicy('sender_window_minutes', Number(value))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione a janela" />
                  </SelectTrigger>
                  <SelectContent>
                    {WALL_WINDOW_MINUTE_OPTIONS.map((value) => (
                      <SelectItem key={value} value={String(value)}>{value} min</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="flex items-center justify-between gap-3">
                <div>
                  <HelpLabel helpKey="fairnessSection">Evitar repetir o mesmo remetente</HelpLabel>
                  <p className="text-[11px] text-muted-foreground">
                    Mantem a alternancia entre convidados quando houver outra midia pronta.
                  </p>
                </div>
                <Switch
                  checked={wallSettings.selection_policy.avoid_same_sender_if_alternative_exists}
                  onCheckedChange={(checked) => updateSelectionPolicy('avoid_same_sender_if_alternative_exists', checked)}
                />
              </div>

              <div className="flex items-center justify-between gap-3">
                <div>
                  <HelpLabel helpKey="antiDuplicateSequence">Anti-sequencia parecida</HelpLabel>
                  <p className="text-[11px] text-muted-foreground">
                    Evita puxar fotos muito parecidas do mesmo grupo quando houver alternativa.
                  </p>
                </div>
                <Switch
                  checked={wallSettings.selection_policy.avoid_same_duplicate_cluster_if_alternative_exists}
                  onCheckedChange={(checked) => updateSelectionPolicy('avoid_same_duplicate_cluster_if_alternative_exists', checked)}
                />
              </div>
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                <Settings className="h-4 w-4" />
                Ajustes da exibicao
                <HelpTooltip helpKey="slideshowSection" />
              </span>
            )}
            description="As alteracoes abaixo ficam neste aparelho ate voce tocar em salvar. Isso ajuda quem opera no celular e funciona melhor em internet lenta."
          >
            <div className="space-y-5">
              {WALL_SLIDER_FIELDS.map((field) => {
                const settingValue = wallSettings[field.key] as number;

                return (
                  <div key={field.key}>
                    <HelpLabel helpKey={field.helpKey}>{field.label}</HelpLabel>
                    <div className="mt-2 flex items-center gap-3">
                      <Slider
                        value={[field.toControlValue(settingValue)]}
                        min={field.min}
                        max={field.max}
                        step={field.step}
                        onValueChange={([value]) => updateDraft(field.key, field.fromControlValue(value))}
                        className="flex-1"
                      />
                      <span className="w-12 text-right text-sm font-medium">{field.formatValue(settingValue)}</span>
                    </div>
                  </div>
                );
              })}

              {WALL_TOGGLE_FIELDS.map((field) => (
                <div key={field.key} className="flex items-center justify-between gap-3">
                  <div>
                    <HelpLabel helpKey={field.helpKey}>{field.label}</HelpLabel>
                    <p className="text-[11px] text-muted-foreground">{field.description}</p>
                  </div>
                  <Switch
                    checked={wallSettings[field.key] as boolean}
                    onCheckedChange={(checked) => updateDraft(field.key, checked)}
                  />
                </div>
              ))}

              {wallSettings.show_neon ? (
                <div className="grid gap-4 rounded-2xl border border-border/60 bg-background/60 p-4 sm:grid-cols-[minmax(0,1fr)_96px]">
                  <div className="space-y-2">
                    <HelpLabel helpKey="neonText" className="text-sm">Texto da chamada</HelpLabel>
                    <Input
                      value={wallSettings.neon_text ?? ''}
                      onChange={(event) => updateDraft('neon_text', event.target.value)}
                      placeholder="Compartilhe o melhor momento da noite"
                    />
                  </div>
                  <div className="space-y-2">
                    <HelpLabel helpKey="neonColor" className="text-sm">Cor da chamada</HelpLabel>
                    <Input
                      type="color"
                      value={wallSettings.neon_color ?? '#ffffff'}
                      onChange={(event) => updateDraft('neon_color', event.target.value)}
                      className="h-11 w-20 p-1"
                    />
                  </div>
                </div>
              ) : null}
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                Visual e troca de fotos
                <HelpTooltip helpKey="layoutSection" />
              </span>
            )}
            description="Escolha aqui o estilo de exibicao e a animacao de troca entre as midias."
          >
            <div className="space-y-4">
              <div className="space-y-2">
                <HelpLabel helpKey="layout" className="text-sm">Estilo da exibicao</HelpLabel>
                <Select value={wallSettings.layout} onValueChange={(value) => updateDraft('layout', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione o estilo" />
                  </SelectTrigger>
                  <SelectContent>
                    {options.layouts.map((layout) => (
                      <SelectItem key={layout.value} value={layout.value}>{layout.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="transition" className="text-sm">Animacao de troca</HelpLabel>
                <Select value={wallSettings.transition_effect} onValueChange={(value) => updateDraft('transition_effect', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione a animacao" />
                  </SelectTrigger>
                  <SelectContent>
                    {options.transitions.map((transition) => (
                      <SelectItem key={transition.value} value={transition.value}>{transition.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <HelpLabel helpKey="orientation" className="text-sm">Orientação aceita</HelpLabel>
                <Select value={wallSettings.accepted_orientation ?? 'all'} onValueChange={(value) => updateDraft('accepted_orientation', value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione a orientação" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas as orientações</SelectItem>
                    <SelectItem value="landscape">Apenas paisagem (horizontal)</SelectItem>
                    <SelectItem value="portrait">Apenas retrato (vertical)</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-[11px] text-muted-foreground">
                  Filtra quais mídias aparecem no telão por orientação. Mídias quadradas passam em qualquer filtro.
                </p>
              </div>

              <div className="flex items-center justify-between gap-3">
                <div>
                  <HelpLabel helpKey="sideThumbnails">Miniaturas laterais</HelpLabel>
                  <p className="text-[11px] text-muted-foreground">
                    Exibe uma faixa com as próximas mídias na lateral do telão, mantendo o público engajado.
                  </p>
                </div>
                <Switch
                  checked={wallSettings.show_side_thumbnails ?? true}
                  onCheckedChange={(checked) => updateDraft('show_side_thumbnails', checked)}
                />
              </div>
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                Patrocinadores no telao
              </span>
            )}
            description="Configure quando os anuncios entram no slideshow e gerencie os criativos ativos do evento."
          >
            <div className="space-y-5">
              <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
                  <div className="space-y-2">
                    <p className="text-sm font-medium">Modo de exibicao dos anuncios</p>
                    <Select value={adMode} onValueChange={(value) => updateDraft('ad_mode', value as ApiWallSettings['ad_mode'])}>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o modo de anuncios" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="disabled">Desativado</SelectItem>
                        <SelectItem value="by_photos">A cada X fotos</SelectItem>
                        <SelectItem value="by_minutes">A cada X minutos</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-[11px] text-muted-foreground">
                      Videos de patrocinador sao reproduzidos sem som para respeitar autoplay nos navegadores.
                    </p>
                  </div>

                  {adMode === 'by_photos' ? (
                    <div className="space-y-2">
                      <p className="text-sm font-medium">Frequencia por fotos</p>
                      <Input
                        type="number"
                        min={1}
                        max={100}
                        value={String(adFrequency)}
                        onChange={(event) => updateDraft('ad_frequency', clampIntegerInput(event.target.value, 5, 1, 100))}
                      />
                      <p className="text-[11px] text-muted-foreground">
                        O anuncio entra depois de cada bloco de fotos exibidas pelo slideshow.
                      </p>
                    </div>
                  ) : null}

                  {adMode === 'by_minutes' ? (
                    <div className="space-y-2">
                      <p className="text-sm font-medium">Intervalo por minutos</p>
                      <Input
                        type="number"
                        min={1}
                        max={60}
                        value={String(adIntervalMinutes)}
                        onChange={(event) => updateDraft('ad_interval_minutes', clampIntegerInput(event.target.value, 3, 1, 60))}
                      />
                      <p className="text-[11px] text-muted-foreground">
                        Use esse modo quando quiser ciclos mais previsiveis para patrocinadores em eventos longos.
                      </p>
                    </div>
                  ) : null}
                </div>

                <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
                  <div className="space-y-2">
                    <p className="text-sm font-medium">Adicionar novo criativo</p>
                    <Input
                      ref={adFileInputRef}
                      aria-label="Arquivo do patrocinador"
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,image/jpeg,image/png,image/webp,image/gif,video/mp4"
                      onChange={(event) => setSelectedAdFile(event.target.files?.[0] ?? null)}
                    />
                  </div>

                  <div className="space-y-2">
                    <p className="text-sm font-medium">Duracao da imagem em segundos</p>
                    <Input
                      aria-label="Duracao do anuncio"
                      type="number"
                      min={3}
                      max={120}
                      disabled={selectedAdIsVideo || !selectedAdFile}
                      value={selectedAdDuration}
                      onChange={(event) => setSelectedAdDuration(event.target.value)}
                    />
                    <p className="text-[11px] text-muted-foreground">
                      Para video, a duracao vem do proprio arquivo e o player avanca ao terminar.
                    </p>
                  </div>

                  <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                    {selectedAdFile
                      ? `Selecionado: ${selectedAdFile.name} · ${formatFileSize(selectedAdFile.size)}`
                      : 'Formatos aceitos: JPG, PNG, WebP, GIF e MP4. Tamanho maximo: 20 MB.'}
                  </div>

                  <div className="flex flex-wrap gap-2">
                    <Button
                      type="button"
                      onClick={() => void handleAdUpload()}
                      disabled={!selectedAdFile || uploadAdMutation.isPending}
                    >
                      {uploadAdMutation.isPending ? (
                        <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                      ) : null}
                      Enviar anuncio
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={resetAdUploadForm}
                      disabled={!selectedAdFile || uploadAdMutation.isPending}
                    >
                      Limpar selecao
                    </Button>
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-sm font-medium">Criativos ativos</p>
                    <p className="text-[11px] text-muted-foreground">
                      A ordem abaixo define a sequencia usada pelo player em round-robin.
                    </p>
                  </div>
                  <span className="rounded-full border border-border/70 bg-background px-3 py-1 text-xs text-muted-foreground">
                    {wallAds.length} item(ns)
                  </span>
                </div>

                {adsQuery.isLoading ? (
                  <div className="rounded-2xl border border-border/60 bg-background/60 px-4 py-6 text-sm text-muted-foreground">
                    Carregando anuncios do telao...
                  </div>
                ) : wallAds.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-border/60 bg-background/60 px-4 py-8 text-sm text-muted-foreground">
                    Nenhum anuncio cadastrado ainda. Envie o primeiro criativo para liberar a monetizacao do telao.
                  </div>
                ) : (
                  <div className="space-y-3">
                    {wallAds.map((ad, index) => (
                      <div key={ad.id} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                          <div className="h-24 w-full overflow-hidden rounded-xl border border-border/60 bg-muted/30 lg:w-40">
                            {ad.media_type === 'image' && ad.url ? (
                              <img
                                src={ad.url}
                                alt={`Criativo ${index + 1}`}
                                className="h-full w-full object-cover"
                              />
                            ) : (
                              <div className="flex h-full w-full items-center justify-center text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                Video MP4
                              </div>
                            )}
                          </div>

                          <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold">Patrocinador {index + 1}</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                              {ad.media_type === 'video' ? 'Video' : 'Imagem'}
                              {' · '}
                              {ad.media_type === 'video' ? 'termina no fim do video' : `${ad.duration_seconds}s na tela`}
                            </p>
                            {ad.url ? (
                              <a
                                href={ad.url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-flex text-xs font-medium text-primary underline-offset-4 hover:underline"
                              >
                                Abrir criativo
                              </a>
                            ) : null}
                          </div>

                          <div className="flex flex-wrap items-center gap-2">
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              aria-label={`Subir anuncio ${index + 1}`}
                              disabled={index === 0 || reorderAdsMutation.isPending}
                              onClick={() => void handleMoveAd(ad.id, -1)}
                            >
                              <ArrowUp className="h-4 w-4" />
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              aria-label={`Descer anuncio ${index + 1}`}
                              disabled={index === wallAds.length - 1 || reorderAdsMutation.isPending}
                              onClick={() => void handleMoveAd(ad.id, 1)}
                            >
                              <ArrowDown className="h-4 w-4" />
                            </Button>
                            <Button
                              type="button"
                              variant="destructive"
                              size="icon"
                              aria-label={`Remover anuncio ${index + 1}`}
                              disabled={deleteAdMutation.isPending}
                              onClick={() => void handleDeleteAd(ad)}
                            >
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </WallManagerSection>

          <WallManagerSection
            title={(
              <span className="flex items-center gap-2">
                Mensagem quando nao ha fotos
                <HelpTooltip helpKey="idleSection" />
              </span>
            )}
            description="Use este texto para orientar o publico quando o telao ainda estiver esperando novas midias."
          >
            <div className="space-y-2">
              <HelpLabel helpKey="instructions" className="text-sm">Texto de espera</HelpLabel>
              <Textarea
                value={wallSettings.instructions_text ?? ''}
                onChange={(event) => updateDraft('instructions_text', event.target.value)}
                className="min-h-[120px]"
                placeholder="Envie sua foto para aparecer no telao em tempo real."
              />
            </div>
          </WallManagerSection>
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
    </motion.div>
  );
}

function InfoCard({
  label,
  value,
  detail,
  action,
  helpKey,
}: {
  label: string;
  value: string;
  detail: string;
  action?: ReactNode;
  helpKey?: Parameters<typeof HelpTooltip>[0]['helpKey'];
}) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-1.5">
          <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
          {helpKey ? <HelpTooltip helpKey={helpKey} /> : null}
        </div>
        {action}
      </div>
      <p className="mt-2 text-base font-semibold">{value}</p>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>
    </div>
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

function PlayerRuntimeCard({ player }: { player: ApiWallDiagnosticsPlayer }) {
  return (
    <div className={`rounded-2xl border p-4 ${playerCardClass(player.health_status)}`}>
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-sm font-semibold">Tela {shortPlayerId(player.player_instance_id)}</p>
          <p className="text-xs text-muted-foreground">
            Ultimo sinal {formatTimestampLabel(player.last_seen_at)}
          </p>
        </div>
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-medium ${healthBadgeClass(player.health_status)}`}>
          {formatPlayerHealthLabel(player.health_status)}
        </span>
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <RuntimeStat label="Exibicao" value={formatRuntimeStatus(player.runtime_status)} />
        <RuntimeStat label="Conexao" value={formatConnectionStatus(player.connection_status)} />
        <RuntimeStat label="Ultimo envio na tela" value={player.current_sender_key ?? 'Nenhum item agora'} />
        <RuntimeStat
          label="Fotos"
          value={`R${player.ready_count} | L${player.loading_count} | E${player.error_count} | S${player.stale_count}`}
        />
        <RuntimeStat label="Uso do cache" value={formatPercentLabel(player.cache_hit_rate)} />
        <RuntimeStat
          label="Espaco local"
          value={`${formatBytes(player.cache_usage_bytes)} / ${formatBytes(player.cache_quota_bytes)}`}
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Cache {player.cache_enabled ? 'ativo' : 'desligado'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Armazenamento {formatPersistentStorage(player.persistent_storage)}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          Acertos {player.cache_hit_count} | Falhas {player.cache_miss_count} | Desatualizados {player.cache_stale_fallback_count}
        </span>
        {player.last_sync_at ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Ultima atualizacao {formatTimestampLabel(player.last_sync_at)}
          </span>
        ) : null}
        {player.last_fallback_reason ? (
          <span className="rounded-full border border-border/60 bg-background px-3 py-1">
            Motivo da troca {formatFallbackReason(player.last_fallback_reason)}
          </span>
        ) : null}
      </div>
    </div>
  );
}

function RuntimeStat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-border/50 bg-background/70 p-3">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-medium">{value}</p>
    </div>
  );
}

function shortPlayerId(playerInstanceId: string) {
  return playerInstanceId.length <= 12
    ? playerInstanceId
    : `${playerInstanceId.slice(0, 8)}...${playerInstanceId.slice(-4)}`;
}

function formatDurationSeconds(value?: number | null) {
  if (value == null) {
    return 'Sem dado';
  }

  if (value < 60) {
    return `${value}s`;
  }

  const minutes = Math.floor(value / 60);
  const seconds = value % 60;
  return `${minutes}m ${seconds}s`;
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

function formatLevelLabel(value: 'low' | 'medium' | 'high') {
  if (value === 'high') return 'Alta';
  if (value === 'medium') return 'Media';
  return 'Baixa';
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

function formatPlayerHealthLabel(value: ApiWallDiagnosticsPlayer['health_status']) {
  if (value === 'healthy') return 'Saudavel';
  if (value === 'degraded') return 'Com instabilidade';
  return 'Sem conexao';
}

function formatRuntimeStatus(value?: string | null) {
  switch (value) {
    case 'booting':
      return 'Iniciando';
    case 'loading':
      return 'Carregando';
    case 'playing':
      return 'Exibindo';
    case 'paused':
      return 'Pausado';
    case 'error':
      return 'Com erro';
    case 'idle':
      return 'Aguardando';
    default:
      return formatLooseLabel(value, 'Sem dado');
  }
}

function formatConnectionStatus(value?: string | null) {
  switch (value) {
    case 'connected':
      return 'Conectado';
    case 'reconnecting':
      return 'Reconectando';
    case 'disconnected':
      return 'Desconectado';
    case 'offline':
      return 'Offline';
    default:
      return formatLooseLabel(value, 'Sem dado');
  }
}

function formatFallbackReason(value?: string | null) {
  return formatLooseLabel(value, 'Sem detalhe');
}

function formatLooseLabel(value?: string | null, fallback = 'Sem dado') {
  if (!value) {
    return fallback;
  }

  const normalized = value.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();

  if (!normalized) {
    return fallback;
  }

  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function formatSimulationExplanation(line: string) {
  return line
    .replace(
      'a simulacao usou a fila real atual do evento com o draft das configuracoes do wall.',
      'a previsao usou a fila atual do evento com as configuracoes que voce esta ajustando no telao.',
    )
    .replace(/\bsimulacao\b/gi, 'previsao')
    .replace(/\bdraft das configuracoes do wall\b/gi, 'configuracoes que voce esta ajustando no telao')
    .replace(/\bdraft\b/gi, 'rascunho')
    .replace(/\bwall\b/gi, 'telao')
    .replace(/\bselector\b/gi, 'organizador da fila')
    .replace(/\breplay\b/gi, 'reprise');
}

function formatPersistentStorage(value: ApiWallPersistentStorage) {
  switch (value) {
    case 'localstorage':
      return 'Memoria do navegador';
    case 'indexeddb':
      return 'Banco local do navegador';
    case 'cache_api':
      return 'Cache do navegador';
    case 'unavailable':
      return 'Indisponivel';
    case 'unknown':
      return 'Desconhecida';
    default:
      return 'Nenhuma';
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

function playerCardClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/20 bg-emerald-500/5';
    case 'degraded':
      return 'border-amber-500/20 bg-amber-500/5';
    default:
      return 'border-rose-500/20 bg-rose-500/5';
  }
}

function healthBadgeClass(health: ApiWallDiagnosticsPlayer['health_status']) {
  switch (health) {
    case 'healthy':
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700';
    case 'degraded':
      return 'border-amber-500/30 bg-amber-500/10 text-amber-700';
    default:
      return 'border-rose-500/30 bg-rose-500/10 text-rose-700';
  }
}

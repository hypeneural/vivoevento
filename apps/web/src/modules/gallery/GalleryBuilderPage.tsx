import { startTransition, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { ExternalLink, Loader2 } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { getEventDetail, listEventMedia } from '@/modules/events/api';
import { PageHeader } from '@/shared/components/PageHeader';
import {
  autosaveEventGalleryDraft,
  createEventGalleryPreviewLink,
  publishEventGalleryDraft,
  restoreEventGalleryRevision,
  trackEventGalleryBuilderTelemetry,
  updateEventGallerySettings,
  type GalleryBuilderSettingsUpdatePayload,
} from './api';
import {
  applyGalleryAiVariationToDraft,
  applyMatrixSelectionToDraft,
  buildGalleryBuilderVitalsTelemetryPayload,
  mergeGalleryLayers,
  resolveGalleryRenderModeForBuilder,
  type GalleryAiApplyScope,
  type GalleryAiProposalRun,
  type GalleryAiTargetLayer,
  type GalleryAiVariation,
  type GalleryBuilderMode,
  type GalleryBuilderOperationalFeedback,
  type GalleryBuilderPreset,
  type GalleryBuilderTelemetryPayload,
  type GalleryBuilderVitalsSnapshot,
  type GalleryPresetOrigin,
  type GalleryRenderMode,
  type GalleryBuilderRevision,
  type GalleryBuilderSettings,
  type GalleryBuilderShowResponse,
  type GalleryBuilderViewport,
} from './gallery-builder';
import { useGalleryAiProposals } from './hooks/useGalleryAiProposals';
import { useGalleryBuilderSettings } from './hooks/useGalleryBuilderSettings';
import { useGalleryPresets } from './hooks/useGalleryPresets';
import { useGalleryRevisions } from './hooks/useGalleryRevisions';
import { GalleryAiVariationsPanel } from './components/GalleryAiVariationsPanel';
import { GalleryBlocksPanel } from './components/GalleryBlocksPanel';
import { GalleryContextInspector } from './components/GalleryContextInspector';
import { GalleryModeSwitch } from './components/GalleryModeSwitch';
import { GalleryPresetRail } from './components/GalleryPresetRail';
import { GalleryPreviewFrame } from './components/GalleryPreviewFrame';
import { GalleryPreviewToolbar } from './components/GalleryPreviewToolbar';
import { GalleryQuickSetupRail } from './components/GalleryQuickSetupRail';
import { GalleryQuickStartWizard } from './components/GalleryQuickStartWizard';
import { GalleryRevisionPanel } from './components/GalleryRevisionPanel';
import { GalleryThemePanel } from './components/GalleryThemePanel';

type AutosaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
type SaveSource = 'autosave' | 'manual' | 'publish-prep' | 'preview-prep';
const QUICK_SHORTCUT_SELECTIONS = {
  weddingRomanticStory: {
    event_type_family: 'wedding',
    style_skin: 'romantic',
    behavior_profile: 'story',
  },
  weddingPremiumLight: {
    event_type_family: 'wedding',
    style_skin: 'premium',
    behavior_profile: 'light',
  },
  quinceModernLive: {
    event_type_family: 'quince',
    style_skin: 'modern',
    behavior_profile: 'live',
  },
  corporateCleanSponsors: {
    event_type_family: 'corporate',
    style_skin: 'clean',
    behavior_profile: 'sponsors',
  },
} as const;

const QUICK_SHORTCUT_LABELS: Record<keyof typeof QUICK_SHORTCUT_SELECTIONS, string> = {
  weddingRomanticStory: 'Romantico story',
  weddingPremiumLight: 'Premium light',
  quinceModernLive: 'Quince live',
  corporateCleanSponsors: 'Corporate sponsors',
};

const EMPTY_OPERATIONAL_FEEDBACK: GalleryBuilderOperationalFeedback = {
  current_preset_origin: null,
  last_ai_application: null,
  last_publish: null,
  last_restore: null,
};

function extractUpdatePayload(settings: GalleryBuilderSettings): GalleryBuilderSettingsUpdatePayload {
  return {
    is_enabled: settings.is_enabled,
    event_type_family: settings.event_type_family,
    style_skin: settings.style_skin,
    behavior_profile: settings.behavior_profile,
    theme_key: settings.theme_key,
    layout_key: settings.layout_key,
    theme_tokens: settings.theme_tokens,
    page_schema: settings.page_schema,
    media_behavior: settings.media_behavior,
  };
}

function serializeSettings(settings: GalleryBuilderSettings) {
  return JSON.stringify(extractUpdatePayload(settings));
}

function applyPresetToDraft(current: GalleryBuilderSettings, preset: GalleryBuilderPreset) {
  const currentHero = current.page_schema.blocks.hero as { image_path?: string | null; image_url?: string | null } | undefined;
  const currentBanner = current.page_schema.blocks.banner_strip as { image_path?: string | null; image_url?: string | null } | undefined;
  const nextHero = preset.payload.page_schema.blocks.hero as Record<string, unknown>;
  const nextBanner = preset.payload.page_schema.blocks.banner_strip as Record<string, unknown>;

  return mergeGalleryLayers(current, {
    event_type_family: preset.event_type_family,
    style_skin: preset.style_skin,
    behavior_profile: preset.behavior_profile,
    theme_key: preset.theme_key,
    layout_key: preset.layout_key,
    theme_tokens: preset.payload.theme_tokens,
    page_schema: {
      ...preset.payload.page_schema,
      blocks: {
        ...preset.payload.page_schema.blocks,
        hero: {
          ...nextHero,
          image_path: currentHero?.image_path ?? null,
          image_url: currentHero?.image_url ?? null,
        },
        banner_strip: {
          ...nextBanner,
          image_path: currentBanner?.image_path ?? null,
          image_url: currentBanner?.image_url ?? null,
        },
      },
    },
    media_behavior: preset.payload.media_behavior,
  });
}

function nowMs() {
  if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
    return performance.now();
  }

  return Date.now();
}

function readGalleryBuilderVitalsSample(): GalleryBuilderVitalsSnapshot {
  if (typeof performance === 'undefined' || typeof performance.getEntriesByType !== 'function') {
    return {
      lcp_ms: null,
      inp_ms: null,
      cls: null,
    };
  }

  const getEntries = (entryType: string) => {
    try {
      return performance.getEntriesByType(entryType);
    } catch {
      return [];
    }
  };

  const lcpEntries = getEntries('largest-contentful-paint');
  const lcpEntry = lcpEntries[lcpEntries.length - 1];
  const paintEntries = getEntries('paint');
  let contentPaint: PerformanceEntry | undefined;

  for (let index = paintEntries.length - 1; index >= 0; index -= 1) {
    const entry = paintEntries[index];

    if (entry.name === 'first-contentful-paint') {
      contentPaint = entry;
      break;
    }
  }

  const eventEntries = getEntries('event') as Array<PerformanceEntry & { duration?: number }>;
  let inpEntry: (PerformanceEntry & { duration?: number }) | undefined;

  for (let index = eventEntries.length - 1; index >= 0; index -= 1) {
    const entry = eventEntries[index];

    if (typeof entry.duration === 'number' && entry.duration > 0) {
      inpEntry = entry;
      break;
    }
  }

  const layoutShiftEntries = getEntries('layout-shift') as Array<PerformanceEntry & {
    value?: number;
    hadRecentInput?: boolean;
  }>;

  const clsValue = layoutShiftEntries.reduce((total, entry) => {
    if (entry.hadRecentInput || typeof entry.value !== 'number') {
      return total;
    }

    return total + entry.value;
  }, 0);

  return {
    lcp_ms: lcpEntry ? Math.round(lcpEntry.startTime) : contentPaint ? Math.round(contentPaint.startTime) : null,
    inp_ms: inpEntry && typeof inpEntry.duration === 'number' ? Math.round(inpEntry.duration) : null,
    cls: layoutShiftEntries.length > 0 ? Number(clsValue.toFixed(3)) : null,
  };
}

export default function GalleryBuilderPage() {
  const { id } = useParams<{ id: string }>();
  const eventId = id ?? null;
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const settingsQuery = useGalleryBuilderSettings(eventId);
  const presetsQuery = useGalleryPresets();
  const revisionsQuery = useGalleryRevisions(eventId);
  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(eventId ?? 'missing'),
    enabled: !!eventId,
    queryFn: () => getEventDetail(eventId as string),
  });
  const mediaQuery = useQuery({
    queryKey: ['gallery-builder', 'media', eventId ?? 'missing'],
    enabled: !!eventId,
    queryFn: () => listEventMedia(eventId as string, 24),
  });

  const [mode, setMode] = useState<GalleryBuilderMode>('quick');
  const [viewport, setViewport] = useState<GalleryBuilderViewport>('mobile');
  const [draft, setDraft] = useState<GalleryBuilderSettings | null>(null);
  const [operationalFeedback, setOperationalFeedback] = useState<GalleryBuilderOperationalFeedback>(EMPTY_OPERATIONAL_FEEDBACK);
  const [autosaveState, setAutosaveState] = useState<AutosaveState>('idle');
  const [statusMessage, setStatusMessage] = useState('Builder pronto para edicao.');
  const [alertMessage, setAlertMessage] = useState<string | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [previewExpiresAt, setPreviewExpiresAt] = useState<string | null>(null);
  const [aiPromptText, setAiPromptText] = useState('');
  const [aiTargetLayer, setAiTargetLayer] = useState<GalleryAiTargetLayer>('mixed');
  const [aiRun, setAiRun] = useState<GalleryAiProposalRun | null>(null);
  const [aiVariations, setAiVariations] = useState<GalleryAiVariation[]>([]);
  const [aiApplyingVariationId, setAiApplyingVariationId] = useState<string | null>(null);
  const [aiPreviewRequired, setAiPreviewRequired] = useState(false);
  const [previewLatencyMs, setPreviewLatencyMs] = useState<number | null>(null);
  const [publishLatencyMs, setPublishLatencyMs] = useState<number | null>(null);

  const lastSyncedSignatureRef = useRef('');
  const vitalsTelemetrySignatureRef = useRef('');
  const aiProposalsMutation = useGalleryAiProposals(eventId);
  const media = mediaQuery.data?.data ?? [];

  const renderMode = useMemo<GalleryRenderMode>(() => {
    if (!draft || !settingsQuery.data) {
      return 'standard';
    }

    return resolveGalleryRenderModeForBuilder({
      draft,
      itemCount: media.length,
      viewport,
      trigger: settingsQuery.data.optimized_renderer_trigger,
    });
  }, [draft, media.length, settingsQuery.data, viewport]);

  function updateBuilderCache(updater: (current: GalleryBuilderShowResponse) => GalleryBuilderShowResponse) {
    if (!eventId) {
      return;
    }

    queryClient.setQueryData<GalleryBuilderShowResponse | undefined>(
      queryKeys.gallery.settings(eventId),
      (current) => (current ? updater(current) : current),
    );
  }

  function updateSettingsCache(settings: GalleryBuilderSettings) {
    updateBuilderCache((current) => ({ ...current, settings }));
  }

  function updateOperationalFeedbackCache(nextFeedback: GalleryBuilderOperationalFeedback) {
    updateBuilderCache((current) => ({
      ...current,
      settings: {
        ...current.settings,
        current_preset_origin: nextFeedback.current_preset_origin ?? current.settings.current_preset_origin,
      },
      operational_feedback: nextFeedback,
    }));
  }

  function prependRevision(revision?: GalleryBuilderRevision) {
    if (!eventId || !revision) {
      return;
    }

    queryClient.setQueryData<GalleryBuilderRevision[] | undefined>(
      queryKeys.gallery.revisions(eventId),
      (current) => [
        revision,
        ...(current ?? []).filter((item) => item.id !== revision.id),
      ],
    );
  }

  function syncDraftFromServer(settings: GalleryBuilderSettings) {
    lastSyncedSignatureRef.current = serializeSettings(settings);
    setDraft((current) => ({
      ...settings,
      current_preset_origin: settings.current_preset_origin
        ?? current?.current_preset_origin
        ?? operationalFeedback.current_preset_origin,
    }));
    setAutosaveState(settings.last_autosaved_at ? 'saved' : 'idle');
    setPreviewUrl((current) => current ?? settings.preview_url);
    setPreviewExpiresAt((current) => current ?? settings.preview_share_expires_at);
  }

  function syncOperationalFeedback(nextFeedback: GalleryBuilderOperationalFeedback) {
    setOperationalFeedback(nextFeedback);
    setDraft((current) => (
      current
        ? {
            ...current,
            current_preset_origin: nextFeedback.current_preset_origin ?? current.current_preset_origin,
          }
        : current
    ));
  }

  function applyCurrentPresetOrigin(origin: GalleryPresetOrigin | null) {
    setDraft((current) => (
      current
        ? {
            ...current,
            current_preset_origin: origin,
          }
        : current
    ));

    const nextFeedback = {
      ...operationalFeedback,
      current_preset_origin: origin,
    };

    setOperationalFeedback(nextFeedback);
    updateOperationalFeedbackCache(nextFeedback);
  }

  function patchOperationalFeedback(patch: Partial<GalleryBuilderOperationalFeedback>) {
    const nextFeedback = {
      ...operationalFeedback,
      ...patch,
    };

    setOperationalFeedback(nextFeedback);
    updateOperationalFeedbackCache(nextFeedback);
  }

  async function submitTelemetry(payload: GalleryBuilderTelemetryPayload) {
    if (!eventId) {
      return;
    }

    try {
      const result = await trackEventGalleryBuilderTelemetry(eventId, payload);

      if (result.current_preset_origin !== undefined) {
        setDraft((current) => (
          current
            ? {
                ...current,
                current_preset_origin: result.current_preset_origin,
              }
            : current
        ));
      }

      syncOperationalFeedback(result.operational_feedback);
      updateOperationalFeedbackCache(result.operational_feedback);
    } catch {
      // Telemetry is best-effort and must not block editing flows.
    }
  }

  useEffect(() => {
    if (settingsQuery.data) {
      syncDraftFromServer(settingsQuery.data.settings);
      syncOperationalFeedback(settingsQuery.data.operational_feedback);
    }
  }, [settingsQuery.data]);

  const saveDraftMutation = useMutation({
    mutationFn: async ({ settings }: { settings: GalleryBuilderSettings; source: SaveSource }) => {
      await updateEventGallerySettings(eventId as string, extractUpdatePayload(settings));

      return autosaveEventGalleryDraft(eventId as string);
    },
    onMutate: () => {
      setAutosaveState('saving');
      setStatusMessage('Salvando rascunho...');
      setAlertMessage(null);
    },
    onSuccess: (result, variables) => {
      syncDraftFromServer(result.settings);
      updateSettingsCache(result.settings);
      prependRevision(result.revision);
      setAlertMessage(null);
      setStatusMessage(variables.source === 'autosave'
        ? 'Rascunho salvo automaticamente.'
        : 'Rascunho salvo com sucesso.');

      if (variables.source === 'manual') {
        toast({ title: 'Rascunho salvo' });
      }
    },
    onError: (error: Error, variables) => {
      setAutosaveState('error');
      setAlertMessage(`Falha ao salvar o rascunho: ${error.message}`);

      if (variables.source !== 'autosave') {
        toast({
          title: 'Falha ao salvar o rascunho',
          description: error.message,
          variant: 'destructive',
        });
      }
    },
  });

  const publishMutation = useMutation({
    mutationFn: () => publishEventGalleryDraft(eventId as string),
    onSuccess: (result) => {
      syncDraftFromServer(result.settings);
      updateSettingsCache(result.settings);
      prependRevision(result.revision);
      patchOperationalFeedback({
        last_publish: {
          revision_id: result.revision.id,
          version_number: result.revision.version_number,
          occurred_at: result.revision.created_at,
          actor: result.revision.creator,
          change_reason: result.revision.change_summary?.reason ?? 'Publicacao do draft',
        },
      });
      setAlertMessage(null);
      setStatusMessage(`Galeria publicada na versao ${result.revision.version_number}.`);
      toast({ title: 'Galeria publicada' });
    },
    onError: (error: Error) => {
      setAlertMessage(`Falha ao publicar a galeria: ${error.message}`);
      toast({
        title: 'Falha ao publicar a galeria',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const previewMutation = useMutation({
    mutationFn: () => createEventGalleryPreviewLink(eventId as string),
    onSuccess: (result) => {
      setPreviewUrl(result.preview_url);
      setPreviewExpiresAt(result.expires_at);
      setAiPreviewRequired(false);
      prependRevision(result.revision);
      setAlertMessage(null);
      setStatusMessage('Preview compartilhavel gerado com sucesso.');
      toast({ title: 'Preview compartilhavel gerado' });
    },
    onError: (error: Error) => {
      setAlertMessage(`Falha ao gerar preview: ${error.message}`);
      toast({
        title: 'Falha ao gerar preview',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const restoreMutation = useMutation({
    mutationFn: (revisionId: number) => restoreEventGalleryRevision(eventId as string, revisionId),
    onSuccess: (result) => {
      syncDraftFromServer(result.settings);
      setAiPreviewRequired(false);
      updateSettingsCache(result.settings);
      prependRevision(result.revision);
      patchOperationalFeedback({
        current_preset_origin: result.settings.current_preset_origin,
        last_restore: {
          revision_id: result.revision.id,
          version_number: result.revision.version_number,
          occurred_at: result.revision.created_at,
          actor: result.revision.creator,
          change_reason: result.revision.change_summary?.reason ?? 'Restore executado',
          source_revision_id: Number(result.revision.change_summary?.restored_from_revision_id ?? 0) || null,
          source_version_number: Number(result.revision.change_summary?.restored_from_version_number ?? 0) || null,
        },
      });
      setAlertMessage(null);
      setStatusMessage(`Revisao ${result.revision.version_number} restaurada com sucesso.`);
      toast({ title: 'Versao restaurada' });
    },
    onError: (error: Error) => {
      setAlertMessage(`Falha ao restaurar a revisao: ${error.message}`);
      toast({
        title: 'Falha ao restaurar a revisao',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  useEffect(() => {
    if (!draft || !eventId) {
      return;
    }

    const currentSignature = serializeSettings(draft);

    if (!lastSyncedSignatureRef.current || currentSignature === lastSyncedSignatureRef.current) {
      return;
    }

    setAutosaveState('dirty');

    const handle = window.setTimeout(() => {
      if (!saveDraftMutation.isPending) {
        saveDraftMutation.mutate({ settings: draft, source: 'autosave' });
      }
    }, 1200);

    return () => window.clearTimeout(handle);
  }, [draft, eventId, saveDraftMutation]);

  useEffect(() => {
    if (!eventId || !draft || !settingsQuery.data) {
      return undefined;
    }

    const payload = buildGalleryBuilderVitalsTelemetryPayload({
      draft,
      itemCount: media.length,
      viewport,
      renderMode,
      vitals: readGalleryBuilderVitalsSample(),
      previewLatencyMs,
      publishLatencyMs,
    });
    const signature = JSON.stringify(payload);

    if (signature === vitalsTelemetrySignatureRef.current) {
      return undefined;
    }

    vitalsTelemetrySignatureRef.current = signature;

    const handle = window.setTimeout(() => {
      void submitTelemetry(payload);
    }, 1500);

    return () => window.clearTimeout(handle);
  }, [draft, eventId, media.length, previewLatencyMs, publishLatencyMs, renderMode, settingsQuery.data, viewport]);

  async function ensureDraftSaved(source: SaveSource) {
    if (!draft) {
      return;
    }

    if (serializeSettings(draft) === lastSyncedSignatureRef.current) {
      return;
    }

    await saveDraftMutation.mutateAsync({ settings: draft, source });
  }

  function updateDraft(updater: (current: GalleryBuilderSettings) => GalleryBuilderSettings) {
    startTransition(() => {
      setDraft((current) => (current ? updater(current) : current));
    });
  }

  const eventSummary = {
    id: Number(eventId ?? settingsQuery.data?.event.id ?? 0),
    title: eventQuery.data?.title ?? settingsQuery.data?.event.title ?? 'Evento',
    slug: eventQuery.data?.slug ?? settingsQuery.data?.event.slug ?? '',
  };

  if (!eventId) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Evento invalido para o builder da galeria.
      </div>
    );
  }

  if (settingsQuery.isError) {
    return (
      <Alert>
        <AlertTitle>Builder indisponivel</AlertTitle>
        <AlertDescription>
          Nao foi possivel carregar as configuracoes da galeria deste evento.
        </AlertDescription>
      </Alert>
    );
  }

  if (settingsQuery.isLoading || !draft) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
        Carregando builder da galeria...
      </div>
    );
  }

  async function handleSaveNow() {
    await ensureDraftSaved('manual');
  }

  async function handlePublish() {
    if (aiPreviewRequired) {
      setAlertMessage('Preview obrigatorio antes de publicar. Gere um preview compartilhavel depois de aplicar uma variacao de IA.');
      toast({
        title: 'Preview obrigatorio antes de publicar',
        description: 'Gere um preview compartilhavel depois de aplicar uma variacao de IA.',
        variant: 'destructive',
      });

      return;
    }

    const startedAt = nowMs();
    await ensureDraftSaved('publish-prep');
    await publishMutation.mutateAsync();
    setPublishLatencyMs(Math.max(0, Math.round(nowMs() - startedAt)));
  }

  async function handleGeneratePreviewLink() {
    const startedAt = nowMs();
    await ensureDraftSaved('preview-prep');
    await previewMutation.mutateAsync();
    setPreviewLatencyMs(Math.max(0, Math.round(nowMs() - startedAt)));
  }

  async function handleGenerateAiProposals() {
    try {
      const result = await aiProposalsMutation.mutateAsync({
        prompt_text: aiPromptText.trim(),
        persona_key: 'operator',
        target_layer: aiTargetLayer,
        base_preset_key: draft.current_preset_origin?.key,
      });

      setAiRun(result.run);
      setAiVariations(result.variations);
      setAlertMessage(null);
      setStatusMessage('Variacoes de IA geradas com sucesso.');
      toast({ title: '3 variacoes seguras geradas' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao gerar variacoes';
      setAlertMessage(`Falha ao gerar variacoes de IA: ${message}`);
      toast({
        title: 'Falha ao gerar variacoes de IA',
        description: message,
        variant: 'destructive',
      });
    }
  }

  async function handleApplyAiVariation(variation: GalleryAiVariation, scope: GalleryAiApplyScope) {
    if (!draft) {
      return;
    }

    const nextDraft = applyGalleryAiVariationToDraft(draft, variation, scope);
    setAiApplyingVariationId(variation.id);
    setDraft(nextDraft);

    try {
      await saveDraftMutation.mutateAsync({ settings: nextDraft, source: 'manual' });
      setAiPreviewRequired(true);
      setStatusMessage(`Variacao ${variation.label} aplicada ao draft.`);
      setAlertMessage('Preview obrigatorio antes de publicar apos uma aplicacao de IA.');
      if (aiRun) {
        await submitTelemetry({
          event: 'ai_applied',
          run_id: aiRun.id,
          variation_id: variation.id,
          apply_scope: scope,
        });
      }
      toast({ title: 'Variacao de IA aplicada ao draft' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao aplicar variacao';
      setAlertMessage(`Falha ao aplicar variacao de IA: ${message}`);
      toast({
        title: 'Falha ao aplicar variacao de IA',
        description: message,
        variant: 'destructive',
      });
    } finally {
      setAiApplyingVariationId(null);
    }
  }

  return (
    <div className="space-y-6">
      <div className="sr-only" role="status" aria-live="polite">
        {statusMessage}
      </div>

      <PageHeader
        title="Gallery Builder"
        description="Modo rapido para operador leigo, modo profissional para ajuste fino e preview central usando o renderer publico."
        actions={(
          <div className="flex flex-wrap gap-2">
            <Button asChild variant="outline">
              <Link to={`/events/${eventId}`}>Abrir evento</Link>
            </Button>
            {eventSummary.slug ? (
              <Button asChild variant="outline">
                <a href={`/e/${eventSummary.slug}/gallery`} target="_blank" rel="noreferrer">
                  <ExternalLink className="h-4 w-4" />
                  Ver publica
                </a>
              </Button>
            ) : null}
          </div>
        )}
      />

      {alertMessage ? (
        <Alert variant="destructive">
          <AlertTitle>Atencao operacional</AlertTitle>
          <AlertDescription>{alertMessage}</AlertDescription>
        </Alert>
      ) : null}

      <GalleryModeSwitch
        value={mode}
        onChange={setMode}
        draftVersion={draft.draft_version}
        publishedVersion={draft.published_version}
        autosaveState={autosaveState}
      />

      <div className="grid gap-6 xl:grid-cols-[minmax(340px,420px)_minmax(0,1fr)]">
        <div className="space-y-6">
          {mode === 'quick' ? (
            <>
              <GalleryQuickStartWizard
                draft={draft}
                onApplySelection={(selection) => {
                  updateDraft((current) => applyMatrixSelectionToDraft(current, selection));
                  const origin = {
                    origin_type: 'wizard',
                    key: `${selection.event_type_family}.${selection.style_skin}.${selection.behavior_profile}`,
                    label: 'Base guiada do evento',
                    applied_at: new Date().toISOString(),
                    applied_by: null,
                  } satisfies GalleryPresetOrigin;

                  applyCurrentPresetOrigin(origin);
                  setStatusMessage('Base guiada do evento aplicada ao draft.');
                  void submitTelemetry({
                    event: 'preset_applied',
                    preset: {
                      origin_type: 'wizard',
                      key: origin.key ?? 'guided-base',
                      label: origin.label ?? 'Base guiada do evento',
                    },
                  });
                }}
              />
              <GalleryQuickSetupRail
                draft={draft}
                mobileBudget={settingsQuery.data.mobile_budget}
                responsiveSizes={settingsQuery.data.responsive_source_contract.sizes}
                operationalFeedback={operationalFeedback}
                onApplyShortcut={(fixtureKey) => {
                  updateDraft((current) => applyMatrixSelectionToDraft(current, QUICK_SHORTCUT_SELECTIONS[fixtureKey]));
                  const origin = {
                    origin_type: 'shortcut',
                    key: fixtureKey,
                    label: QUICK_SHORTCUT_LABELS[fixtureKey],
                    applied_at: new Date().toISOString(),
                    applied_by: null,
                  } satisfies GalleryPresetOrigin;

                  applyCurrentPresetOrigin(origin);
                  setStatusMessage(`Atalho ${QUICK_SHORTCUT_LABELS[fixtureKey]} aplicado ao draft.`);
                  void submitTelemetry({
                    event: 'preset_applied',
                    preset: {
                      origin_type: 'shortcut',
                      key: fixtureKey,
                      label: QUICK_SHORTCUT_LABELS[fixtureKey],
                    },
                  });
                }}
              />
              <GalleryContextInspector event={eventSummary} draft={draft} autosaveState={autosaveState} />
              <GalleryAiVariationsPanel
                promptText={aiPromptText}
                targetLayer={aiTargetLayer}
                run={aiRun}
                variations={aiVariations}
                isGenerating={aiProposalsMutation.isPending}
                isApplyingVariationId={aiApplyingVariationId}
                previewRequired={aiPreviewRequired}
                onPromptTextChange={setAiPromptText}
                onTargetLayerChange={setAiTargetLayer}
                onGenerate={handleGenerateAiProposals}
                onApplyVariation={handleApplyAiVariation}
              />
            </>
          ) : (
            <>
              <GalleryPresetRail
                presets={presetsQuery.data ?? []}
                appliedPresetName={draft.current_preset_origin?.label}
                onApplyPreset={(preset) => {
                  updateDraft((current) => applyPresetToDraft(current, preset));
                  const origin = {
                    origin_type: 'preset',
                    key: preset.slug,
                    label: preset.name,
                    applied_at: new Date().toISOString(),
                    applied_by: null,
                  } satisfies GalleryPresetOrigin;

                  applyCurrentPresetOrigin(origin);
                  setStatusMessage(`Preset ${preset.name} aplicado ao draft.`);
                  void submitTelemetry({
                    event: 'preset_applied',
                    preset: {
                      origin_type: 'preset',
                      key: preset.slug,
                      label: preset.name,
                    },
                  });
                }}
              />
              <GalleryThemePanel
                draft={draft}
                onThemeKeyChange={(themeKey) => {
                  updateDraft((current) => ({ ...current, theme_key: themeKey }));
                }}
                onPaletteChange={(key, value) => {
                  updateDraft((current) => ({
                    ...current,
                    theme_tokens: {
                      ...current.theme_tokens,
                      palette: {
                        ...current.theme_tokens.palette,
                        [key]: value,
                      },
                    },
                  }));
                }}
                onMotionPreferenceChange={(value) => {
                  updateDraft((current) => ({
                    ...current,
                    theme_tokens: {
                      ...current.theme_tokens,
                      motion: {
                        respect_user_preference: value,
                      },
                    },
                  }));
                }}
              />
              <GalleryBlocksPanel
                draft={draft}
                onLayoutKeyChange={(layoutKey) => {
                  updateDraft((current) => ({ ...current, layout_key: layoutKey }));
                }}
                onGridLayoutChange={(layout) => {
                  updateDraft((current) => ({
                    ...current,
                    media_behavior: {
                      ...current.media_behavior,
                      grid: {
                        ...current.media_behavior.grid,
                        layout,
                      },
                    },
                  }));
                }}
                onDensityChange={(density) => {
                  updateDraft((current) => ({
                    ...current,
                    media_behavior: {
                      ...current.media_behavior,
                      grid: {
                        ...current.media_behavior.grid,
                        density,
                      },
                    },
                  }));
                }}
                onVideoModeChange={(modeValue) => {
                  updateDraft((current) => ({
                    ...current,
                    media_behavior: {
                      ...current.media_behavior,
                      video: {
                        ...current.media_behavior.video,
                        mode: modeValue,
                        allow_inline_preview: modeValue === 'inline_preview',
                      },
                    },
                  }));
                }}
                onBlockToggle={(blockKey, enabled) => {
                  updateDraft((current) => ({
                    ...current,
                    page_schema: {
                      ...current.page_schema,
                      blocks: {
                        ...current.page_schema.blocks,
                        [blockKey]: {
                          ...(current.page_schema.blocks[blockKey] as Record<string, unknown>),
                          enabled,
                        },
                      },
                    },
                  }));
                }}
              />
              <GalleryContextInspector event={eventSummary} draft={draft} autosaveState={autosaveState} />
              <GalleryAiVariationsPanel
                promptText={aiPromptText}
                targetLayer={aiTargetLayer}
                run={aiRun}
                variations={aiVariations}
                isGenerating={aiProposalsMutation.isPending}
                isApplyingVariationId={aiApplyingVariationId}
                previewRequired={aiPreviewRequired}
                onPromptTextChange={setAiPromptText}
                onTargetLayerChange={setAiTargetLayer}
                onGenerate={handleGenerateAiProposals}
                onApplyVariation={handleApplyAiVariation}
              />
            </>
          )}
        </div>

        <div className="space-y-6">
          <GalleryPreviewToolbar
            viewport={viewport}
            onViewportChange={setViewport}
            settings={draft}
            autosaveState={autosaveState}
            onSaveNow={handleSaveNow}
            onPublish={handlePublish}
            isSaving={saveDraftMutation.isPending}
            isPublishing={publishMutation.isPending}
            renderMode={renderMode}
            operationalFeedback={operationalFeedback}
            publishBlockedReason={aiPreviewRequired
              ? 'Gere um preview compartilhavel apos aplicar uma variacao de IA.'
              : null}
          />

          <GalleryPreviewFrame
            event={eventSummary}
            draft={draft}
            media={media}
            viewport={viewport}
            renderMode={renderMode}
          />

          <GalleryRevisionPanel
            revisions={revisionsQuery.data ?? []}
            settings={draft}
            operationalFeedback={operationalFeedback}
            previewUrl={previewUrl}
            previewExpiresAt={previewExpiresAt}
            onRestore={(revisionId) => restoreMutation.mutate(revisionId)}
            onGeneratePreviewLink={handleGeneratePreviewLink}
            isRestoringId={restoreMutation.variables ?? null}
            isGeneratingPreview={previewMutation.isPending}
          />
        </div>
      </div>
    </div>
  );
}

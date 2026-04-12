import { startTransition, useEffect, useRef, useState } from 'react';
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
  updateEventGallerySettings,
  type GalleryBuilderSettingsUpdatePayload,
} from './api';
import {
  applyGalleryAiVariationToDraft,
  applyMatrixSelectionToDraft,
  mergeGalleryLayers,
  type GalleryAiApplyScope,
  type GalleryAiProposalRun,
  type GalleryAiTargetLayer,
  type GalleryAiVariation,
  type GalleryBuilderMode,
  type GalleryBuilderPreset,
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
  const [autosaveState, setAutosaveState] = useState<AutosaveState>('idle');
  const [lastAppliedPresetName, setLastAppliedPresetName] = useState<string | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [previewExpiresAt, setPreviewExpiresAt] = useState<string | null>(null);
  const [aiPromptText, setAiPromptText] = useState('');
  const [aiTargetLayer, setAiTargetLayer] = useState<GalleryAiTargetLayer>('mixed');
  const [aiRun, setAiRun] = useState<GalleryAiProposalRun | null>(null);
  const [aiVariations, setAiVariations] = useState<GalleryAiVariation[]>([]);
  const [aiApplyingVariationId, setAiApplyingVariationId] = useState<string | null>(null);
  const [aiPreviewRequired, setAiPreviewRequired] = useState(false);

  const lastSyncedSignatureRef = useRef('');
  const aiProposalsMutation = useGalleryAiProposals(eventId);

  function updateSettingsCache(settings: GalleryBuilderSettings) {
    if (!eventId) {
      return;
    }

    queryClient.setQueryData<GalleryBuilderShowResponse | undefined>(
      queryKeys.gallery.settings(eventId),
      (current) => (current ? { ...current, settings } : current),
    );
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
    setDraft(settings);
    setAutosaveState(settings.last_autosaved_at ? 'saved' : 'idle');
    setPreviewUrl((current) => current ?? settings.preview_url);
    setPreviewExpiresAt((current) => current ?? settings.preview_share_expires_at);
  }

  useEffect(() => {
    if (settingsQuery.data?.settings) {
      syncDraftFromServer(settingsQuery.data.settings);
    }
  }, [settingsQuery.data?.settings]);

  const saveDraftMutation = useMutation({
    mutationFn: async ({ settings }: { settings: GalleryBuilderSettings; source: SaveSource }) => {
      await updateEventGallerySettings(eventId as string, extractUpdatePayload(settings));

      return autosaveEventGalleryDraft(eventId as string);
    },
    onMutate: () => {
      setAutosaveState('saving');
    },
    onSuccess: (result, variables) => {
      syncDraftFromServer(result.settings);
      updateSettingsCache(result.settings);
      prependRevision(result.revision);

      if (variables.source === 'manual') {
        toast({ title: 'Rascunho salvo' });
      }
    },
    onError: (error: Error, variables) => {
      setAutosaveState('error');

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
      toast({ title: 'Galeria publicada' });
    },
    onError: (error: Error) => {
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
      toast({ title: 'Preview compartilhavel gerado' });
    },
    onError: (error: Error) => {
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
      toast({ title: 'Versao restaurada' });
    },
    onError: (error: Error) => {
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

  const media = mediaQuery.data?.data ?? [];

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
      toast({
        title: 'Preview obrigatorio antes de publicar',
        description: 'Gere um preview compartilhavel depois de aplicar uma variacao de IA.',
        variant: 'destructive',
      });

      return;
    }

    await ensureDraftSaved('publish-prep');
    await publishMutation.mutateAsync();
  }

  async function handleGeneratePreviewLink() {
    await ensureDraftSaved('preview-prep');
    await previewMutation.mutateAsync();
  }

  async function handleGenerateAiProposals() {
    try {
      const result = await aiProposalsMutation.mutateAsync({
        prompt_text: aiPromptText.trim(),
        persona_key: 'operator',
        target_layer: aiTargetLayer,
        base_preset_key: lastAppliedPresetName,
      });

      setAiRun(result.run);
      setAiVariations(result.variations);
      toast({ title: '3 variacoes seguras geradas' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao gerar variacoes';
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
      toast({ title: 'Variacao de IA aplicada ao draft' });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Falha ao aplicar variacao';
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
                  setLastAppliedPresetName('Base guiada do evento');
                }}
              />
              <GalleryQuickSetupRail
                draft={draft}
                mobileBudget={settingsQuery.data.mobile_budget}
                responsiveSizes={settingsQuery.data.responsive_source_contract.sizes}
                lastAppliedPresetName={lastAppliedPresetName}
                onApplyShortcut={(fixtureKey) => {
                  updateDraft((current) => applyMatrixSelectionToDraft(current, QUICK_SHORTCUT_SELECTIONS[fixtureKey]));
                  setLastAppliedPresetName(fixtureKey);
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
                appliedPresetName={lastAppliedPresetName}
                onApplyPreset={(preset) => {
                  updateDraft((current) => applyPresetToDraft(current, preset));
                  setLastAppliedPresetName(preset.name);
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
            publishBlockedReason={aiPreviewRequired
              ? 'Gere um preview compartilhavel apos aplicar uma variacao de IA.'
              : null}
          />

          <GalleryPreviewFrame
            event={eventSummary}
            draft={draft}
            media={media}
            viewport={viewport}
          />

          <GalleryRevisionPanel
            revisions={revisionsQuery.data ?? []}
            settings={draft}
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

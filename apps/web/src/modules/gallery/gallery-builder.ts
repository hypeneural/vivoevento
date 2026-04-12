import {
  GALLERY_BEHAVIOR_PROFILES,
  GALLERY_BLOCK_KEYS,
  GALLERY_DENSITIES,
  GALLERY_EVENT_TYPE_FAMILIES,
  GALLERY_INTERSTITIAL_POLICIES,
  GALLERY_LAYOUT_KEYS,
  GALLERY_MOBILE_BUDGET,
  GALLERY_STYLE_SKINS,
  GALLERY_THEME_KEYS,
  GALLERY_VIDEO_MODES,
  type GalleryBehaviorProfile,
  type GalleryDensity,
  type GalleryEventTypeFamily,
  type GalleryExperienceConfig,
  type GalleryInterstitialPolicy,
  type GalleryLayoutKey,
  type GalleryMediaBehavior,
  type GalleryModelMatrixSelection,
  type GalleryPageSchema,
  type GalleryStyleSkin,
  type GalleryThemeKey,
  type GalleryThemeTokens,
} from '@eventovivo/shared-types';

export interface GalleryBuilderExperienceLayers {
  event_type_family: GalleryEventTypeFamily;
  style_skin: GalleryStyleSkin;
  behavior_profile: GalleryBehaviorProfile;
  theme_key: GalleryThemeKey;
  layout_key: GalleryLayoutKey;
  theme_tokens: GalleryThemeTokens;
  page_schema: GalleryPageSchema;
  media_behavior: GalleryMediaBehavior;
}

export interface GalleryBuilderEventSummary {
  id: number;
  title: string;
  slug: string;
}

export interface GalleryBuilderActorSummary {
  id: number | null;
  name: string | null;
}

export interface GalleryPresetOrigin {
  origin_type: string | null;
  key: string | null;
  label: string | null;
  applied_at: string | null;
  applied_by: GalleryBuilderActorSummary | null;
}

export interface GalleryBuilderOperationalRevisionFeedback {
  revision_id: number;
  version_number: number;
  occurred_at: string | null;
  actor: GalleryBuilderActorSummary | null;
  change_reason: string | null;
  source_revision_id?: number | null;
  source_version_number?: number | null;
}

export interface GalleryBuilderLastAiApplication {
  run_id: number;
  variation_id: string | null;
  apply_scope: GalleryAiApplyScope | null;
  prompt_text: string;
  target_layer: GalleryAiTargetLayer;
  occurred_at: string | null;
  actor: GalleryBuilderActorSummary | null;
}

export interface GalleryBuilderOperationalFeedback {
  current_preset_origin: GalleryPresetOrigin | null;
  last_ai_application: GalleryBuilderLastAiApplication | null;
  last_publish: GalleryBuilderOperationalRevisionFeedback | null;
  last_restore: GalleryBuilderOperationalRevisionFeedback | null;
}

export interface GalleryOptimizedRendererTrigger {
  item_count: number;
  estimated_rendered_height_px: number;
}

export interface GalleryBuilderSettings extends GalleryBuilderExperienceLayers {
  id: number;
  event_id: number;
  is_enabled: boolean;
  current_preset_origin: GalleryPresetOrigin | null;
  current_draft_revision_id: number | null;
  current_published_revision_id: number | null;
  preview_revision_id: number | null;
  draft_version: number;
  published_version: number;
  preview_share_token: string | null;
  preview_url: string | null;
  preview_share_expires_at: string | null;
  last_autosaved_at: string | null;
  updated_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface GalleryBuilderRevision extends GalleryBuilderExperienceLayers {
  id: number;
  event_id: number;
  version_number: number;
  kind: 'autosave' | 'publish' | 'restore' | string;
  change_summary: {
    reason?: string | null;
    source?: string | null;
    layers?: string[];
  } | null;
  creator: {
    id: number;
    name: string;
  } | null;
  created_at: string | null;
}

export interface GalleryBuilderPreset {
  id: number;
  organization_id: number;
  name: string;
  slug: string;
  description: string | null;
  event_type_family: GalleryEventTypeFamily;
  style_skin: GalleryStyleSkin;
  behavior_profile: GalleryBehaviorProfile;
  theme_key: GalleryThemeKey;
  layout_key: GalleryLayoutKey;
  derived_preset_key: string | null;
  source_event: {
    id: number;
    title: string;
    slug: string;
  } | null;
  creator: {
    id: number;
    name: string;
  } | null;
  payload: {
    theme_tokens: GalleryThemeTokens;
    page_schema: GalleryPageSchema;
    media_behavior: GalleryMediaBehavior;
  };
  created_at: string | null;
  updated_at: string | null;
}

export interface GalleryBuilderResponsiveSourceContract {
  sizes: string;
  required_variant_fields: string[];
  target_widths: number[];
}

export interface GalleryBuilderShowResponse {
  event: GalleryBuilderEventSummary;
  settings: GalleryBuilderSettings;
  mobile_budget: typeof GALLERY_MOBILE_BUDGET;
  responsive_source_contract: GalleryBuilderResponsiveSourceContract;
  optimized_renderer_trigger: GalleryOptimizedRendererTrigger;
  operational_feedback: GalleryBuilderOperationalFeedback;
}

export interface GalleryBuilderPreviewLinkResponse {
  token: string;
  preview_url: string;
  expires_at: string | null;
  revision: GalleryBuilderRevision;
}

export interface GalleryBuilderMutationResult {
  settings: GalleryBuilderSettings;
  revision?: GalleryBuilderRevision;
}

export type GalleryGridLayout = GalleryMediaBehavior['grid']['layout'];
export type GalleryRenderMode = 'standard' | 'optimized';

export interface GalleryBuilderTelemetryResponse {
  current_preset_origin: GalleryPresetOrigin | null;
  operational_feedback: GalleryBuilderOperationalFeedback;
}

export interface GalleryPresetAppliedTelemetryPayload {
  event: 'preset_applied';
  preset: {
    origin_type: 'preset' | 'shortcut' | 'wizard';
    key: string;
    label: string;
  };
}

export interface GalleryAiAppliedTelemetryPayload {
  event: 'ai_applied';
  run_id: number;
  variation_id: string;
  apply_scope: GalleryAiApplyScope;
}

export interface GalleryBuilderVitalsSnapshot {
  lcp_ms: number | null;
  inp_ms: number | null;
  cls: number | null;
}

export interface GalleryBuilderVitalsSampleTelemetryPayload extends GalleryBuilderVitalsSnapshot {
  event: 'vitals_sample';
  viewport: GalleryBuilderViewport;
  item_count: number;
  layout: GalleryGridLayout;
  density: GalleryDensity;
  render_mode: GalleryRenderMode;
  preview_latency_ms: number | null;
  publish_latency_ms: number | null;
}

export type GalleryBuilderTelemetryPayload =
  | GalleryPresetAppliedTelemetryPayload
  | GalleryAiAppliedTelemetryPayload
  | GalleryBuilderVitalsSampleTelemetryPayload;

type DeepPartial<T> = {
  [K in keyof T]?: T[K] extends Array<unknown>
    ? T[K]
    : T[K] extends Record<string, unknown>
      ? DeepPartial<T[K]>
      : T[K];
};

export type GalleryAiTargetLayer = 'mixed' | 'theme_tokens' | 'page_schema' | 'media_behavior';
export type GalleryAiApplyScope = 'all' | Exclude<GalleryAiTargetLayer, 'mixed'>;

export interface GalleryAiVariationModelMatrix {
  event_type_family: GalleryEventTypeFamily;
  style_skin: GalleryStyleSkin;
  behavior_profile: GalleryBehaviorProfile;
  theme_key: GalleryThemeKey;
  layout_key: GalleryLayoutKey;
}

export interface GalleryAiVariationPatch {
  theme_tokens?: DeepPartial<GalleryThemeTokens>;
  page_schema?: DeepPartial<GalleryPageSchema>;
  media_behavior?: DeepPartial<GalleryMediaBehavior>;
}

export interface GalleryAiVariation {
  id: string;
  label: string;
  summary: string;
  scope: GalleryAiTargetLayer;
  available_layers: Array<Exclude<GalleryAiTargetLayer, 'mixed'>>;
  model_matrix: GalleryAiVariationModelMatrix;
  patch: GalleryAiVariationPatch;
}

export interface GalleryAiProposalRun {
  id: number;
  event_id: number;
  organization_id: number | null;
  user_id: number | null;
  prompt_text: string;
  persona_key: string | null;
  event_type_key: string | null;
  target_layer: GalleryAiTargetLayer;
  base_preset_key: string | null;
  response_schema_version: number;
  status: string;
  provider_key: string;
  model_key: string;
  created_at: string | null;
}

export interface GalleryAiProposalsResponse {
  run: GalleryAiProposalRun;
  variations: GalleryAiVariation[];
}

export type GalleryBuilderMode = 'quick' | 'professional';
export type GalleryBuilderViewport = 'mobile' | 'desktop';

export const galleryModelMatrixOptions = {
  eventTypeFamilies: [...GALLERY_EVENT_TYPE_FAMILIES],
  styleSkins: [...GALLERY_STYLE_SKINS],
  behaviorProfiles: [...GALLERY_BEHAVIOR_PROFILES],
} as const;

export const galleryModelMatrixLabels = {
  eventTypeFamily: {
    wedding: 'Casamento',
    quince: '15 anos',
    corporate: 'Corporativo',
  } satisfies Record<GalleryEventTypeFamily, string>,
  styleSkin: {
    romantic: 'Romantico',
    modern: 'Moderno',
    classic: 'Classico',
    premium: 'Premium',
    clean: 'Clean',
  } satisfies Record<GalleryStyleSkin, string>,
  behaviorProfile: {
    light: 'Leve',
    story: 'Historia',
    live: 'Ao vivo',
    sponsors: 'Patrocinios',
  } satisfies Record<GalleryBehaviorProfile, string>,
} as const;

export const galleryContractCatalog = {
  themeKeys: [...GALLERY_THEME_KEYS],
  layoutKeys: [...GALLERY_LAYOUT_KEYS],
  blockKeys: [...GALLERY_BLOCK_KEYS],
  videoModes: [...GALLERY_VIDEO_MODES],
  densities: [...GALLERY_DENSITIES],
  interstitialPolicies: [...GALLERY_INTERSTITIAL_POLICIES],
  mobileBudget: GALLERY_MOBILE_BUDGET,
  publicResponsiveSizes: '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw',
} as const;

function resolveThemeKey(eventTypeFamily: GalleryEventTypeFamily, styleSkin: GalleryStyleSkin) {
  if (eventTypeFamily === 'wedding') {
    return styleSkin === 'premium' ? 'black-tie' : 'wedding-rose';
  }

  if (eventTypeFamily === 'quince') {
    return 'quince-glam';
  }

  return styleSkin === 'premium' ? 'black-tie' : 'corporate-clean';
}

function resolveLayoutKey(
  eventTypeFamily: GalleryEventTypeFamily,
  behaviorProfile: GalleryBehaviorProfile,
) {
  if (behaviorProfile === 'live') {
    return 'live-stream';
  }

  if (behaviorProfile === 'story') {
    return eventTypeFamily === 'corporate' ? 'timeless-rows' : 'justified-story';
  }

  if (eventTypeFamily === 'corporate') {
    return 'timeless-rows';
  }

  return 'editorial-masonry';
}

export function createGalleryExperienceFixture(
  selection: GalleryModelMatrixSelection,
): GalleryExperienceConfig {
  const themeKey = resolveThemeKey(selection.event_type_family, selection.style_skin);
  const layoutKey = resolveLayoutKey(selection.event_type_family, selection.behavior_profile);

  return {
    version: 1,
    model_matrix: selection,
    theme_key: themeKey,
    layout_key: layoutKey,
    theme_tokens: {
      palette: {
        page_background: selection.event_type_family === 'corporate' ? '#f8fafc' : '#fff7f5',
        surface_background: '#ffffff',
        surface_border: selection.event_type_family === 'corporate' ? '#cbd5e1' : '#f5d0d6',
        text_primary: selection.event_type_family === 'corporate' ? '#0f172a' : '#4c0519',
        text_secondary: selection.event_type_family === 'corporate' ? '#334155' : '#9f1239',
        accent: selection.event_type_family === 'corporate' ? '#0f766e' : '#d97786',
        button_fill: selection.event_type_family === 'corporate' ? '#0f766e' : '#be185d',
        button_text: '#ffffff',
      },
      typography: {
        display_family_key: selection.style_skin === 'classic' || selection.style_skin === 'premium'
          ? 'editorial-serif'
          : 'clean-sans',
        body_family_key: 'clean-sans',
        title_scale: selection.style_skin === 'premium' ? 'lg' : 'md',
      },
      radius: {
        card: 'xl',
        button: 'pill',
        media: 'lg',
      },
      borders: {
        surface: 'soft',
        media: 'none',
      },
      shadows: {
        card: selection.style_skin === 'clean' ? 'none' : 'soft',
        hero: 'overlay-soft',
      },
      contrast_rules: {
        body_text_min_ratio: 4.5,
        large_text_min_ratio: 3,
        ui_min_ratio: 3,
      },
      motion: {
        respect_user_preference: true,
      },
    },
    page_schema: {
      block_order: ['hero', 'gallery_stream', 'banner_strip', 'quote', 'footer_brand'],
      blocks: {
        hero: {
          enabled: true,
          variant: selection.event_type_family,
          show_logo: true,
          show_face_search_cta: true,
          image_path: null,
          image_url: null,
        },
        gallery_stream: {
          enabled: true,
        },
        banner_strip: {
          enabled: selection.behavior_profile === 'sponsors',
          positions: ['after_12'],
          image_path: null,
          image_url: null,
        },
        quote: {
          enabled: selection.behavior_profile === 'story',
        },
        footer_brand: {
          enabled: true,
        },
      },
      presence_rules: {
        hero_required: true,
        max_banner_blocks: 2,
        require_preview_before_publish: true,
      },
    },
    media_behavior: {
      grid: {
        layout: layoutKey === 'timeless-rows'
          ? 'rows'
          : layoutKey === 'clean-columns'
            ? 'columns'
            : layoutKey === 'justified-story'
              ? 'justified'
              : 'masonry',
        density: selection.behavior_profile === 'live' ? 'compact' : 'comfortable',
        breakpoints: [360, 768, 1200],
      },
      pagination: {
        mode: 'infinite-scroll',
        page_size: 30,
        chunk_strategy: 'sectioned',
      },
      loading: {
        hero_and_first_band: 'eager',
        below_fold: 'lazy',
        content_visibility: 'auto',
      },
      lightbox: {
        photos: true,
        videos: false,
      },
      video: {
        allowed_modes: ['poster_only', 'poster_to_modal', 'inline_preview'],
        mode: selection.behavior_profile === 'live' ? 'inline_preview' : 'poster_to_modal',
        show_badge: true,
        allow_inline_preview: selection.behavior_profile === 'live',
      },
      interstitials: {
        enabled: selection.behavior_profile === 'sponsors',
        policy: selection.behavior_profile === 'sponsors' ? 'sponsors' : 'disabled',
        max_per_24_items: 1,
      },
    },
  };
}

export function buildGalleryExperienceFromBuilder(
  layers: GalleryBuilderExperienceLayers,
): GalleryExperienceConfig {
  return {
    version: 1,
    model_matrix: {
      event_type_family: layers.event_type_family,
      style_skin: layers.style_skin,
      behavior_profile: layers.behavior_profile,
    },
    theme_key: layers.theme_key,
    layout_key: layers.layout_key,
    theme_tokens: layers.theme_tokens,
    page_schema: layers.page_schema,
    media_behavior: layers.media_behavior,
  };
}

export function createGalleryBuilderSettingsFixture(
  overrides: Partial<GalleryBuilderSettings> = {},
): GalleryBuilderSettings {
  const baseExperience = createGalleryExperienceFixture({
    event_type_family: 'wedding',
    style_skin: 'romantic',
    behavior_profile: 'story',
  });

  return {
    id: 1,
    event_id: 42,
    is_enabled: true,
    current_preset_origin: null,
    event_type_family: baseExperience.model_matrix.event_type_family,
    style_skin: baseExperience.model_matrix.style_skin,
    behavior_profile: baseExperience.model_matrix.behavior_profile,
    theme_key: baseExperience.theme_key,
    layout_key: baseExperience.layout_key,
    theme_tokens: baseExperience.theme_tokens,
    page_schema: baseExperience.page_schema,
    media_behavior: baseExperience.media_behavior,
    current_draft_revision_id: 101,
    current_published_revision_id: 96,
    preview_revision_id: null,
    draft_version: 7,
    published_version: 5,
    preview_share_token: null,
    preview_url: null,
    preview_share_expires_at: null,
    last_autosaved_at: '2026-04-12T12:00:00Z',
    updated_by: 9,
    created_at: '2026-04-12T10:00:00Z',
    updated_at: '2026-04-12T12:00:00Z',
    ...overrides,
  };
}

export function createGalleryBuilderOperationalFeedbackFixture(
  overrides: Partial<GalleryBuilderOperationalFeedback> = {},
): GalleryBuilderOperationalFeedback {
  return {
    current_preset_origin: {
      origin_type: 'preset',
      key: 'wedding-premium-light',
      label: 'Wedding premium light',
      applied_at: '2026-04-12T12:10:00Z',
      applied_by: {
        id: 9,
        name: 'Operador',
      },
    },
    last_ai_application: {
      run_id: 401,
      variation_id: 'premium-album',
      apply_scope: 'all',
      prompt_text: 'quero uma galeria romantica em tons rose',
      target_layer: 'mixed',
      occurred_at: '2026-04-12T12:14:00Z',
      actor: {
        id: 9,
        name: 'Operador',
      },
    },
    last_publish: {
      revision_id: 205,
      version_number: 7,
      occurred_at: '2026-04-12T12:18:00Z',
      actor: {
        id: 9,
        name: 'Operador',
      },
      change_reason: 'Publicacao do draft',
    },
    last_restore: {
      revision_id: 206,
      version_number: 8,
      occurred_at: '2026-04-12T12:22:00Z',
      actor: {
        id: 9,
        name: 'Operador',
      },
      change_reason: 'Restaurado da versao 5',
      source_revision_id: 202,
      source_version_number: 5,
    },
    ...overrides,
  };
}

export function createGalleryOptimizedRendererTriggerFixture(
  overrides: Partial<GalleryOptimizedRendererTrigger> = {},
): GalleryOptimizedRendererTrigger {
  return {
    item_count: 500,
    estimated_rendered_height_px: 24000,
    ...overrides,
  };
}

export function createGalleryBuilderRevisionFixture(
  overrides: Partial<GalleryBuilderRevision> = {},
): GalleryBuilderRevision {
  const settings = createGalleryBuilderSettingsFixture();

  return {
    id: 201,
    event_id: settings.event_id,
    version_number: 7,
    kind: 'autosave',
    event_type_family: settings.event_type_family,
    style_skin: settings.style_skin,
    behavior_profile: settings.behavior_profile,
    theme_key: settings.theme_key,
    layout_key: settings.layout_key,
    theme_tokens: settings.theme_tokens,
    page_schema: settings.page_schema,
    media_behavior: settings.media_behavior,
    change_summary: {
      reason: 'Ajuste de paleta e hero',
      source: 'builder',
      layers: ['theme_tokens', 'page_schema'],
    },
    creator: {
      id: 9,
      name: 'Operador',
    },
    created_at: '2026-04-12T12:00:00Z',
    ...overrides,
  };
}

export function createGalleryBuilderPresetFixture(
  overrides: Partial<GalleryBuilderPreset> = {},
): GalleryBuilderPreset {
  const experience = createGalleryExperienceFixture({
    event_type_family: 'wedding',
    style_skin: 'premium',
    behavior_profile: 'light',
  });

  return {
    id: 301,
    organization_id: 10,
    name: 'Casamento premium',
    slug: 'casamento-premium',
    description: 'Preset editorial premium para casamento.',
    event_type_family: experience.model_matrix.event_type_family,
    style_skin: experience.model_matrix.style_skin,
    behavior_profile: experience.model_matrix.behavior_profile,
    theme_key: experience.theme_key,
    layout_key: experience.layout_key,
    derived_preset_key: 'wedding-premium-light',
    source_event: {
      id: 42,
      title: 'Casamento Ana e Leo',
      slug: 'casamento-ana-leo',
    },
    creator: {
      id: 9,
      name: 'Operador',
    },
    payload: {
      theme_tokens: experience.theme_tokens,
      page_schema: experience.page_schema,
      media_behavior: experience.media_behavior,
    },
    created_at: '2026-04-12T11:00:00Z',
    updated_at: '2026-04-12T11:30:00Z',
    ...overrides,
  };
}

export function createGalleryAiVariationFixture(
  overrides: Partial<GalleryAiVariation> = {},
): GalleryAiVariation {
  return {
    id: 'romantic-soft',
    label: 'Romantico suave',
    summary: 'Rose claro, hero mais editorial e grade masonry confortavel.',
    scope: 'mixed',
    available_layers: ['theme_tokens', 'page_schema', 'media_behavior'],
    model_matrix: {
      event_type_family: 'wedding',
      style_skin: 'romantic',
      behavior_profile: 'story',
      theme_key: 'wedding-rose',
      layout_key: 'justified-story',
    },
    patch: {
      theme_tokens: {
        palette: {
          accent: '#d97786',
        },
      },
      page_schema: {
        blocks: {
          hero: {
            show_logo: true,
          },
        },
      },
      media_behavior: {
        grid: {
          layout: 'masonry',
          density: 'comfortable',
        },
      },
    },
    ...overrides,
  };
}

export function createGalleryAiProposalsFixture(
  overrides: Partial<GalleryAiProposalsResponse> = {},
): GalleryAiProposalsResponse {
  return {
    run: {
      id: 401,
      event_id: 42,
      organization_id: 10,
      user_id: 9,
      prompt_text: 'quero uma galeria romantica em tons rose',
      persona_key: 'operator',
      event_type_key: 'wedding',
      target_layer: 'mixed',
      base_preset_key: 'wedding.romantic.story',
      response_schema_version: 1,
      status: 'success',
      provider_key: 'local-guardrailed',
      model_key: 'gallery-builder-local-v1',
      created_at: '2026-04-12T13:00:00Z',
    },
    variations: [
      createGalleryAiVariationFixture(),
      createGalleryAiVariationFixture({
        id: 'modern-clean',
        label: 'Moderno clean',
        summary: 'Menos ornamento, mais respiro visual e leitura limpa.',
        model_matrix: {
          event_type_family: 'wedding',
          style_skin: 'modern',
          behavior_profile: 'light',
          theme_key: 'wedding-rose',
          layout_key: 'editorial-masonry',
        },
        patch: {
          theme_tokens: {
            palette: {
              accent: '#475569',
            },
          },
          page_schema: {
            blocks: {
              quote: {
                enabled: false,
              },
            },
          },
          media_behavior: {
            grid: {
              layout: 'masonry',
              density: 'comfortable',
            },
          },
        },
      }),
      createGalleryAiVariationFixture({
        id: 'premium-album',
        label: 'Premium album',
        summary: 'Mais contraste editorial, peso visual elegante e ritmo de album.',
        model_matrix: {
          event_type_family: 'wedding',
          style_skin: 'premium',
          behavior_profile: 'light',
          theme_key: 'black-tie',
          layout_key: 'editorial-masonry',
        },
        patch: {
          theme_tokens: {
            palette: {
              accent: '#f8fafc',
            },
          },
          media_behavior: {
            grid: {
              layout: 'masonry',
              density: 'immersive',
            },
          },
        },
      }),
    ],
    ...overrides,
  };
}

export function mergeGalleryLayers(
  current: GalleryBuilderSettings,
  nextLayers: Partial<GalleryBuilderExperienceLayers>,
): GalleryBuilderSettings {
  return {
    ...current,
    ...nextLayers,
    theme_tokens: nextLayers.theme_tokens ?? current.theme_tokens,
    page_schema: nextLayers.page_schema ?? current.page_schema,
    media_behavior: nextLayers.media_behavior ?? current.media_behavior,
  };
}

function deepMergeLayer<T>(base: T, patch: DeepPartial<T> | undefined): T {
  if (!patch) {
    return base;
  }

  if (Array.isArray(base) || Array.isArray(patch)) {
    return patch as T;
  }

  if (
    typeof base !== 'object'
    || base === null
    || typeof patch !== 'object'
    || patch === null
  ) {
    return patch as T;
  }

  const result: Record<string, unknown> = { ...(base as Record<string, unknown>) };

  for (const [key, value] of Object.entries(patch as Record<string, unknown>)) {
    const currentValue = result[key];

    if (
      Array.isArray(value)
      || Array.isArray(currentValue)
      || typeof value !== 'object'
      || value === null
      || typeof currentValue !== 'object'
      || currentValue === null
    ) {
      result[key] = value;
      continue;
    }

    result[key] = deepMergeLayer(
      currentValue as Record<string, unknown>,
      value as Record<string, unknown>,
    );
  }

  return result as T;
}

export function applyGalleryAiVariationToDraft(
  current: GalleryBuilderSettings,
  variation: GalleryAiVariation,
  scope: GalleryAiApplyScope,
): GalleryBuilderSettings {
  const next = { ...current };

  if (scope === 'all') {
    next.event_type_family = variation.model_matrix.event_type_family;
    next.style_skin = variation.model_matrix.style_skin;
    next.behavior_profile = variation.model_matrix.behavior_profile;
    next.theme_key = variation.model_matrix.theme_key;
    next.layout_key = variation.model_matrix.layout_key;
  }

  if (scope === 'theme_tokens' || scope === 'all') {
    next.style_skin = variation.model_matrix.style_skin;
    next.theme_key = variation.model_matrix.theme_key;
    next.theme_tokens = deepMergeLayer(next.theme_tokens, variation.patch.theme_tokens);
  }

  if (scope === 'page_schema' || scope === 'all') {
    next.event_type_family = variation.model_matrix.event_type_family;
    next.page_schema = deepMergeLayer(next.page_schema, variation.patch.page_schema);
  }

  if (scope === 'media_behavior' || scope === 'all') {
    next.behavior_profile = variation.model_matrix.behavior_profile;
    next.layout_key = variation.model_matrix.layout_key;
    next.media_behavior = deepMergeLayer(next.media_behavior, variation.patch.media_behavior);
  }

  return next;
}

export function applyMatrixSelectionToDraft(
  current: GalleryBuilderSettings,
  selection: GalleryModelMatrixSelection,
): GalleryBuilderSettings {
  const nextExperience = createGalleryExperienceFixture(selection);
  const currentHero = current.page_schema.blocks.hero as { image_path?: string | null; image_url?: string | null } | undefined;
  const currentBanner = current.page_schema.blocks.banner_strip as { image_path?: string | null; image_url?: string | null } | undefined;
  const nextHero = nextExperience.page_schema.blocks.hero as Record<string, unknown>;
  const nextBanner = nextExperience.page_schema.blocks.banner_strip as Record<string, unknown>;

  return mergeGalleryLayers(current, {
    event_type_family: selection.event_type_family,
    style_skin: selection.style_skin,
    behavior_profile: selection.behavior_profile,
    theme_key: nextExperience.theme_key,
    layout_key: nextExperience.layout_key,
    theme_tokens: nextExperience.theme_tokens,
    page_schema: {
      ...nextExperience.page_schema,
      blocks: {
        ...nextExperience.page_schema.blocks,
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
    media_behavior: nextExperience.media_behavior,
  });
}

export function formatGalleryModelMatrix(
  selection: GalleryModelMatrixSelection | GalleryBuilderExperienceLayers,
) {
  return [
    galleryModelMatrixLabels.eventTypeFamily[selection.event_type_family],
    galleryModelMatrixLabels.styleSkin[selection.style_skin],
    galleryModelMatrixLabels.behaviorProfile[selection.behavior_profile],
  ].join(' / ');
}

export function formatGalleryDensity(density: GalleryDensity) {
  return density === 'compact'
    ? 'Compacta'
    : density === 'immersive'
      ? 'Imersiva'
      : 'Confortavel';
}

export function formatGalleryInterstitialPolicy(policy: GalleryInterstitialPolicy) {
  return policy === 'disabled'
    ? 'Desativado'
    : policy === 'story'
      ? 'Narrativa'
      : 'Patrocinios';
}

export function estimateGalleryRenderedHeightPx(options: {
  itemCount: number;
  layout: GalleryGridLayout;
  density: GalleryDensity;
  viewport: GalleryBuilderViewport;
}) {
  const itemCount = Math.max(0, options.itemCount);

  if (itemCount === 0) {
    return 0;
  }

  const columns = options.layout === 'rows'
    ? 1
    : options.viewport === 'mobile'
      ? 2
      : options.layout === 'columns'
        ? 3
        : 4;

  const rowHeight = options.layout === 'rows'
    ? (options.density === 'compact' ? 220 : options.density === 'immersive' ? 320 : 260)
    : (options.density === 'compact' ? 240 : options.density === 'immersive' ? 360 : 300);

  return Math.ceil(itemCount / columns) * rowHeight;
}

export function resolveGalleryRenderMode(options: {
  itemCount: number;
  estimatedRenderedHeightPx: number;
  trigger: GalleryOptimizedRendererTrigger;
}): GalleryRenderMode {
  if (
    options.itemCount >= options.trigger.item_count
    || options.estimatedRenderedHeightPx >= options.trigger.estimated_rendered_height_px
  ) {
    return 'optimized';
  }

  return 'standard';
}

export function resolveGalleryRenderModeForBuilder(options: {
  draft: Pick<GalleryBuilderSettings, 'media_behavior'>;
  itemCount: number;
  viewport: GalleryBuilderViewport;
  trigger: GalleryOptimizedRendererTrigger;
}) {
  const estimatedRenderedHeightPx = estimateGalleryRenderedHeightPx({
    itemCount: options.itemCount,
    layout: options.draft.media_behavior.grid.layout,
    density: options.draft.media_behavior.grid.density,
    viewport: options.viewport,
  });

  return resolveGalleryRenderMode({
    itemCount: options.itemCount,
    estimatedRenderedHeightPx,
    trigger: options.trigger,
  });
}

export function buildGalleryBuilderVitalsTelemetryPayload(options: {
  draft: Pick<GalleryBuilderSettings, 'media_behavior'>;
  itemCount: number;
  viewport: GalleryBuilderViewport;
  renderMode: GalleryRenderMode;
  vitals: GalleryBuilderVitalsSnapshot;
  previewLatencyMs?: number | null;
  publishLatencyMs?: number | null;
}): GalleryBuilderVitalsSampleTelemetryPayload {
  return {
    event: 'vitals_sample',
    viewport: options.viewport,
    item_count: options.itemCount,
    layout: options.draft.media_behavior.grid.layout,
    density: options.draft.media_behavior.grid.density,
    render_mode: options.renderMode,
    lcp_ms: options.vitals.lcp_ms,
    inp_ms: options.vitals.inp_ms,
    cls: options.vitals.cls,
    preview_latency_ms: options.previewLatencyMs ?? null,
    publish_latency_ms: options.publishLatencyMs ?? null,
  };
}

export const galleryExperienceFixtures = {
  weddingRomanticStory: createGalleryExperienceFixture({
    event_type_family: 'wedding',
    style_skin: 'romantic',
    behavior_profile: 'story',
  }),
  weddingPremiumLight: createGalleryExperienceFixture({
    event_type_family: 'wedding',
    style_skin: 'premium',
    behavior_profile: 'light',
  }),
  quinceModernLive: createGalleryExperienceFixture({
    event_type_family: 'quince',
    style_skin: 'modern',
    behavior_profile: 'live',
  }),
  corporateCleanSponsors: createGalleryExperienceFixture({
    event_type_family: 'corporate',
    style_skin: 'clean',
    behavior_profile: 'sponsors',
  }),
} as const;

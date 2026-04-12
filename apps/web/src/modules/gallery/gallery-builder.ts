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

export interface GalleryBuilderSettings extends GalleryBuilderExperienceLayers {
  id: number;
  event_id: number;
  is_enabled: boolean;
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

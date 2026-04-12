import {
  GALLERY_BEHAVIOR_PROFILES,
  GALLERY_BLOCK_KEYS,
  GALLERY_EVENT_TYPE_FAMILIES,
  GALLERY_LAYOUT_KEYS,
  GALLERY_MOBILE_BUDGET,
  GALLERY_STYLE_SKINS,
  GALLERY_THEME_KEYS,
  GALLERY_VIDEO_MODES,
  type GalleryBehaviorProfile,
  type GalleryEventTypeFamily,
  type GalleryExperienceConfig,
  type GalleryModelMatrixSelection,
  type GalleryStyleSkin,
} from '@eventovivo/shared-types';

export const galleryModelMatrixOptions = {
  eventTypeFamilies: [...GALLERY_EVENT_TYPE_FAMILIES],
  styleSkins: [...GALLERY_STYLE_SKINS],
  behaviorProfiles: [...GALLERY_BEHAVIOR_PROFILES],
} as const;

export const galleryContractCatalog = {
  themeKeys: [...GALLERY_THEME_KEYS],
  layoutKeys: [...GALLERY_LAYOUT_KEYS],
  blockKeys: [...GALLERY_BLOCK_KEYS],
  videoModes: [...GALLERY_VIDEO_MODES],
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
        },
        gallery_stream: {
          enabled: true,
        },
        banner_strip: {
          enabled: selection.behavior_profile === 'sponsors',
          positions: ['after_12'],
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

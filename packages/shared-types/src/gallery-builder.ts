export const GALLERY_EVENT_TYPE_FAMILIES = ['wedding', 'quince', 'corporate'] as const;
export const GALLERY_STYLE_SKINS = ['romantic', 'modern', 'classic', 'premium', 'clean'] as const;
export const GALLERY_BEHAVIOR_PROFILES = ['light', 'story', 'live', 'sponsors'] as const;

export const GALLERY_THEME_KEYS = [
  'event-brand',
  'pearl',
  'wedding-rose',
  'black-tie',
  'quince-glam',
  'corporate-clean',
] as const;

export const GALLERY_LAYOUT_KEYS = [
  'editorial-masonry',
  'timeless-rows',
  'clean-columns',
  'justified-story',
  'live-stream',
] as const;

export const GALLERY_BLOCK_KEYS = [
  'hero',
  'gallery_stream',
  'banner_strip',
  'info_cards',
  'quote',
  'cta_strip',
  'footer_brand',
] as const;

export const GALLERY_VIDEO_MODES = ['poster_only', 'poster_to_modal', 'inline_preview'] as const;
export const GALLERY_DENSITIES = ['compact', 'comfortable', 'immersive'] as const;
export const GALLERY_INTERSTITIAL_POLICIES = ['disabled', 'story', 'sponsors'] as const;

export type GalleryEventTypeFamily = typeof GALLERY_EVENT_TYPE_FAMILIES[number];
export type GalleryStyleSkin = typeof GALLERY_STYLE_SKINS[number];
export type GalleryBehaviorProfile = typeof GALLERY_BEHAVIOR_PROFILES[number];
export type GalleryThemeKey = typeof GALLERY_THEME_KEYS[number];
export type GalleryLayoutKey = typeof GALLERY_LAYOUT_KEYS[number];
export type GalleryBlockKey = typeof GALLERY_BLOCK_KEYS[number];
export type GalleryVideoMode = typeof GALLERY_VIDEO_MODES[number];
export type GalleryDensity = typeof GALLERY_DENSITIES[number];
export type GalleryInterstitialPolicy = typeof GALLERY_INTERSTITIAL_POLICIES[number];

export interface GalleryModelMatrixSelection {
  event_type_family: GalleryEventTypeFamily;
  style_skin: GalleryStyleSkin;
  behavior_profile: GalleryBehaviorProfile;
}

export interface GalleryThemeTokens {
  palette: {
    page_background: string;
    surface_background: string;
    surface_border: string;
    text_primary: string;
    text_secondary: string;
    accent: string;
    button_fill: string;
    button_text: string;
  };
  typography: {
    display_family_key: string;
    body_family_key: string;
    title_scale: 'sm' | 'md' | 'lg';
  };
  radius: {
    card: 'md' | 'lg' | 'xl';
    button: 'md' | 'lg' | 'pill';
    media: 'md' | 'lg' | 'xl';
  };
  borders: {
    surface: 'none' | 'soft' | 'strong';
    media: 'none' | 'soft' | 'strong';
  };
  shadows: {
    card: 'none' | 'soft' | 'strong';
    hero: 'none' | 'overlay-soft' | 'overlay-strong';
  };
  contrast_rules: {
    body_text_min_ratio: number;
    large_text_min_ratio: number;
    ui_min_ratio: number;
  };
  motion: {
    respect_user_preference: boolean;
  };
}

export interface GalleryPageSchema {
  block_order: GalleryBlockKey[];
  blocks: Record<string, Record<string, unknown>>;
  presence_rules: {
    hero_required: boolean;
    max_banner_blocks: number;
    require_preview_before_publish: boolean;
  };
}

export interface GalleryResponsiveSourceVariant {
  variant_key: string;
  src: string;
  width: number;
  height: number;
  mime_type: string;
}

export interface GalleryResponsiveSources {
  sizes: string;
  srcset: string;
  variants: GalleryResponsiveSourceVariant[];
}

export interface GalleryMediaBehavior {
  grid: {
    layout: 'masonry' | 'rows' | 'columns' | 'justified';
    density: GalleryDensity;
    breakpoints: number[];
  };
  pagination: {
    mode: 'page' | 'infinite-scroll';
    page_size: number;
    chunk_strategy: 'page' | 'sectioned';
  };
  loading: {
    hero_and_first_band: 'eager';
    below_fold: 'lazy';
    content_visibility: 'auto' | 'visible';
  };
  lightbox: {
    photos: boolean;
    videos: boolean;
  };
  video: {
    allowed_modes: GalleryVideoMode[];
    mode: GalleryVideoMode;
    show_badge: boolean;
    allow_inline_preview: boolean;
  };
  interstitials: {
    enabled: boolean;
    policy: GalleryInterstitialPolicy;
    max_per_24_items: number;
  };
}

export interface GalleryExperienceConfig {
  version: 1;
  model_matrix: GalleryModelMatrixSelection;
  theme_key: GalleryThemeKey;
  layout_key: GalleryLayoutKey;
  theme_tokens: GalleryThemeTokens;
  page_schema: GalleryPageSchema;
  media_behavior: GalleryMediaBehavior;
}

export interface GalleryMobileBudget {
  lcp_ms: number;
  inp_ms: number;
  cls: number;
  percentile: 75;
}

export const GALLERY_MOBILE_BUDGET: GalleryMobileBudget = {
  lcp_ms: 2500,
  inp_ms: 200,
  cls: 0.1,
  percentile: 75,
};

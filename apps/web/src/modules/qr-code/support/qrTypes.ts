import type {
  CornerDotType,
  CornerSquareType,
  DotType,
  DrawType,
  ErrorCorrectionLevel,
  FileExtension,
  GradientType,
  Mode,
  ShapeType,
  TypeNumber,
} from 'qr-code-styling';

export const QR_CONFIG_VERSION = 'event-public-link-qr.v1' as const;

export const QR_LINK_KEYS = ['gallery', 'upload', 'wall', 'hub', 'play', 'find_me'] as const;
export const QR_USAGE_PRESETS = [
  'telao',
  'upload_rapido',
  'galeria_premium',
  'impresso_pequeno',
  'convite_whatsapp',
] as const;
export const QR_SKIN_PRESETS = ['classico', 'premium', 'minimalista', 'escuro'] as const;
export const QR_READABILITY_STATUSES = ['great', 'good', 'risky'] as const;
export const QR_LOGO_MODES = ['none', 'event_logo', 'organization_logo', 'custom'] as const;

export type QrConfigVersion = typeof QR_CONFIG_VERSION;
export type QrLinkKey = (typeof QR_LINK_KEYS)[number];
export type QrUsagePreset = (typeof QR_USAGE_PRESETS)[number];
export type QrSkinPreset = (typeof QR_SKIN_PRESETS)[number];
export type QrReadabilityStatus = (typeof QR_READABILITY_STATUSES)[number];
export type QrLogoMode = (typeof QR_LOGO_MODES)[number];
export type QrExportExtension = FileExtension;

export type DeepPartial<T> = {
  [K in keyof T]?: T[K] extends Array<infer U>
    ? Array<DeepPartial<U>>
    : T[K] extends object
      ? DeepPartial<T[K]>
      : T[K];
};

export interface QrGradientStop {
  offset: number;
  color: string;
}

export interface QrGradientConfig {
  type: GradientType;
  rotation?: number;
  colorStops: QrGradientStop[];
}

export interface QrDotsStyle {
  type: DotType;
  color: string;
  gradient: QrGradientConfig | null;
}

export interface QrCornersSquareStyle {
  type: CornerSquareType;
  color: string;
  gradient: QrGradientConfig | null;
}

export interface QrCornersDotStyle {
  type: CornerDotType;
  color: string;
  gradient: QrGradientConfig | null;
}

export interface QrBackgroundStyle {
  color: string;
  gradient: QrGradientConfig | null;
  transparent: boolean;
}

export interface EventPublicLinkQrConfig {
  config_version: QrConfigVersion;
  usage_preset: QrUsagePreset;
  skin_preset: QrSkinPreset;
  render: {
    preview_type: DrawType;
    preview_size: number;
    margin_modules: number;
    background_mode: 'solid' | 'transparent';
  };
  style: {
    dots: QrDotsStyle;
    corners_square: QrCornersSquareStyle;
    corners_dot: QrCornersDotStyle;
    background: QrBackgroundStyle;
  };
  logo: {
    mode: QrLogoMode;
    asset_path: string | null;
    asset_url: string | null;
    image_size: number;
    margin_px: number;
    hide_background_dots: boolean;
    save_as_blob: boolean;
  };
  advanced: {
    error_correction_level: ErrorCorrectionLevel;
    shape: ShapeType;
    round_size: boolean;
    type_number: TypeNumber;
    mode: Mode;
  };
  export_defaults: {
    extension: QrExportExtension;
    size: number;
    download_name_pattern: string;
  };
}

export type EventPublicLinkQrConfigInput = (DeepPartial<EventPublicLinkQrConfig> & {
  version?: string | null;
  render?: DeepPartial<EventPublicLinkQrConfig['render']> & {
    margin?: number | null;
  };
  advanced?: DeepPartial<EventPublicLinkQrConfig['advanced']> & {
    error_correction?: ErrorCorrectionLevel | null;
  };
}) | null | undefined;

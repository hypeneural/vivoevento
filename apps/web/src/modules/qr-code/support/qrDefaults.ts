import type { DeepPartial, EventPublicLinkQrConfig, QrLinkKey, QrSkinPreset, QrUsagePreset } from './qrTypes';
import { QR_CONFIG_VERSION } from './qrTypes';
import { getDefaultUsagePresetForLinkKey } from './qrPresets';

function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function mergeRecords(
  target: Record<string, unknown>,
  patch: Record<string, unknown>,
): Record<string, unknown> {
  const result = { ...target };

  for (const [key, value] of Object.entries(patch)) {
    if (value === undefined) {
      continue;
    }

    const current = result[key];

    if (isRecord(current) && isRecord(value)) {
      result[key] = mergeRecords(current, value);
      continue;
    }

    result[key] = value;
  }

  return result;
}

export function mergeQrConfig<T>(base: T, ...patches: Array<unknown>): T {
  const seed = JSON.parse(JSON.stringify(base)) as T;

  return patches.reduce((accumulator, patch) => {
    if (!isRecord(accumulator) || !isRecord(patch)) {
      return accumulator;
    }

    return mergeRecords(accumulator, patch) as T;
  }, seed);
}

const BASE_QR_CONFIG: EventPublicLinkQrConfig = {
  config_version: QR_CONFIG_VERSION,
  usage_preset: 'galeria_premium',
  skin_preset: 'classico',
  render: {
    preview_type: 'svg',
    preview_size: 320,
    margin_modules: 4,
    background_mode: 'solid',
  },
  style: {
    dots: {
      type: 'rounded',
      color: '#0f172a',
      gradient: null,
    },
    corners_square: {
      type: 'extra-rounded',
      color: '#0f172a',
      gradient: null,
    },
    corners_dot: {
      type: 'dot',
      color: '#0f172a',
      gradient: null,
    },
    background: {
      color: '#ffffff',
      gradient: null,
      transparent: false,
    },
  },
  logo: {
    mode: 'none',
    asset_path: null,
    asset_url: null,
    image_size: 0.22,
    margin_px: 8,
    hide_background_dots: true,
    save_as_blob: true,
  },
  advanced: {
    error_correction_level: 'Q',
    shape: 'square',
    round_size: true,
    type_number: 0,
    mode: 'Byte',
  },
  export_defaults: {
    extension: 'svg',
    size: 1024,
    download_name_pattern: 'evento-{event_id}-{link_key}',
  },
};

const USAGE_PRESET_PATCHES: Record<QrUsagePreset, DeepPartial<EventPublicLinkQrConfig>> = {
  telao: {
    advanced: {
      error_correction_level: 'H',
    },
    export_defaults: {
      extension: 'png',
      size: 2048,
    },
    logo: {
      image_size: 0.18,
    },
  },
  upload_rapido: {
    export_defaults: {
      extension: 'png',
      size: 1024,
    },
  },
  galeria_premium: {
    export_defaults: {
      extension: 'svg',
      size: 1024,
    },
  },
  impresso_pequeno: {
    advanced: {
      error_correction_level: 'H',
    },
    logo: {
      image_size: 0.18,
    },
    export_defaults: {
      extension: 'png',
      size: 2048,
    },
  },
  convite_whatsapp: {
    export_defaults: {
      extension: 'png',
      size: 1024,
    },
  },
};

const SKIN_PRESET_PATCHES: Record<QrSkinPreset, DeepPartial<EventPublicLinkQrConfig>> = {
  classico: {},
  premium: {
    style: {
      dots: {
        type: 'rounded',
      },
      corners_square: {
        type: 'extra-rounded',
      },
    },
  },
  minimalista: {
    style: {
      dots: {
        type: 'square',
      },
      corners_square: {
        type: 'square',
      },
      corners_dot: {
        type: 'square',
      },
    },
  },
  escuro: {
    style: {
      dots: {
        color: '#020617',
      },
      corners_square: {
        color: '#020617',
      },
      corners_dot: {
        color: '#020617',
      },
      background: {
        color: '#ffffff',
      },
    },
  },
};

export function buildQrConfigDefaults(options?: {
  linkKey?: QrLinkKey;
  usagePreset?: QrUsagePreset;
  skinPreset?: QrSkinPreset;
}): EventPublicLinkQrConfig {
  const usagePreset = options?.usagePreset ?? (options?.linkKey ? getDefaultUsagePresetForLinkKey(options.linkKey) : 'galeria_premium');
  const skinPreset = options?.skinPreset ?? 'classico';

  return mergeQrConfig(
    BASE_QR_CONFIG,
    {
      usage_preset: usagePreset,
      skin_preset: skinPreset,
    },
    USAGE_PRESET_PATCHES[usagePreset],
    SKIN_PRESET_PATCHES[skinPreset],
  );
}

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

function createLinearGradient(colors: string[], rotation = 0) {
  return {
    type: 'linear' as const,
    rotation,
    colorStops: colors.map((color, index) => ({
      offset: colors.length === 1 ? 1 : index / (colors.length - 1),
      color,
    })),
  };
}

function createRadialGradient(colors: string[]) {
  return {
    type: 'radial' as const,
    colorStops: colors.map((color, index) => ({
      offset: colors.length === 1 ? 1 : index / (colors.length - 1),
      color,
    })),
  };
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
  luxo_dourado: {
    style: {
      dots: {
        type: 'classy-rounded',
        color: '#6b4f1d',
        gradient: createLinearGradient(['#6b4f1d', '#d4a017'], Math.PI / 6),
      },
      corners_square: {
        type: 'extra-rounded',
        color: '#8c6a23',
        gradient: createLinearGradient(['#8c6a23', '#d4a017'], Math.PI / 5),
      },
      corners_dot: {
        type: 'rounded',
        color: '#8c6a23',
      },
      background: {
        color: '#fffaf0',
      },
    },
  },
  oceano: {
    style: {
      dots: {
        type: 'rounded',
        color: '#0f172a',
        gradient: createLinearGradient(['#0f172a', '#0f766e'], Math.PI / 8),
      },
      corners_square: {
        type: 'rounded',
        color: '#155e75',
        gradient: createRadialGradient(['#155e75', '#0284c7']),
      },
      corners_dot: {
        type: 'dot',
        color: '#0284c7',
      },
      background: {
        color: '#f8fafc',
      },
    },
  },
  romantico: {
    style: {
      dots: {
        type: 'extra-rounded',
        color: '#9f1239',
        gradient: createLinearGradient(['#9f1239', '#fb7185'], Math.PI / 7),
      },
      corners_square: {
        type: 'rounded',
        color: '#be185d',
        gradient: createLinearGradient(['#be185d', '#fb7185'], Math.PI / 8),
      },
      corners_dot: {
        type: 'rounded',
        color: '#be185d',
      },
      background: {
        color: '#fff1f2',
      },
    },
  },
  festa: {
    style: {
      dots: {
        type: 'dots',
        color: '#4c1d95',
        gradient: createLinearGradient(['#4c1d95', '#c2410c'], Math.PI / 4),
      },
      corners_square: {
        type: 'classy-rounded',
        color: '#6d28d9',
        gradient: createLinearGradient(['#6d28d9', '#ea580c'], Math.PI / 5),
      },
      corners_dot: {
        type: 'dots',
        color: '#db2777',
      },
      background: {
        color: '#fff7ed',
      },
    },
  },
  grafite: {
    style: {
      dots: {
        type: 'classy',
        color: '#111827',
        gradient: createLinearGradient(['#111827', '#6b7280'], Math.PI / 7),
      },
      corners_square: {
        type: 'square',
        color: '#1f2937',
        gradient: createLinearGradient(['#1f2937', '#9ca3af'], Math.PI / 9),
      },
      corners_dot: {
        type: 'rounded',
        color: '#4b5563',
      },
      background: {
        color: '#f8fafc',
      },
    },
  },
  terracota: {
    style: {
      dots: {
        type: 'rounded',
        color: '#9a3412',
        gradient: createLinearGradient(['#9a3412', '#f97316'], Math.PI / 6),
      },
      corners_square: {
        type: 'extra-rounded',
        color: '#c2410c',
        gradient: createLinearGradient(['#c2410c', '#fdba74'], Math.PI / 8),
      },
      corners_dot: {
        type: 'dot',
        color: '#b45309',
      },
      background: {
        color: '#fff7ed',
      },
    },
  },
  floresta: {
    style: {
      dots: {
        type: 'classy-rounded',
        color: '#166534',
        gradient: createLinearGradient(['#166534', '#65a30d'], Math.PI / 5),
      },
      corners_square: {
        type: 'rounded',
        color: '#166534',
        gradient: createRadialGradient(['#166534', '#84cc16']),
      },
      corners_dot: {
        type: 'rounded',
        color: '#3f6212',
      },
      background: {
        color: '#f7fee7',
      },
    },
  },
  lavanda: {
    style: {
      dots: {
        type: 'extra-rounded',
        color: '#6d28d9',
        gradient: createLinearGradient(['#6d28d9', '#c084fc'], Math.PI / 6),
      },
      corners_square: {
        type: 'classy-rounded',
        color: '#7c3aed',
        gradient: createLinearGradient(['#7c3aed', '#d8b4fe'], Math.PI / 8),
      },
      corners_dot: {
        type: 'dot',
        color: '#8b5cf6',
      },
      background: {
        color: '#faf5ff',
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

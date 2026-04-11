import type { ErrorCorrectionLevel } from 'qr-code-styling';

import type { EventPublicLinkQrConfig } from './qrTypes';

const MIN_MARGIN_MODULES = 4;
const DEFAULT_IMAGE_SIZE = 0.22;
const MAX_IMAGE_SIZE = 0.5;

export function clampMarginModules(value: number | null | undefined): number {
  if (!Number.isFinite(value)) {
    return MIN_MARGIN_MODULES;
  }

  return Math.max(MIN_MARGIN_MODULES, Math.round(Number(value)));
}

export function clampImageSize(value: number | null | undefined): number {
  if (!Number.isFinite(value)) {
    return DEFAULT_IMAGE_SIZE;
  }

  return Math.min(MAX_IMAGE_SIZE, Math.max(0, Number(value)));
}

export function resolveErrorCorrectionLevel(
  level: ErrorCorrectionLevel | null | undefined,
  options?: { hasLogo?: boolean },
): ErrorCorrectionLevel {
  if (options?.hasLogo) {
    return 'H';
  }

  return level ?? 'Q';
}

function hasRenderableLogo(config: EventPublicLinkQrConfig): boolean {
  return config.logo.mode !== 'none';
}

export function applyQrGuardrails(config: EventPublicLinkQrConfig): EventPublicLinkQrConfig {
  const hasLogo = hasRenderableLogo(config);
  const transparentBackground = Boolean(config.style.background.transparent || config.render.background_mode === 'transparent');
  const safeExportExtension = transparentBackground && config.export_defaults.extension === 'jpeg'
    ? 'png'
    : config.export_defaults.extension;

  return {
    ...config,
    render: {
      ...config.render,
      margin_modules: clampMarginModules(config.render.margin_modules),
      background_mode: transparentBackground ? 'transparent' : 'solid',
    },
    style: {
      ...config.style,
      background: {
        ...config.style.background,
        transparent: transparentBackground,
      },
    },
    logo: {
      ...config.logo,
      image_size: clampImageSize(config.logo.image_size),
    },
    advanced: {
      ...config.advanced,
      error_correction_level: resolveErrorCorrectionLevel(config.advanced.error_correction_level, { hasLogo }),
    },
    export_defaults: {
      ...config.export_defaults,
      extension: safeExportExtension,
    },
  };
}

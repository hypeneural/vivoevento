import type { ApiEventEffectiveBranding } from '@/lib/api-types';

import { getDefaultUsagePresetForLinkKey } from './qrPresets';
import { buildQrConfigDefaults } from './qrDefaults';
import type { EventPublicLinkQrConfig, QrLinkKey } from './qrTypes';
import { buildQrBrandingSeed, buildQrCascadeDefaults } from './qrPresetCascade';

export type QrFieldOrigin = 'event' | 'preset' | 'custom';

export interface QrCascadeExplanation {
  usagePreset: QrFieldOrigin;
  skinPreset: QrFieldOrigin;
  primaryColor: QrFieldOrigin;
  backgroundColor: QrFieldOrigin;
  logo: QrFieldOrigin;
  exportDefaults: QrFieldOrigin;
}

type BrandingSeedInput = Pick<ApiEventEffectiveBranding, 'logo_path' | 'logo_url' | 'primary_color' | 'secondary_color'> | null | undefined;

function isEqualValue(a: unknown, b: unknown): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}

function resolveOrigin(current: unknown, presetValue: unknown, brandedValue: unknown): QrFieldOrigin {
  if (!isEqualValue(brandedValue, presetValue) && isEqualValue(current, brandedValue)) {
    return 'event';
  }

  if (isEqualValue(current, presetValue)) {
    return 'preset';
  }

  return 'custom';
}

export function resolveQrCascadeExplanation(params: {
  config: EventPublicLinkQrConfig;
  linkKey: QrLinkKey;
  branding?: BrandingSeedInput;
}): QrCascadeExplanation {
  const { config, linkKey, branding } = params;
  const usageDefault = getDefaultUsagePresetForLinkKey(linkKey);
  const defaultSkinPreset = buildQrConfigDefaults({
    linkKey,
    usagePreset: config.usage_preset,
  }).skin_preset;
  const brandingSeed = buildQrBrandingSeed(branding);
  const presetDefaults = buildQrCascadeDefaults({
    linkKey,
    usagePreset: config.usage_preset,
    skinPreset: config.skin_preset,
  });
  const brandedDefaults = buildQrCascadeDefaults({
    linkKey,
    usagePreset: config.usage_preset,
    skinPreset: config.skin_preset,
    branding,
  });

  const usagePresetOrigin: QrFieldOrigin = config.usage_preset === usageDefault ? 'preset' : 'custom';

  const brandingSkinPreset = brandingSeed.skin_preset ?? null;
  let skinPresetOrigin: QrFieldOrigin = 'preset';
  if (brandingSkinPreset && config.skin_preset === brandingSkinPreset) {
    skinPresetOrigin = 'event';
  } else if (config.skin_preset !== defaultSkinPreset) {
    skinPresetOrigin = 'custom';
  }

  return {
    usagePreset: usagePresetOrigin,
    skinPreset: skinPresetOrigin,
    primaryColor: resolveOrigin(
      config.style.dots.color,
      presetDefaults.style.dots.color,
      brandedDefaults.style.dots.color,
    ),
    backgroundColor: resolveOrigin(
      config.style.background.color,
      presetDefaults.style.background.color,
      brandedDefaults.style.background.color,
    ),
    logo: resolveOrigin(
      {
        mode: config.logo.mode,
        asset_path: config.logo.asset_path,
        asset_url: config.logo.asset_url,
      },
      {
        mode: presetDefaults.logo.mode,
        asset_path: presetDefaults.logo.asset_path,
        asset_url: presetDefaults.logo.asset_url,
      },
      {
        mode: brandedDefaults.logo.mode,
        asset_path: brandedDefaults.logo.asset_path,
        asset_url: brandedDefaults.logo.asset_url,
      },
    ),
    exportDefaults: resolveOrigin(
      {
        extension: config.export_defaults.extension,
        size: config.export_defaults.size,
      },
      {
        extension: presetDefaults.export_defaults.extension,
        size: presetDefaults.export_defaults.size,
      },
      {
        extension: brandedDefaults.export_defaults.extension,
        size: brandedDefaults.export_defaults.size,
      },
    ),
  };
}

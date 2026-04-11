import type { ApiEventEffectiveBranding } from '@/lib/api-types';

import { buildQrConfigDefaults, mergeQrConfig } from './qrDefaults';
import { applyQrGuardrails } from './qrGuardrails';
import type { EventPublicLinkQrConfig, QrLinkKey, QrSkinPreset, QrUsagePreset } from './qrTypes';

type BrandingSeedInput = Pick<ApiEventEffectiveBranding, 'logo_url' | 'primary_color' | 'secondary_color'> | null | undefined;

export function buildQrBrandingSeed(branding?: BrandingSeedInput) {
  const hasVisualBranding = Boolean(branding?.logo_url || branding?.primary_color || branding?.secondary_color);

  return {
    skin_preset: hasVisualBranding ? 'premium' : undefined,
    style: {
      dots: {
        color: branding?.primary_color ?? undefined,
      },
      corners_square: {
        color: branding?.primary_color ?? undefined,
      },
      corners_dot: {
        color: branding?.secondary_color ?? branding?.primary_color ?? undefined,
      },
    },
    logo: branding?.logo_url ? {
      mode: 'event_logo',
      asset_url: branding.logo_url,
    } : undefined,
  } as const;
}

export function buildQrCascadeDefaults(params: {
  linkKey: QrLinkKey;
  usagePreset?: QrUsagePreset;
  skinPreset?: QrSkinPreset;
  branding?: BrandingSeedInput;
}): EventPublicLinkQrConfig {
  const brandingSeed = buildQrBrandingSeed(params.branding);
  const effectiveSkinPreset = params.skinPreset ?? brandingSeed.skin_preset ?? undefined;
  const defaults = buildQrConfigDefaults({
    linkKey: params.linkKey,
    usagePreset: params.usagePreset,
    skinPreset: effectiveSkinPreset,
  });
  const { skin_preset: _ignoredSkinPreset, ...brandingOverrides } = brandingSeed;

  return applyQrGuardrails(
    mergeQrConfig(defaults, brandingOverrides),
  );
}

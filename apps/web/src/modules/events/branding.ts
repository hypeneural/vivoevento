import type { MeOrganization } from '@/lib/api-types';

import type { EventEffectiveBranding, EventEffectiveBrandingSource } from './types';

const BRANDING_FIELDS = ['logo', 'cover_image'] as const;

type BrandingAssetField = (typeof BRANDING_FIELDS)[number];

type BrandingPreviewInput = {
  logo_path?: string | null;
  logo_url?: string | null;
  cover_image_path?: string | null;
  cover_image_url?: string | null;
  primary_color?: string | null;
  secondary_color?: string | null;
};

type ResolveEffectiveBrandingPreviewInput = {
  inheritBranding: boolean;
  eventBranding: BrandingPreviewInput;
  organizationBranding?: BrandingPreviewInput | null;
};

function isFilled(value?: string | null) {
  return typeof value === 'string' && value.trim().length > 0;
}

function resolveAssetField(
  field: BrandingAssetField,
  eventBranding: BrandingPreviewInput,
  organizationBranding?: BrandingPreviewInput | null,
) {
  const pathKey = `${field}_path` as const;
  const urlKey = `${field}_url` as const;
  const eventHasValue = isFilled(eventBranding[pathKey]) || isFilled(eventBranding[urlKey]);
  const organizationHasValue = isFilled(organizationBranding?.[pathKey]) || isFilled(organizationBranding?.[urlKey]);

  return {
    path: eventHasValue ? eventBranding[pathKey] ?? null : organizationBranding?.[pathKey] ?? null,
    url: eventHasValue ? eventBranding[urlKey] ?? null : organizationBranding?.[urlKey] ?? null,
    eventHasValue,
    usedOrganizationFallback: !eventHasValue && organizationHasValue,
  };
}

export function getOrganizationBrandingPreview(organization?: MeOrganization | null): BrandingPreviewInput | null {
  if (!organization) {
    return null;
  }

  return {
    logo_path: organization.branding?.logo_path ?? null,
    logo_url: organization.logo_url ?? organization.branding?.logo_url ?? null,
    cover_image_path: organization.branding?.cover_path ?? null,
    cover_image_url: organization.branding?.cover_url ?? null,
    primary_color: organization.branding?.primary_color ?? null,
    secondary_color: organization.branding?.secondary_color ?? null,
  };
}

export function resolveEffectiveBrandingPreview({
  inheritBranding,
  eventBranding,
  organizationBranding,
}: ResolveEffectiveBrandingPreviewInput): EventEffectiveBranding {
  if (!inheritBranding || !organizationBranding) {
    return {
      logo_path: eventBranding.logo_path ?? null,
      logo_url: eventBranding.logo_url ?? null,
      cover_image_path: eventBranding.cover_image_path ?? null,
      cover_image_url: eventBranding.cover_image_url ?? null,
      primary_color: eventBranding.primary_color ?? null,
      secondary_color: eventBranding.secondary_color ?? null,
      source: 'event',
      inherits_from_organization: false,
    };
  }

  const resolvedLogo = resolveAssetField('logo', eventBranding, organizationBranding);
  const resolvedCover = resolveAssetField('cover_image', eventBranding, organizationBranding);
  const primaryColorUsesOrganizationFallback = !isFilled(eventBranding.primary_color) && isFilled(organizationBranding.primary_color);
  const secondaryColorUsesOrganizationFallback = !isFilled(eventBranding.secondary_color) && isFilled(organizationBranding.secondary_color);

  const hasEventValue = BRANDING_FIELDS.some((field) => {
    const resolved = field === 'logo' ? resolvedLogo : resolvedCover;
    return resolved.eventHasValue;
  }) || isFilled(eventBranding.primary_color) || isFilled(eventBranding.secondary_color);

  const usedOrganizationFallback = resolvedLogo.usedOrganizationFallback
    || resolvedCover.usedOrganizationFallback
    || primaryColorUsesOrganizationFallback
    || secondaryColorUsesOrganizationFallback;

  const source: EventEffectiveBrandingSource = hasEventValue && usedOrganizationFallback
    ? 'mixed'
    : hasEventValue
      ? 'event'
      : 'organization';

  return {
    logo_path: resolvedLogo.path,
    logo_url: resolvedLogo.url,
    cover_image_path: resolvedCover.path,
    cover_image_url: resolvedCover.url,
    primary_color: eventBranding.primary_color || organizationBranding.primary_color || null,
    secondary_color: eventBranding.secondary_color || organizationBranding.secondary_color || null,
    source,
    inherits_from_organization: true,
  };
}

export function resolveEffectiveBrandingSourceLabel(source: EventEffectiveBrandingSource) {
  switch (source) {
    case 'organization':
      return 'Organizacao';
    case 'mixed':
      return 'Evento + organizacao';
    default:
      return 'Evento';
  }
}

export function resolveEffectiveBrandingSourceDescription(
  source: EventEffectiveBrandingSource,
  organizationName?: string | null,
) {
  const organizationLabel = organizationName?.trim() || 'a organizacao';

  switch (source) {
    case 'organization':
      return `Este evento aproveita automaticamente a identidade visual da ${organizationLabel}.`;
    case 'mixed':
      return `Este evento combina ajustes proprios com itens herdados da ${organizationLabel}.`;
    default:
      return 'Este evento usa apenas a identidade visual salva nele.';
  }
}

export function resolveEffectiveBrandingDisplayColors(branding?: Pick<EventEffectiveBranding, 'primary_color' | 'secondary_color'> | null) {
  return {
    primary: branding?.primary_color ?? '#f97316',
    secondary: branding?.secondary_color ?? '#1d4ed8',
  };
}

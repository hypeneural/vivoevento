import { describe, expect, it } from 'vitest';

import {
  getOrganizationBrandingPreview,
  resolveEffectiveBrandingPreview,
  resolveEffectiveBrandingSourceDescription,
  resolveEffectiveBrandingSourceLabel,
} from './branding';

describe('event branding preview helpers', () => {
  const organization = {
    id: 1,
    uuid: 'org-1',
    type: 'partner',
    name: 'Studio Aurora',
    slug: 'studio-aurora',
    status: 'active',
    logo_url: 'https://cdn.example.com/organization/logo-light.webp',
    branding: {
      logo_path: 'organizations/branding/logo-light.webp',
      logo_url: 'https://cdn.example.com/organization/logo-light.webp',
      logo_dark_path: null,
      logo_dark_url: null,
      favicon_path: null,
      favicon_url: null,
      watermark_path: null,
      watermark_url: null,
      cover_path: 'organizations/branding/cover.webp',
      cover_url: 'https://cdn.example.com/organization/cover.webp',
      primary_color: '#112233',
      secondary_color: '#445566',
      subdomain: null,
      custom_domain: null,
    },
  } as const;

  it('uses organization branding when inheritance is enabled and the event has no own assets', () => {
    const resolved = resolveEffectiveBrandingPreview({
      inheritBranding: true,
      organizationBranding: getOrganizationBrandingPreview(organization),
      eventBranding: {
        logo_path: null,
        logo_url: null,
        cover_image_path: null,
        cover_image_url: null,
        primary_color: null,
        secondary_color: null,
      },
    });

    expect(resolved).toMatchObject({
      source: 'organization',
      inherits_from_organization: true,
      logo_url: 'https://cdn.example.com/organization/logo-light.webp',
      cover_image_url: 'https://cdn.example.com/organization/cover.webp',
      primary_color: '#112233',
      secondary_color: '#445566',
    });
  });

  it('keeps event overrides and fills missing fields from the organization', () => {
    const resolved = resolveEffectiveBrandingPreview({
      inheritBranding: true,
      organizationBranding: getOrganizationBrandingPreview(organization),
      eventBranding: {
        logo_path: 'events/branding/logo.webp',
        logo_url: 'https://cdn.example.com/events/logo.webp',
        cover_image_path: null,
        cover_image_url: null,
        primary_color: '#ff6600',
        secondary_color: null,
      },
    });

    expect(resolved).toMatchObject({
      source: 'mixed',
      inherits_from_organization: true,
      logo_path: 'events/branding/logo.webp',
      logo_url: 'https://cdn.example.com/events/logo.webp',
      cover_image_url: 'https://cdn.example.com/organization/cover.webp',
      primary_color: '#ff6600',
      secondary_color: '#445566',
    });
  });

  it('ignores organization branding when inheritance is disabled', () => {
    const resolved = resolveEffectiveBrandingPreview({
      inheritBranding: false,
      organizationBranding: getOrganizationBrandingPreview(organization),
      eventBranding: {
        logo_path: null,
        logo_url: null,
        cover_image_path: null,
        cover_image_url: null,
        primary_color: null,
        secondary_color: null,
      },
    });

    expect(resolved).toMatchObject({
      source: 'event',
      inherits_from_organization: false,
      logo_url: null,
      cover_image_url: null,
      primary_color: null,
      secondary_color: null,
    });
  });

  it('returns user-facing labels and descriptions for the current source', () => {
    expect(resolveEffectiveBrandingSourceLabel('organization')).toBe('Organizacao');
    expect(resolveEffectiveBrandingSourceLabel('mixed')).toBe('Evento + organizacao');
    expect(resolveEffectiveBrandingSourceDescription('mixed', 'Studio Aurora')).toContain('Studio Aurora');
  });
});

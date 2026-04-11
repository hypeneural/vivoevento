import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { queryClient } from '@/lib/query-client';

import { __resetEventPublicLinkQrWarmState, warmEventPublicLinkQrEditor } from './preload';
import * as loaderModule from './loader';

function makeLink(overrides: Partial<ApiEventPublicLink> = {}): ApiEventPublicLink {
  return {
    key: 'gallery',
    label: 'Galeria',
    enabled: true,
    identifier_type: 'slug',
    identifier: 'casamento-ana-pedro',
    url: 'https://example.com/gallery/casamento-ana-pedro',
    api_url: null,
    qr_value: 'https://example.com/gallery/casamento-ana-pedro',
    ...overrides,
  };
}

function makeBranding(overrides: Partial<ApiEventEffectiveBranding> = {}): ApiEventEffectiveBranding {
  return {
    logo_path: null,
    logo_url: null,
    cover_image_path: null,
    cover_image_url: null,
    primary_color: '#112233',
    secondary_color: '#445566',
    source: 'organization',
    inherits_from_organization: true,
    ...overrides,
  };
}

describe('event public link qr preload', () => {
  beforeEach(() => {
    __resetEventPublicLinkQrWarmState();
    vi.clearAllMocks();
  });

  it('warms chunk and query only once for the same event/link pair', async () => {
    const loadSpy = vi.spyOn(loaderModule, 'loadEventPublicLinkQrEditorModule')
      .mockResolvedValue({ default: () => null });
    const prefetchSpy = vi.spyOn(queryClient, 'prefetchQuery')
      .mockResolvedValue(undefined as never);

    await warmEventPublicLinkQrEditor({
      eventId: '42',
      link: makeLink(),
      effectiveBranding: makeBranding(),
    });

    await warmEventPublicLinkQrEditor({
      eventId: '42',
      link: makeLink(),
      effectiveBranding: makeBranding(),
    });

    expect(loadSpy).toHaveBeenCalledTimes(1);
    expect(prefetchSpy).toHaveBeenCalledTimes(1);
  });

  it('skips warmup when the link does not expose qr_value', async () => {
    const loadSpy = vi.spyOn(loaderModule, 'loadEventPublicLinkQrEditorModule')
      .mockResolvedValue({ default: () => null });
    const prefetchSpy = vi.spyOn(queryClient, 'prefetchQuery')
      .mockResolvedValue(undefined as never);

    await warmEventPublicLinkQrEditor({
      eventId: '42',
      link: makeLink({ qr_value: null }),
      effectiveBranding: makeBranding(),
    });

    expect(loadSpy).not.toHaveBeenCalled();
    expect(prefetchSpy).not.toHaveBeenCalled();
  });
});

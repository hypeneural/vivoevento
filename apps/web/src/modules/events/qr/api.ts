import { useQuery } from '@tanstack/react-query';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { queryClient } from '@/lib/query-client';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { normalizeEventPublicLinkQrConfig } from '@/modules/qr-code/support/qrSchemaNormalizer';
import type { EventPublicLinkQrConfig, QrLinkKey } from '@/modules/qr-code/support/qrTypes';

export interface EventPublicLinkQrEditorState {
  eventId: string;
  link: ApiEventPublicLink;
  effectiveBranding: ApiEventEffectiveBranding | null;
  config: EventPublicLinkQrConfig;
}

export interface EventPublicLinkQrEditorParams {
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
}

function buildBrandingSeed(branding?: ApiEventEffectiveBranding | null) {
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

export function getEventPublicLinkQrEditorQueryKey(eventId: string | number, linkKey: QrLinkKey) {
  return ['event-public-link-qr-editor', String(eventId), linkKey] as const;
}

export function buildEventPublicLinkQrEditorState(params: EventPublicLinkQrEditorParams): EventPublicLinkQrEditorState {
  const eventId = String(params.eventId);
  const config = normalizeEventPublicLinkQrConfig(buildBrandingSeed(params.effectiveBranding), {
    linkKey: params.link.key,
  });

  return {
    eventId,
    link: params.link,
    effectiveBranding: params.effectiveBranding ?? null,
    config,
  };
}

export function buildEventPublicLinkQrPreviewOptions(params: EventPublicLinkQrEditorState) {
  return buildQrCodeStylingOptions({
    config: params.config,
    data: params.link.qr_value ?? params.link.url ?? '',
  });
}

export function prefetchEventPublicLinkQrEditorState(params: EventPublicLinkQrEditorParams) {
  return queryClient.prefetchQuery({
    queryKey: getEventPublicLinkQrEditorQueryKey(params.eventId, params.link.key),
    queryFn: async () => buildEventPublicLinkQrEditorState(params),
    staleTime: 60_000,
  });
}

export function useEventPublicLinkQrEditorState(
  params: EventPublicLinkQrEditorParams & { enabled?: boolean },
) {
  return useQuery({
    queryKey: getEventPublicLinkQrEditorQueryKey(params.eventId, params.link.key),
    enabled: params.enabled ?? true,
    staleTime: 60_000,
    refetchOnWindowFocus: false,
    queryFn: async () => buildEventPublicLinkQrEditorState(params),
    placeholderData: () => buildEventPublicLinkQrEditorState(params),
  });
}

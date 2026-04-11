import { useQuery } from '@tanstack/react-query';

import api from '@/lib/api';
import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { queryClient } from '@/lib/query-client';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import { buildQrBrandingSeed } from '@/modules/qr-code/support/qrPresetCascade';
import { normalizeEventPublicLinkQrConfig } from '@/modules/qr-code/support/qrSchemaNormalizer';
import type { EventPublicLinkQrConfig, QrLinkKey } from '@/modules/qr-code/support/qrTypes';

export interface EventPublicLinkQrEditorState {
  eventId: string;
  linkKey: QrLinkKey;
  link: ApiEventPublicLink;
  effectiveBranding: ApiEventEffectiveBranding | null;
  config: EventPublicLinkQrConfig;
  configSource: 'default' | 'saved';
  hasSavedConfig: boolean;
  updatedAt: string | null;
  assets: {
    svgPath: string | null;
    pngPath: string | null;
  };
}

interface EventPublicLinkQrEditorStateResponse {
  event_id: number;
  link_key: QrLinkKey;
  link: ApiEventPublicLink;
  effective_branding: ApiEventEffectiveBranding | null;
  config: EventPublicLinkQrConfig;
  config_source: 'default' | 'saved';
  has_saved_config: boolean;
  updated_at: string | null;
  assets: {
    svg_path: string | null;
    png_path: string | null;
  };
}

export interface EventPublicLinkQrEditorParams {
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
}

export interface EventPublicLinkQrLogoUploadResponse {
  kind: 'logo';
  path: string;
  url: string;
}

function mapEventPublicLinkQrEditorState(response: EventPublicLinkQrEditorStateResponse): EventPublicLinkQrEditorState {
  return {
    eventId: String(response.event_id),
    linkKey: response.link_key,
    link: response.link,
    effectiveBranding: response.effective_branding,
    config: response.config,
    configSource: response.config_source,
    hasSavedConfig: response.has_saved_config,
    updatedAt: response.updated_at,
    assets: {
      svgPath: response.assets?.svg_path ?? null,
      pngPath: response.assets?.png_path ?? null,
    },
  };
}

export function getEventPublicLinkQrEditorQueryKey(eventId: string | number, linkKey: QrLinkKey) {
  return ['event-public-link-qr-editor', String(eventId), linkKey] as const;
}

export function getEventPublicLinkQrListQueryKey(eventId: string | number) {
  return ['event-public-link-qr-list', String(eventId)] as const;
}

export function buildPlaceholderEventPublicLinkQrEditorState(
  params: EventPublicLinkQrEditorParams,
): EventPublicLinkQrEditorState {
  const eventId = String(params.eventId);
  const config = normalizeEventPublicLinkQrConfig(buildQrBrandingSeed(params.effectiveBranding), {
    linkKey: params.link.key,
  });

  return {
    eventId,
    linkKey: params.link.key,
    link: params.link,
    effectiveBranding: params.effectiveBranding ?? null,
    config,
    configSource: 'default',
    hasSavedConfig: false,
    updatedAt: null,
    assets: {
      svgPath: null,
      pngPath: null,
    },
  };
}

export async function getEventPublicLinkQrEditorState(
  eventId: string | number,
  linkKey: QrLinkKey,
): Promise<EventPublicLinkQrEditorState> {
  const response = await api.get<EventPublicLinkQrEditorStateResponse>(`/events/${eventId}/qr-codes/${linkKey}`);

  return mapEventPublicLinkQrEditorState(response);
}

export async function listEventPublicLinkQrEditorStates(
  eventId: string | number,
): Promise<EventPublicLinkQrEditorState[]> {
  const response = await api.get<EventPublicLinkQrEditorStateResponse[]>(`/events/${eventId}/qr-codes`);

  return response.map(mapEventPublicLinkQrEditorState);
}

export async function updateEventPublicLinkQrEditorState(
  eventId: string | number,
  linkKey: QrLinkKey,
  config: EventPublicLinkQrConfig,
): Promise<EventPublicLinkQrEditorState> {
  const response = await api.put<EventPublicLinkQrEditorStateResponse>(`/events/${eventId}/qr-codes/${linkKey}`, {
    body: {
      config,
    },
  });

  return mapEventPublicLinkQrEditorState(response);
}

export async function resetEventPublicLinkQrEditorState(
  eventId: string | number,
  linkKey: QrLinkKey,
): Promise<EventPublicLinkQrEditorState> {
  const response = await api.post<EventPublicLinkQrEditorStateResponse>(`/events/${eventId}/qr-codes/${linkKey}/reset`, {
    body: {},
  });

  return mapEventPublicLinkQrEditorState(response);
}

export async function uploadEventPublicLinkQrLogoAsset(
  file: File,
  previousPath?: string | null,
): Promise<EventPublicLinkQrLogoUploadResponse> {
  const formData = new FormData();
  formData.append('kind', 'logo');
  formData.append('file', file);

  if (previousPath) {
    formData.append('previous_path', previousPath);
  }

  return api.upload<EventPublicLinkQrLogoUploadResponse>('/events/branding-assets', formData);
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
    queryFn: async () => getEventPublicLinkQrEditorState(params.eventId, params.link.key),
    staleTime: 300_000,
  });
}

export function useEventPublicLinkQrEditorState(
  params: EventPublicLinkQrEditorParams & { enabled?: boolean },
) {
  return useQuery({
    queryKey: getEventPublicLinkQrEditorQueryKey(params.eventId, params.link.key),
    enabled: params.enabled ?? true,
    staleTime: 300_000,
    refetchOnWindowFocus: false,
    queryFn: async () => getEventPublicLinkQrEditorState(params.eventId, params.link.key),
    placeholderData: () => buildPlaceholderEventPublicLinkQrEditorState(params),
  });
}

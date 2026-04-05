import api from '@/lib/api';
import type {
  ApiEvent,
  ApiEventCommercialStatus,
  ApiEventContentModerationSettings,
  ApiEventDetail,
  ApiEventFaceSearchSettings,
  ApiEventMediaIntelligenceSettings,
  ApiEventMediaItem,
  ApiEventPublicLinksPayload,
  PaginatedResponse,
} from '@/lib/api-types';

export interface EventListFilters {
  search?: string;
  status?: string;
  event_type?: string;
  module?: string;
  per_page?: number;
}

export interface UpdateEventPublicIdentifiersPayload {
  slug?: string;
  upload_slug?: string;
}

export interface RegenerateEventPublicIdentifiersPayload {
  fields: Array<'slug' | 'upload_slug' | 'wall_code'>;
}

export interface UpdateEventContentModerationSettingsPayload {
  enabled: boolean;
  provider_key: 'openai' | 'noop';
  mode?: 'enforced' | 'observe_only';
  threshold_version?: string | null;
  fallback_mode: 'review' | 'block';
  hard_block_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  review_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
}

export interface UpdateEventMediaIntelligenceSettingsPayload {
  enabled: boolean;
  provider_key: 'vllm' | 'noop';
  model_key: string;
  mode: 'enrich_only' | 'gate';
  prompt_version?: string | null;
  approval_prompt?: string | null;
  caption_style_prompt?: string | null;
  response_schema_version: string;
  timeout_ms: number;
  fallback_mode: 'review' | 'skip';
  require_json_output: boolean;
}

export interface UpdateEventFaceSearchSettingsPayload {
  enabled: boolean;
  provider_key: 'noop';
  embedding_model_key: string;
  vector_store_key: 'pgvector';
  min_face_size_px: number;
  min_quality_score: number;
  search_threshold: number;
  top_k: number;
  allow_public_selfie_search: boolean;
  selfie_retention_hours: number;
}

export function listEvents(filters: EventListFilters) {
  return api.get<PaginatedResponse<ApiEvent>>('/events', {
    params: {
      search: filters.search || undefined,
      status: filters.status && filters.status !== 'all' ? filters.status : undefined,
      event_type: filters.event_type && filters.event_type !== 'all' ? filters.event_type : undefined,
      module: filters.module || undefined,
      per_page: filters.per_page ?? 24,
    },
  });
}

export function getEventDetail(eventId: string | number) {
  return api.get<ApiEventDetail>(`/events/${eventId}`);
}

export function getEventCommercialStatus(eventId: string | number) {
  return api.get<ApiEventCommercialStatus>(`/events/${eventId}/commercial-status`);
}

export function listEventMedia(eventId: string | number, perPage = 24) {
  return api.get<PaginatedResponse<ApiEventMediaItem>>(`/events/${eventId}/media`, {
    params: {
      per_page: perPage,
    },
  });
}

export function getEventContentModerationSettings(eventId: string | number) {
  return api.get<ApiEventContentModerationSettings>(`/events/${eventId}/content-moderation/settings`);
}

export function updateEventContentModerationSettings(
  eventId: string | number,
  payload: UpdateEventContentModerationSettingsPayload,
) {
  return api.patch<ApiEventContentModerationSettings>(`/events/${eventId}/content-moderation/settings`, {
    body: payload,
  });
}

export function getEventMediaIntelligenceSettings(eventId: string | number) {
  return api.get<ApiEventMediaIntelligenceSettings>(`/events/${eventId}/media-intelligence/settings`);
}

export function updateEventMediaIntelligenceSettings(
  eventId: string | number,
  payload: UpdateEventMediaIntelligenceSettingsPayload,
) {
  return api.patch<ApiEventMediaIntelligenceSettings>(`/events/${eventId}/media-intelligence/settings`, {
    body: payload,
  });
}

export function getEventFaceSearchSettings(eventId: string | number) {
  return api.get<ApiEventFaceSearchSettings>(`/events/${eventId}/face-search/settings`);
}

export function updateEventFaceSearchSettings(
  eventId: string | number,
  payload: UpdateEventFaceSearchSettingsPayload,
) {
  return api.patch<ApiEventFaceSearchSettings>(`/events/${eventId}/face-search/settings`, {
    body: payload,
  });
}

export function getEventShareLinks(eventId: string | number) {
  return api.get<ApiEventPublicLinksPayload>(`/events/${eventId}/share-links`);
}

export function updateEventPublicIdentifiers(
  eventId: string | number,
  payload: UpdateEventPublicIdentifiersPayload,
) {
  return api.patch<ApiEventPublicLinksPayload>(`/events/${eventId}/public-links`, {
    body: payload,
  });
}

export function regenerateEventPublicIdentifiers(
  eventId: string | number,
  payload: RegenerateEventPublicIdentifiersPayload,
) {
  return api.post<ApiEventPublicLinksPayload>(`/events/${eventId}/public-links/regenerate`, {
    body: payload,
  });
}

export function getPublicGallery(slug: string) {
  return api.get<PaginatedResponse<ApiEventMediaItem>>(`/public/events/${slug}/gallery`);
}

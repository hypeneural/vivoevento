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
import type { EventIntakeBlacklist } from './intake';

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

export interface UpsertEventIntakeBlacklistEntryPayload {
  id?: number | null;
  identity_type: 'phone' | 'lid' | 'external_id';
  identity_value: string;
  normalized_phone?: string | null;
  reason?: string | null;
  expires_at?: string | null;
  is_active?: boolean;
}

export interface EventIntakeBlacklistMutationResponse {
  entry_id: number;
  intake_blacklist: EventIntakeBlacklist;
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
  provider_key: 'vllm' | 'openrouter' | 'noop';
  model_key: string;
  mode: 'enrich_only' | 'gate';
  prompt_version?: string | null;
  approval_prompt?: string | null;
  caption_style_prompt?: string | null;
  response_schema_version: string;
  timeout_ms: number;
  fallback_mode: 'review' | 'skip';
  require_json_output: boolean;
  reply_text_mode: 'disabled' | 'ai' | 'fixed_random';
  reply_prompt_override?: string | null;
  reply_fixed_templates?: string[];
  reply_prompt_preset_id?: number | null;
}

export interface UpdateEventFaceSearchSettingsPayload {
  enabled: boolean;
  provider_key: 'noop' | 'compreface';
  embedding_model_key: string;
  vector_store_key: 'pgvector';
  search_strategy: 'exact' | 'ann';
  min_face_size_px: number;
  min_quality_score: number;
  search_threshold: number;
  top_k: number;
  allow_public_selfie_search: boolean;
  selfie_retention_hours: number;
  recognition_enabled: boolean;
  search_backend_key: 'local_pgvector' | 'aws_rekognition' | 'luxand_managed';
  fallback_backend_key: 'local_pgvector' | 'aws_rekognition' | 'luxand_managed' | null;
  routing_policy: 'local_only' | 'aws_primary_local_fallback' | 'aws_primary_local_shadow' | 'local_primary_aws_on_error';
  shadow_mode_percentage: number;
  aws_region: string;
  aws_search_mode: 'faces' | 'users';
  aws_index_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE';
  aws_search_faces_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE';
  aws_search_users_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE';
  aws_search_face_match_threshold: number;
  aws_search_user_match_threshold: number;
  aws_associate_user_match_threshold: number;
  aws_max_faces_per_image: number;
  aws_index_profile_key: string;
  aws_detection_attributes_json: string[];
  delete_remote_vectors_on_event_close: boolean;
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

export function upsertEventIntakeBlacklistEntry(
  eventId: string | number,
  payload: UpsertEventIntakeBlacklistEntryPayload,
) {
  return api.post<EventIntakeBlacklistMutationResponse>(`/events/${eventId}/intake-blacklist/entries`, {
    body: payload,
  });
}

export function deleteEventIntakeBlacklistEntry(
  eventId: string | number,
  entryId: string | number,
) {
  return api.delete<EventIntakeBlacklistMutationResponse>(`/events/${eventId}/intake-blacklist/entries/${entryId}`);
}

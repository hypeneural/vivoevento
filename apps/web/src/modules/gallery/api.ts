import { api } from '@/lib/api';
import type {
  GalleryAiProposalsResponse,
  GalleryBuilderMutationResult,
  GalleryBuilderPreviewLinkResponse,
  GalleryBuilderPreset,
  GalleryBuilderRevision,
  GalleryBuilderSettings,
  GalleryBuilderShowResponse,
} from './gallery-builder';

export type GalleryBuilderSettingsUpdatePayload = Pick<
  GalleryBuilderSettings,
  | 'is_enabled'
  | 'event_type_family'
  | 'style_skin'
  | 'behavior_profile'
  | 'theme_key'
  | 'layout_key'
  | 'theme_tokens'
  | 'page_schema'
  | 'media_behavior'
>;

export function getEventGallerySettings(eventId: string | number) {
  return api.get<GalleryBuilderShowResponse>(`/events/${eventId}/gallery/settings`);
}

export function updateEventGallerySettings(
  eventId: string | number,
  payload: GalleryBuilderSettingsUpdatePayload,
) {
  return api.patch<{ settings: GalleryBuilderSettings }>(`/events/${eventId}/gallery/settings`, {
    body: payload,
  });
}

export function autosaveEventGalleryDraft(eventId: string | number) {
  return api.post<GalleryBuilderMutationResult>(`/events/${eventId}/gallery/autosave`, {
    body: {},
  });
}

export function publishEventGalleryDraft(eventId: string | number) {
  return api.post<GalleryBuilderMutationResult>(`/events/${eventId}/gallery/publish`, {
    body: {},
  });
}

export function listEventGalleryRevisions(eventId: string | number) {
  return api.get<GalleryBuilderRevision[]>(`/events/${eventId}/gallery/revisions`);
}

export function restoreEventGalleryRevision(eventId: string | number, revisionId: string | number) {
  return api.post<GalleryBuilderMutationResult>(`/events/${eventId}/gallery/revisions/${revisionId}/restore`, {
    body: {},
  });
}

export function createEventGalleryPreviewLink(eventId: string | number) {
  return api.post<GalleryBuilderPreviewLinkResponse>(`/events/${eventId}/gallery/preview-link`, {
    body: {},
  });
}

export function listGalleryPresets() {
  return api.get<GalleryBuilderPreset[]>('/gallery/presets');
}

export function runEventGalleryAiProposals(
  eventId: string | number,
  payload: {
    prompt_text: string;
    persona_key?: string | null;
    target_layer?: 'mixed' | 'theme_tokens' | 'page_schema' | 'media_behavior';
    base_preset_key?: string | null;
  },
) {
  return api.post<GalleryAiProposalsResponse>(`/events/${eventId}/gallery/ai/proposals`, {
    body: payload,
  });
}

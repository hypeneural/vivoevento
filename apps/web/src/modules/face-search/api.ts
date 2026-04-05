import api from '@/lib/api';
import type { ApiFaceSearchResponse, PublicFaceSearchBootstrap } from '@/lib/api-types';

export function getPublicFaceSearchBootstrap(slug: string) {
  return api.get<PublicFaceSearchBootstrap>(`/public/events/${slug}/face-search`);
}

export function searchEventFaces(eventId: number | string, file: File, includePending = true) {
  const formData = new FormData();
  formData.append('selfie', file);
  formData.append('include_pending', includePending ? '1' : '0');

  return api.upload<ApiFaceSearchResponse>(`/events/${eventId}/face-search/search`, formData);
}

export function searchPublicEventFaces(
  slug: string,
  file: File,
  consentVersion: string,
  selfieStorageStrategy: 'memory_only' | 'ephemeral_object' = 'memory_only',
) {
  const formData = new FormData();
  formData.append('selfie', file);
  formData.append('consent_accepted', '1');
  formData.append('consent_version', consentVersion);
  formData.append('selfie_storage_strategy', selfieStorageStrategy);

  return api.upload<ApiFaceSearchResponse>(`/public/events/${slug}/face-search/search`, formData);
}

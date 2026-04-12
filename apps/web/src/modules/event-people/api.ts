import { api } from '@/lib/api';

import type {
  ConfirmReviewItemPayload,
  ConfirmReviewItemResponse,
  EventMediaFacePeople,
  EventPeopleCreatePayload,
  EventPeopleGraphResponse,
  EventPeoplePresetsResponse,
  EventPeopleOperationalStatus,
  EventPeopleRelationPayload,
  EventPersonGroup,
  EventPersonGroupMemberPayload,
  EventPersonGroupMembership,
  EventPersonGroupPayload,
  EventPeopleUpdatePayload,
  EventPeopleListFilters,
  EventPeopleReviewQueueFilters,
  EventPersonRelation,
  IgnoreReviewItemResponse,
  MergeReviewItemPayload,
  MergeReviewItemResponse,
  PaginatedApiResponse,
  SplitReviewItemResponse,
  EventPerson,
  EventPersonReviewQueueItem,
  EventPersonReferencePhotoCandidate,
  EventPersonReferencePhotoPurpose,
} from './types';

export const eventPeopleApi = {
  async listPeople(eventId: number | string, filters: EventPeopleListFilters = {}) {
    return api.getRaw<PaginatedApiResponse<EventPerson>>(`/events/${eventId}/people`, {
      params: filters,
    });
  },

  async getPerson(eventId: number | string, personId: number | string) {
    return api.get<EventPerson>(`/events/${eventId}/people/${personId}`);
  },

  async getGraph(eventId: number | string) {
    return api.get<EventPeopleGraphResponse>(`/events/${eventId}/people/graph`);
  },

  async createPerson(eventId: number | string, payload: EventPeopleCreatePayload) {
    return api.post<EventPerson>(`/events/${eventId}/people`, {
      body: payload,
    });
  },

  async updatePerson(eventId: number | string, personId: number | string, payload: EventPeopleUpdatePayload) {
    return api.patch<EventPerson>(`/events/${eventId}/people/${personId}`, {
      body: payload,
    });
  },

  async getPresets(eventId: number | string) {
    return api.get<EventPeoplePresetsResponse>(`/events/${eventId}/people/presets`);
  },

  async listGroups(eventId: number | string, filters: Record<string, unknown> = {}) {
    return api.get<EventPersonGroup[]>(`/events/${eventId}/people/groups`, {
      params: filters,
    });
  },

  async createGroup(eventId: number | string, payload: EventPersonGroupPayload) {
    return api.post<EventPersonGroup>(`/events/${eventId}/people/groups`, {
      body: payload,
    });
  },

  async updateGroup(eventId: number | string, groupId: number | string, payload: Partial<EventPersonGroupPayload>) {
    return api.patch<EventPersonGroup>(`/events/${eventId}/people/groups/${groupId}`, {
      body: payload,
    });
  },

  async deleteGroup(eventId: number | string, groupId: number | string) {
    return api.delete<void>(`/events/${eventId}/people/groups/${groupId}`);
  },

  async applyPresetGroups(eventId: number | string) {
    return api.post<EventPersonGroup[]>(`/events/${eventId}/people/groups/apply-preset`, {
      body: {},
    });
  },

  async addGroupMember(eventId: number | string, groupId: number | string, payload: EventPersonGroupMemberPayload) {
    return api.post<EventPersonGroupMembership>(`/events/${eventId}/people/groups/${groupId}/members`, {
      body: payload,
    });
  },

  async removeGroupMember(eventId: number | string, groupId: number | string, membershipId: number | string) {
    return api.delete<void>(`/events/${eventId}/people/groups/${groupId}/members/${membershipId}`);
  },

  async getOperationalStatus(eventId: number | string) {
    return api.get<EventPeopleOperationalStatus>(`/events/${eventId}/people/operational-status`);
  },

  async listReviewQueue(eventId: number | string, filters: EventPeopleReviewQueueFilters = {}) {
    return api.getRaw<PaginatedApiResponse<EventPersonReviewQueueItem>>(`/events/${eventId}/people/review-queue`, {
      params: filters,
    });
  },

  async listMediaFaces(eventId: number | string, mediaId: number | string) {
    return api.get<EventMediaFacePeople[]>(`/events/${eventId}/media/${mediaId}/people`);
  },

  async listReferencePhotoCandidates(eventId: number | string, personId: number | string, limit = 24) {
    return api.get<EventPersonReferencePhotoCandidate[]>(
      `/events/${eventId}/people/${personId}/reference-photo-candidates`,
      {
        params: { limit },
      },
    );
  },

  async addGalleryReferencePhoto(
    eventId: number | string,
    personId: number | string,
    payload: { event_media_face_id: number; purpose?: EventPersonReferencePhotoPurpose },
  ) {
    return api.post<EventPerson>(`/events/${eventId}/people/${personId}/reference-photos/gallery-face`, {
      body: payload,
    });
  },

  async uploadReferencePhoto(
    eventId: number | string,
    personId: number | string,
    payload: { file: File; purpose?: EventPersonReferencePhotoPurpose },
  ) {
    const formData = new FormData();
    formData.append('file', payload.file);
    if (payload.purpose) formData.append('purpose', payload.purpose);

    return api.upload<EventPerson>(`/events/${eventId}/people/${personId}/reference-photos/upload`, formData);
  },

  async setPrimaryReferencePhoto(eventId: number | string, personId: number | string, referencePhotoId: number | string) {
    return api.post<EventPerson>(`/events/${eventId}/people/${personId}/reference-photos/${referencePhotoId}/primary`, {
      body: {},
    });
  },

  async confirmReviewItem(
    eventId: number | string,
    reviewItemId: number | string,
    payload: ConfirmReviewItemPayload,
  ) {
    return api.post<ConfirmReviewItemResponse>(`/events/${eventId}/people/review-queue/${reviewItemId}/confirm`, {
      body: payload,
    });
  },

  async ignoreReviewItem(eventId: number | string, reviewItemId: number | string) {
    return api.post<IgnoreReviewItemResponse>(`/events/${eventId}/people/review-queue/${reviewItemId}/ignore`);
  },

  async rejectReviewItem(eventId: number | string, reviewItemId: number | string) {
    return api.post<IgnoreReviewItemResponse>(`/events/${eventId}/people/review-queue/${reviewItemId}/reject`);
  },

  async splitReviewItem(
    eventId: number | string,
    reviewItemId: number | string,
    payload: ConfirmReviewItemPayload = {},
  ) {
    return api.post<SplitReviewItemResponse>(`/events/${eventId}/people/review-queue/${reviewItemId}/split`, {
      body: payload,
    });
  },

  async mergeReviewItem(
    eventId: number | string,
    reviewItemId: number | string,
    payload: MergeReviewItemPayload,
  ) {
    return api.post<MergeReviewItemResponse>(`/events/${eventId}/people/review-queue/${reviewItemId}/merge`, {
      body: payload,
    });
  },

  async createRelation(eventId: number | string, payload: EventPeopleRelationPayload) {
    return api.post<EventPersonRelation>(`/events/${eventId}/people/relations`, {
      body: payload,
    });
  },

  async updateRelation(eventId: number | string, relationId: number | string, payload: Partial<EventPeopleRelationPayload>) {
    return api.patch<EventPersonRelation>(`/events/${eventId}/people/relations/${relationId}`, {
      body: payload,
    });
  },

  async deleteRelation(eventId: number | string, relationId: number | string) {
    return api.delete<void>(`/events/${eventId}/people/relations/${relationId}`);
  },
};

export type { EventPeopleCreatePayload };

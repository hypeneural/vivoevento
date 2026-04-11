import { api } from '@/lib/api';

import type {
  ConfirmReviewItemPayload,
  ConfirmReviewItemResponse,
  EventMediaFacePeople,
  EventPeopleCreatePayload,
  EventPeoplePresetsResponse,
  EventPeopleRelationPayload,
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

  async listReviewQueue(eventId: number | string, filters: EventPeopleReviewQueueFilters = {}) {
    return api.getRaw<PaginatedApiResponse<EventPersonReviewQueueItem>>(`/events/${eventId}/people/review-queue`, {
      params: filters,
    });
  },

  async listMediaFaces(eventId: number | string, mediaId: number | string) {
    return api.get<EventMediaFacePeople[]>(`/events/${eventId}/media/${mediaId}/people`);
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

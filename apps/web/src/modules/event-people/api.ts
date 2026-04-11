import { api } from '@/lib/api';

import type {
  ConfirmReviewItemPayload,
  ConfirmReviewItemResponse,
  EventMediaFacePeople,
  EventPeopleCreatePayload,
  EventPeopleListFilters,
  EventPeopleReviewQueueFilters,
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
};

export type { EventPeopleCreatePayload };

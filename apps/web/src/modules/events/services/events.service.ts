import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';
import type { ApiOrganization, PaginatedResponse } from '@/lib/api-types';

import type {
  EventBrandingAssetKind,
  EventBrandingUploadResponse,
  EventClientOption,
  EventCreateResponse,
  EventDetailItem,
  EventFormPayload,
  EventListItem,
  EventShareLinks,
  EventTelegramOperationalStatus,
  ListEventsFilters,
  PaginatedEventsResponse,
} from '../types';

export const DEFAULT_EVENTS_LIST_FILTERS: ListEventsFilters = {
  search: undefined,
  status: undefined,
  event_type: undefined,
  module: undefined,
  date_from: undefined,
  date_to: undefined,
  sort_by: 'starts_at',
  sort_direction: 'desc',
  page: 1,
  per_page: 12,
};

export const eventsService = {
  async list(filters: ListEventsFilters = {}) {
    return api.getRaw<PaginatedEventsResponse>('/events', {
      params: filters,
    });
  },

  async listOrganizations(search?: string) {
    const response = await api.get<PaginatedResponse<ApiOrganization>>('/organizations', {
      params: {
        search: search || undefined,
        per_page: 100,
      },
    });

    return response.data.map((organization) => ({
      id: organization.id,
      label: organization.trade_name || organization.legal_name || organization.slug,
      type: organization.type,
      status: organization.status,
    }));
  },

  async show(id: number | string) {
    return api.get<EventDetailItem>(`/events/${id}`);
  },

  async create(payload: EventFormPayload) {
    return api.post<EventCreateResponse>('/events', {
      body: payload,
    });
  },

  async update(id: number | string, payload: EventFormPayload) {
    return api.patch<EventListItem>(`/events/${id}`, {
      body: payload,
    });
  },

  async publish(id: number | string) {
    return api.post<EventListItem>(`/events/${id}/publish`);
  },

  async archive(id: number | string) {
    return api.post<EventListItem>(`/events/${id}/archive`);
  },

  async shareLinks(id: number | string) {
    return api.get<EventShareLinks>(`/events/${id}/share-links`);
  },

  async telegramOperationalStatus(id: number | string) {
    return api.get<EventTelegramOperationalStatus>(`/events/${id}/telegram/operational-status`);
  },

  async listClients() {
    const response = await api.getRaw<{
      success: boolean;
      data: EventClientOption[];
      meta: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
        request_id?: string;
      };
    }>('/clients', {
      params: {
        per_page: 100,
        sort_by: 'name',
        sort_direction: 'asc',
      },
    });

    return response.data;
  },

  async uploadBrandingAsset(
    kind: EventBrandingAssetKind,
    file: File,
    previousPath?: string | null,
  ) {
    const formData = new FormData();
    formData.append('kind', kind);
    formData.append('file', file);

    if (previousPath) {
      formData.append('previous_path', previousPath);
    }

    return api.upload<EventBrandingUploadResponse>('/events/branding-assets', formData);
  },
};

export function eventsListQueryOptions(filters: ListEventsFilters = DEFAULT_EVENTS_LIST_FILTERS) {
  return {
    queryKey: queryKeys.events.list(filters),
    queryFn: () => eventsService.list(filters),
  } as const;
}

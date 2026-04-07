import { api } from '@/lib/api';

import type {
  PaginatedPartnerActivityResponse,
  PaginatedPartnerClientsResponse,
  PaginatedPartnerEventsResponse,
  PaginatedPartnerGrantsResponse,
  PaginatedPartnersResponse,
  PaginatedPartnerStaffResponse,
  PartnerActivityFilters,
  PartnerClientsFilters,
  PartnerDetailItem,
  PartnerEventsFilters,
  PartnerFormPayload,
  PartnerGrant,
  PartnerGrantPayload,
  PartnerGrantsFilters,
  PartnerListFilters,
  PartnerListItem,
  PartnerStaffFilters,
  PartnerStaffMember,
  PartnerStaffPayload,
  PartnerSuspendPayload,
} from './types';

export const partnersService = {
  list(filters: PartnerListFilters = {}) {
    return api.getRaw<PaginatedPartnersResponse>('/partners', {
      params: filters,
    });
  },

  show(partnerId: number | string) {
    return api.get<PartnerDetailItem>(`/partners/${partnerId}`);
  },

  create(payload: PartnerFormPayload) {
    return api.post<PartnerListItem>('/partners', {
      body: payload,
    });
  },

  update(partnerId: number | string, payload: PartnerFormPayload) {
    return api.patch<PartnerListItem>(`/partners/${partnerId}`, {
      body: payload,
    });
  },

  suspend(partnerId: number | string, payload: PartnerSuspendPayload) {
    return api.post<PartnerListItem>(`/partners/${partnerId}/suspend`, {
      body: payload,
    });
  },

  remove(partnerId: number | string) {
    return api.delete(`/partners/${partnerId}`);
  },

  listEvents(partnerId: number | string, filters: PartnerEventsFilters = {}) {
    return api.getRaw<PaginatedPartnerEventsResponse>(`/partners/${partnerId}/events`, {
      params: filters,
    });
  },

  listClients(partnerId: number | string, filters: PartnerClientsFilters = {}) {
    return api.getRaw<PaginatedPartnerClientsResponse>(`/partners/${partnerId}/clients`, {
      params: filters,
    });
  },

  listStaff(partnerId: number | string, filters: PartnerStaffFilters = {}) {
    return api.getRaw<PaginatedPartnerStaffResponse>(`/partners/${partnerId}/staff`, {
      params: filters,
    });
  },

  inviteStaff(partnerId: number | string, payload: PartnerStaffPayload) {
    return api.post<PartnerStaffMember>(`/partners/${partnerId}/staff`, {
      body: payload,
    });
  },

  listGrants(partnerId: number | string, filters: PartnerGrantsFilters = {}) {
    return api.getRaw<PaginatedPartnerGrantsResponse>(`/partners/${partnerId}/grants`, {
      params: filters,
    });
  },

  createGrant(partnerId: number | string, payload: PartnerGrantPayload) {
    return api.post<PartnerGrant>(`/partners/${partnerId}/grants`, {
      body: payload,
    });
  },

  listActivity(partnerId: number | string, filters: PartnerActivityFilters = {}) {
    return api.getRaw<PaginatedPartnerActivityResponse>(`/partners/${partnerId}/activity`, {
      params: filters,
    });
  },
};

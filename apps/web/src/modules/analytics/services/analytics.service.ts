import api from '@/lib/api';
import type { ApiEvent, ApiOrganization, PaginatedResponse } from '@/lib/api-types';

import type {
  AnalyticsFilters,
  AnalyticsOption,
  EventAnalyticsResponse,
  PlatformAnalyticsResponse,
} from '../types';

interface AnalyticsClientResult {
  id: number;
  name: string;
  organization_name?: string | null;
  email?: string | null;
  events_count?: number;
}

function compactParams(filters: Record<string, string | number | undefined>) {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== ''),
  ) as Record<string, string | number>;
}

function buildOrganizationOption(organization: ApiOrganization): AnalyticsOption {
  return {
    id: organization.id,
    label: organization.trade_name || organization.legal_name || organization.slug,
    description: organization.type,
  };
}

function buildClientOption(client: AnalyticsClientResult): AnalyticsOption {
  return {
    id: client.id,
    label: client.name,
    description: client.organization_name || client.email || null,
  };
}

function buildEventOption(event: ApiEvent): AnalyticsOption {
  return {
    id: event.id,
    label: event.title,
    description: event.client_name || event.organization_name || event.status,
  };
}

export const analyticsService = {
  getPlatform(filters: AnalyticsFilters) {
    return api.get<PlatformAnalyticsResponse>('/analytics/platform', {
      params: compactParams({
        period: filters.period,
        date_from: filters.date_from,
        date_to: filters.date_to,
        organization_id: filters.organization_id,
        client_id: filters.client_id,
        event_status: filters.event_status,
        module: filters.module,
      }),
    });
  },

  getEvent(eventId: number | string, filters: AnalyticsFilters) {
    return api.get<EventAnalyticsResponse>(`/analytics/events/${eventId}`, {
      params: compactParams({
        period: filters.period,
        date_from: filters.date_from,
        date_to: filters.date_to,
        module: filters.module,
      }),
    });
  },

  async searchOrganizations(search?: string) {
    const response = await api.get<PaginatedResponse<ApiOrganization>>('/organizations', {
      params: compactParams({
        type: 'partner',
        search: search || undefined,
        per_page: 10,
      }),
    });

    return response.data.map(buildOrganizationOption);
  },

  async getOrganizationOption(id: number | string) {
    const organization = await api.get<ApiOrganization>(`/organizations/${id}`);
    return buildOrganizationOption(organization);
  },

  async searchClients(search?: string, organizationId?: number) {
    const response = await api.get<PaginatedResponse<AnalyticsClientResult>>('/clients', {
      params: compactParams({
        search: search || undefined,
        organization_id: organizationId,
        sort_by: 'name',
        sort_direction: 'asc',
        per_page: 10,
      }),
    });

    return response.data.map(buildClientOption);
  },

  async getClientOption(id: number | string) {
    const client = await api.get<AnalyticsClientResult>(`/clients/${id}`);
    return buildClientOption(client);
  },

  async searchEvents(search: string | undefined, filters: Pick<AnalyticsFilters, 'organization_id' | 'client_id' | 'event_status' | 'module'>) {
    const response = await api.get<PaginatedResponse<ApiEvent>>('/events', {
      params: compactParams({
        search: search || undefined,
        organization_id: filters.organization_id,
        client_id: filters.client_id,
        status: filters.event_status,
        module: filters.module,
        sort_by: 'title',
        sort_direction: 'asc',
        per_page: 10,
      }),
    });

    return response.data.map(buildEventOption);
  },

  async getEventOption(id: number | string) {
    const event = await api.get<ApiEvent>(`/events/${id}`);
    return buildEventOption(event);
  },
};

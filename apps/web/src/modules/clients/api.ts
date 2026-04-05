import { api } from '@/lib/api';
import type { ApiOrganization } from '@/lib/api-types';

import type {
  ClientFormPayload,
  ClientItem,
  ClientListFilters,
  ClientPlanOption,
  PaginatedClientsResponse,
  PaginatedOrganizationsResponse,
} from './types';

interface BackendPlan {
  id: number;
  code: string;
  name: string;
}

export const clientsService = {
  list(filters: ClientListFilters = {}) {
    return api.getRaw<PaginatedClientsResponse>('/clients', {
      params: filters,
    });
  },

  create(payload: ClientFormPayload) {
    return api.post<ClientItem>('/clients', {
      body: payload,
    });
  },

  update(clientId: number | string, payload: ClientFormPayload) {
    return api.patch<ClientItem>(`/clients/${clientId}`, {
      body: payload,
    });
  },

  remove(clientId: number | string) {
    return api.delete(`/clients/${clientId}`);
  },

  async listOrganizations(search?: string) {
    const response = await api.getRaw<PaginatedOrganizationsResponse>('/organizations', {
      params: {
        per_page: 100,
        search: search || undefined,
      },
    });

    return response.data;
  },

  async listPlans() {
    const plans = await api.get<BackendPlan[]>('/plans');

    return plans.map((plan): ClientPlanOption => ({
      id: plan.id,
      code: plan.code,
      name: plan.name,
    }));
  },
};

export function buildOrganizationOptions(organizations: ApiOrganization[]) {
  return organizations.map((organization) => ({
    id: organization.id,
    label: organization.trade_name || organization.legal_name || organization.slug,
    type: organization.type,
    status: organization.status,
  }));
}

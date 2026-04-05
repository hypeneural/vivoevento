import { api } from '@/lib/api';

import type { AuditFiltersResponse, AuditListFilters, AuditListResponse } from '../types';

export const auditService = {
  async list(filters: AuditListFilters = {}) {
    return api.getRaw<AuditListResponse>('/audit', {
      params: filters,
    });
  },

  async filters(organizationId?: number | null) {
    return api.get<AuditFiltersResponse>('/audit/filters', {
      params: organizationId ? { organization_id: organizationId } : undefined,
    });
  },
};

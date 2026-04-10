import { api } from '@/lib/api';
import type { ApiEventMediaDetail } from '@/lib/api-types';

import type { MediaCatalogFilters, MediaCatalogPageResponse } from '../types';

function normalizeCatalogFilters(filters: MediaCatalogFilters): Record<string, string | number | undefined> {
  return Object.fromEntries(
    Object.entries(filters).map(([key, value]) => [
      key,
      typeof value === 'boolean' ? (value ? 1 : 0) : value,
    ]),
  ) as Record<string, string | number | undefined>;
}

export const mediaService = {
  async list(filters: MediaCatalogFilters = {}) {
    return api.get<MediaCatalogPageResponse>('/media', {
      params: normalizeCatalogFilters(filters),
    });
  },

  async show(mediaId: number | string) {
    return api.get<ApiEventMediaDetail>(`/media/${mediaId}`);
  },
};

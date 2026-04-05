import { api } from '@/lib/api';
import type { ApiEventMediaDetail } from '@/lib/api-types';

import type { MediaCatalogFilters, MediaCatalogPageResponse } from '../types';

export const mediaService = {
  async list(filters: MediaCatalogFilters = {}) {
    return api.get<MediaCatalogPageResponse>('/media', {
      params: filters,
    });
  },

  async show(mediaId: number | string) {
    return api.get<ApiEventMediaDetail>(`/media/${mediaId}`);
  },
};

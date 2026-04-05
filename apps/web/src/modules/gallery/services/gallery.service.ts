import { api } from '@/lib/api';
import type { ApiEventMediaDetail, ApiEventMediaItem } from '@/lib/api-types';

import type { GalleryCatalogFilters, GalleryCatalogPageResponse } from '../types';

export const galleryService = {
  async list(filters: GalleryCatalogFilters = {}) {
    return api.getRaw<GalleryCatalogPageResponse>('/gallery', {
      params: filters,
    });
  },

  async show(mediaId: number | string) {
    return api.get<ApiEventMediaDetail>(`/media/${mediaId}`);
  },

  async updateFavorite(mediaId: number | string, isFeatured: boolean) {
    return api.patch<ApiEventMediaItem>(`/media/${mediaId}/favorite`, {
      body: {
        is_featured: isFeatured,
      },
    });
  },

  async updatePinned(mediaId: number | string, isPinned: boolean) {
    return api.patch<ApiEventMediaItem>(`/media/${mediaId}/pin`, {
      body: {
        is_pinned: isPinned,
      },
    });
  },

  async publish(eventId: number | string, mediaId: number | string) {
    return api.post<ApiEventMediaItem>(`/events/${eventId}/gallery/${mediaId}/publish`);
  },

  async hide(eventId: number | string, mediaId: number | string) {
    return api.delete<ApiEventMediaItem>(`/events/${eventId}/gallery/${mediaId}`);
  },
};

import { api } from '@/lib/api';
import type { ApiEventMediaDetail, ApiEventMediaItem, ModerationStatsMeta } from '@/lib/api-types';

import type {
  ModerationBulkActionResponse,
  ModerationFeedPage,
  ModerationListFilters,
} from '../types';

export const moderationService = {
  async list(filters: ModerationListFilters = {}, signal?: AbortSignal) {
    return api.getRaw<ModerationFeedPage>('/media/feed', {
      params: filters,
      signal,
    });
  },

  async listStats(filters: Omit<ModerationListFilters, 'per_page' | 'cursor'> = {}, signal?: AbortSignal) {
    return api.get<ModerationStatsMeta>('/media/feed/stats', {
      params: filters,
      signal,
    });
  },

  async show(mediaId: number | string, signal?: AbortSignal) {
    return api.get<ApiEventMediaDetail>(`/media/${mediaId}`, { signal });
  },

  async approve(mediaId: number | string) {
    return api.post<ApiEventMediaItem>(`/media/${mediaId}/approve`);
  },

  async reject(mediaId: number | string) {
    return api.post<ApiEventMediaItem>(`/media/${mediaId}/reject`);
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

  async blockSender(mediaId: number | string, payload: { reason?: string; expires_at?: string | null }) {
    return api.post<ApiEventMediaItem>(`/media/${mediaId}/sender-block`, {
      body: payload,
    });
  },

  async unblockSender(mediaId: number | string) {
    return api.delete<ApiEventMediaItem>(`/media/${mediaId}/sender-block`);
  },

  async bulkApprove(ids: number[]) {
    return api.post<ModerationBulkActionResponse>('/media/bulk/approve', {
      body: { ids },
    });
  },

  async bulkReject(ids: number[]) {
    return api.post<ModerationBulkActionResponse>('/media/bulk/reject', {
      body: { ids },
    });
  },

  async bulkFavorite(ids: number[], isFeatured: boolean) {
    return api.patch<ModerationBulkActionResponse>('/media/bulk/favorite', {
      body: {
        ids,
        is_featured: isFeatured,
      },
    });
  },

  async bulkPinned(ids: number[], isPinned: boolean) {
    return api.patch<ModerationBulkActionResponse>('/media/bulk/pin', {
      body: {
        ids,
        is_pinned: isPinned,
      },
    });
  },
};

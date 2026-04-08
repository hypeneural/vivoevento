import { api } from '@/lib/api';
import type { PaginatedResponse } from '@/lib/api-types';

import type {
  MediaReplyEventHistoryItem,
  MediaReplyEventOption,
  MediaIntelligenceGlobalSettings,
  MediaReplyPromptCategory,
  MediaReplyPromptPreset,
  MediaReplyPromptTestRun,
  RunMediaReplyPromptTestPayload,
  SaveMediaReplyPromptCategoryPayload,
  SaveMediaReplyPromptPresetPayload,
  UpdateMediaIntelligenceGlobalSettingsPayload,
} from './types';

export const aiMediaRepliesService = {
  getConfiguration() {
    return api.get<MediaIntelligenceGlobalSettings>('/ia/respostas-de-midia/configuracao');
  },

  updateConfiguration(payload: UpdateMediaIntelligenceGlobalSettingsPayload) {
    return api.patch<MediaIntelligenceGlobalSettings>('/ia/respostas-de-midia/configuracao', {
      body: payload,
    });
  },

  listCategories() {
    return api.get<MediaReplyPromptCategory[]>('/ia/respostas-de-midia/categorias');
  },

  createCategory(payload: SaveMediaReplyPromptCategoryPayload) {
    return api.post<MediaReplyPromptCategory>('/ia/respostas-de-midia/categorias', {
      body: payload,
    });
  },

  updateCategory(categoryId: number, payload: SaveMediaReplyPromptCategoryPayload) {
    return api.patch<MediaReplyPromptCategory>(`/ia/respostas-de-midia/categorias/${categoryId}`, {
      body: payload,
    });
  },

  deleteCategory(categoryId: number) {
    return api.delete<void>(`/ia/respostas-de-midia/categorias/${categoryId}`);
  },

  listPresets() {
    return api.get<MediaReplyPromptPreset[]>('/ia/respostas-de-midia/presets');
  },

  createPreset(payload: SaveMediaReplyPromptPresetPayload) {
    return api.post<MediaReplyPromptPreset>('/ia/respostas-de-midia/presets', {
      body: payload,
    });
  },

  updatePreset(presetId: number, payload: SaveMediaReplyPromptPresetPayload) {
    return api.patch<MediaReplyPromptPreset>(`/ia/respostas-de-midia/presets/${presetId}`, {
      body: payload,
    });
  },

  deletePreset(presetId: number) {
    return api.delete<void>(`/ia/respostas-de-midia/presets/${presetId}`);
  },

  async runPromptTest(payload: RunMediaReplyPromptTestPayload) {
    const formData = new FormData();

    if (payload.event_id) {
      formData.append('event_id', String(payload.event_id));
    }

    formData.append('provider_key', payload.provider_key);
    formData.append('model_key', payload.model_key);

    if (payload.prompt_template) {
      formData.append('prompt_template', payload.prompt_template);
    }

    if (payload.preset_id) {
      formData.append('preset_id', String(payload.preset_id));
    }

    payload.images.forEach((image) => {
      formData.append('images[]', image);
    });

    return api.upload<MediaReplyPromptTestRun>('/ia/respostas-de-midia/testes', formData);
  },

  listPromptTests(params?: {
    event_id?: number | null;
    provider_key?: string | null;
    status?: string | null;
    per_page?: number;
  }) {
    return api.get<PaginatedResponse<MediaReplyPromptTestRun>>('/ia/respostas-de-midia/testes', {
      params: {
        event_id: params?.event_id ?? undefined,
        provider_key: params?.provider_key ?? undefined,
        status: params?.status ?? undefined,
        per_page: params?.per_page ?? 15,
      },
    });
  },

  getPromptTest(testId: number) {
    return api.get<MediaReplyPromptTestRun>(`/ia/respostas-de-midia/testes/${testId}`);
  },

  listEventOptions() {
    return api.get<MediaReplyEventOption[]>('/ia/respostas-de-midia/eventos');
  },

  listEventHistory(params?: {
    event_id?: number | null;
    provider_key?: string | null;
    model_key?: string | null;
    status?: string | null;
    preset_name?: string | null;
    sender_query?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    per_page?: number;
  }) {
    return api.get<PaginatedResponse<MediaReplyEventHistoryItem>>('/ia/respostas-de-midia/historico-eventos', {
      params: {
        event_id: params?.event_id ?? undefined,
        provider_key: params?.provider_key ?? undefined,
        model_key: params?.model_key ?? undefined,
        status: params?.status ?? undefined,
        preset_name: params?.preset_name ?? undefined,
        sender_query: params?.sender_query ?? undefined,
        date_from: params?.date_from ?? undefined,
        date_to: params?.date_to ?? undefined,
        per_page: params?.per_page ?? 15,
      },
    });
  },

  getEventHistoryItem(itemId: number) {
    return api.get<MediaReplyEventHistoryItem>(`/ia/respostas-de-midia/historico-eventos/${itemId}`);
  },
};

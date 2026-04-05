import api from '@/lib/api';
import type {
  ApiEventHubInsightsResponse,
  ApiHubBuilderConfig,
  ApiEventHubSettingsResponse,
  ApiHubButton,
  ApiHubHeroUploadResponse,
  ApiHubPreset,
  ApiPublicHubResponse,
} from '@/lib/api-types';

export interface UpdateEventHubSettingsPayload {
  is_enabled: boolean;
  headline: string | null;
  subheadline: string | null;
  welcome_text: string | null;
  hero_image_path: string | null;
  button_style: {
    background_color: string;
    text_color: string;
    outline_color: string;
  };
  builder_config: ApiHubBuilderConfig;
  buttons: Array<Pick<
    ApiHubButton,
    | 'id'
    | 'type'
    | 'preset_key'
    | 'label'
    | 'icon'
    | 'href'
    | 'is_visible'
    | 'opens_in_new_tab'
    | 'background_color'
    | 'text_color'
    | 'outline_color'
  >>;
}

export interface StoreHubPresetPayload {
  event_id?: string | number | null;
  name: string;
  description?: string | null;
  button_style: UpdateEventHubSettingsPayload['button_style'];
  builder_config: ApiHubBuilderConfig;
  buttons: UpdateEventHubSettingsPayload['buttons'];
}

export function getEventHubSettings(eventId: string | number) {
  return api.get<ApiEventHubSettingsResponse>(`/events/${eventId}/hub/settings`);
}

export function getEventHubInsights(eventId: string | number, days: 7 | 30 | 90) {
  return api.get<ApiEventHubInsightsResponse>(`/events/${eventId}/hub/insights`, {
    params: { days },
  });
}

export function listHubPresets() {
  return api.get<ApiHubPreset[]>('/hub/presets');
}

export function storeHubPreset(payload: StoreHubPresetPayload) {
  return api.post<ApiHubPreset>('/hub/presets', {
    body: payload,
  });
}

export function updateEventHubSettings(
  eventId: string | number,
  payload: UpdateEventHubSettingsPayload,
) {
  return api.patch<ApiEventHubSettingsResponse>(`/events/${eventId}/hub/settings`, {
    body: payload,
  });
}

export function uploadHubHeroImage(
  eventId: string | number,
  file: File,
  previousPath?: string | null,
) {
  const formData = new FormData();
  formData.append('file', file);

  if (previousPath) {
    formData.append('previous_path', previousPath);
  }

  return api.upload<ApiHubHeroUploadResponse>(`/events/${eventId}/hub/hero-image`, formData);
}

export function uploadHubSponsorLogo(
  eventId: string | number,
  file: File,
  previousPath?: string | null,
) {
  const formData = new FormData();
  formData.append('file', file);

  if (previousPath) {
    formData.append('previous_path', previousPath);
  }

  return api.upload<ApiHubHeroUploadResponse>(`/events/${eventId}/hub/sponsor-logo`, formData);
}

export function getPublicHub(slug: string) {
  return api.get<ApiPublicHubResponse>(`/public/events/${slug}/hub`);
}

export function trackPublicHubButtonClick(slug: string, buttonId: string) {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api/v1';
  const url = `${baseUrl}/public/events/${encodeURIComponent(slug)}/hub/click`;
  const body = JSON.stringify({ button_id: buttonId });

  if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
    const sent = navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));

    if (sent) {
      return;
    }
  }

  void fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body,
    keepalive: true,
  });
}

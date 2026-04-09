import api from '@/lib/api';
import type {
  ApiWallAdItem,
  ApiWallActionResponse,
  ApiWallDiagnosticsResponse,
  ApiWallInsightsResponse,
  ApiWallLiveSnapshotResponse,
  ApiWallOptionsResponse,
  ApiWallPlayerCommand,
  ApiWallPlayerCommandResponse,
  ApiWallSettings,
  ApiWallSettingsResponse,
  ApiWallSimulationResponse,
} from '@/lib/api-types';

export type UpdateEventWallSettingsPayload = Partial<ApiWallSettings>;

export type EventWallAction = 'start' | 'pause' | 'stop' | 'full-stop' | 'expire' | 'reset';

export function getEventWallSettings(eventId: string | number) {
  return api.get<ApiWallSettingsResponse>(`/events/${eventId}/wall/settings`);
}

export function getEventWallDiagnostics(eventId: string | number) {
  return api.get<ApiWallDiagnosticsResponse>(`/events/${eventId}/wall/diagnostics`);
}

export function getEventWallInsights(eventId: string | number) {
  return api.get<ApiWallInsightsResponse>(`/events/${eventId}/wall/insights`);
}

export function getEventWallLiveSnapshot(eventId: string | number) {
  return api.get<ApiWallLiveSnapshotResponse>(`/events/${eventId}/wall/live-snapshot`);
}

export function getEventWallAds(eventId: string | number) {
  return api.get<ApiWallAdItem[]>(`/events/${eventId}/wall/ads`);
}

export function updateEventWallSettings(
  eventId: string | number,
  payload: UpdateEventWallSettingsPayload,
) {
  return api.patch<ApiWallSettingsResponse>(`/events/${eventId}/wall/settings`, {
    body: payload,
  });
}

export function runEventWallAction(
  eventId: string | number,
  action: EventWallAction,
) {
  return api.post<ApiWallActionResponse>(`/events/${eventId}/wall/${action}`);
}

export function getWallOptions() {
  return api.get<ApiWallOptionsResponse>('/wall/options');
}

export function simulateEventWall(
  eventId: string | number,
  payload: UpdateEventWallSettingsPayload,
) {
  return api.post<ApiWallSimulationResponse>(`/events/${eventId}/wall/simulate`, {
    body: payload,
  });
}

export function runEventWallPlayerCommand(
  eventId: string | number,
  command: ApiWallPlayerCommand,
  reason?: string | null,
) {
  return api.post<ApiWallPlayerCommandResponse>(`/events/${eventId}/wall/player-command`, {
    body: {
      command,
      reason: reason ?? null,
    },
  });
}

export function createEventWallAd(
  eventId: string | number,
  payload: {
    file: File;
    durationSeconds?: number | null;
  },
) {
  const formData = new FormData();
  formData.append('file', payload.file);

  if (payload.durationSeconds != null) {
    formData.append('duration_seconds', String(payload.durationSeconds));
  }

  return api.upload<ApiWallAdItem>(`/events/${eventId}/wall/ads`, formData);
}

export function deleteEventWallAd(eventId: string | number, adId: number) {
  return api.delete<void>(`/events/${eventId}/wall/ads/${adId}`);
}

export function reorderEventWallAds(eventId: string | number, order: number[]) {
  return api.patch<{ reordered: boolean }>(`/events/${eventId}/wall/ads/reorder`, {
    body: { order },
  });
}

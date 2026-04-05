import api from '@/lib/api';
import type {
  ApiWallActionResponse,
  ApiWallDiagnosticsResponse,
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

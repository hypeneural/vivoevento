import api from '@/lib/api';
import type {
  CreateEventAccessInvitationPayload,
  EventAccessInvitation,
  EventAccessMember,
  EventAccessPresetPayload,
  ResendEventAccessInvitationPayload,
} from './types';

export const eventAccessApi = {
  getPresets() {
    return api.get<EventAccessPresetPayload>('/access/presets');
  },

  listMembers(eventId: string | number) {
    return api.get<EventAccessMember[]>(`/events/${eventId}/team`);
  },

  removeMember(eventId: string | number, memberId: string | number) {
    return api.delete<null>(`/events/${eventId}/team/${memberId}`);
  },

  listInvitations(eventId: string | number) {
    return api.get<EventAccessInvitation[]>(`/events/${eventId}/access/invitations`);
  },

  createInvitation(eventId: string | number, payload: CreateEventAccessInvitationPayload) {
    return api.post<EventAccessInvitation>(`/events/${eventId}/access/invitations`, {
      body: payload,
    });
  },

  resendInvitation(
    eventId: string | number,
    invitationId: string | number,
    payload: ResendEventAccessInvitationPayload,
  ) {
    return api.post<EventAccessInvitation>(`/events/${eventId}/access/invitations/${invitationId}/resend`, {
      body: payload,
    });
  },

  revokeInvitation(eventId: string | number, invitationId: string | number) {
    return api.post<EventAccessInvitation>(`/events/${eventId}/access/invitations/${invitationId}/revoke`, {
      body: {},
    });
  },
};

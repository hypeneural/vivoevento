import api from '@/lib/api';
import type {
  AcceptEventInvitationPayload,
  AcceptEventInvitationResult,
  PublicEventInvitationDetails,
} from './types';

export const eventInvitationsApi = {
  getPublicInvitation(token: string) {
    return api.get<PublicEventInvitationDetails>(`/public/event-invitations/${token}`);
  },

  acceptPublicInvitation(token: string, payload: AcceptEventInvitationPayload) {
    return api.post<AcceptEventInvitationResult>(`/public/event-invitations/${token}/accept`, {
      body: payload,
    });
  },

  acceptAuthenticatedInvitation(token: string) {
    return api.post<AcceptEventInvitationResult>(`/event-invitations/${token}/accept`);
  },
};

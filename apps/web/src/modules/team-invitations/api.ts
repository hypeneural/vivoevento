import { api } from '@/lib/api';

import type { AcceptOrganizationInvitationResult, PublicOrganizationInvitation } from './types';

export const organizationInvitationsApi = {
  getPublicInvitation(token: string) {
    return api.get<PublicOrganizationInvitation>(`/public/organization-invitations/${token}`);
  },

  acceptPublicInvitation(token: string, payload: { password: string; password_confirmation: string; device_name?: string }) {
    return api.post<AcceptOrganizationInvitationResult>(`/public/organization-invitations/${token}/accept`, {
      body: payload,
    });
  },

  acceptAuthenticatedInvitation(token: string) {
    return api.post<AcceptOrganizationInvitationResult>(`/organization-invitations/${token}/accept`);
  },
};

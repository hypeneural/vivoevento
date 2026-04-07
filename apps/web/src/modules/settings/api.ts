import { api } from '@/lib/api';

import type {
  CurrentOrganizationLogoUploadResponse,
  InviteCurrentOrganizationTeamMemberPayload,
  OrganizationTeamResponse,
  UpdateCurrentOrganizationBrandingPayload,
  UpdateCurrentOrganizationPayload,
  UpdateCurrentUserPreferencesPayload,
} from './types';

export const settingsService = {
  listCurrentOrganizationTeam() {
    return api.getRaw<OrganizationTeamResponse>('/organizations/current/team');
  },

  updateCurrentOrganization(payload: UpdateCurrentOrganizationPayload) {
    return api.patch('/organizations/current', { body: payload });
  },

  updateCurrentOrganizationBranding(payload: UpdateCurrentOrganizationBrandingPayload) {
    return api.patch('/organizations/current/branding', { body: payload });
  },

  uploadCurrentOrganizationLogo(file: File) {
    const formData = new FormData();
    formData.append('logo', file);

    return api.upload<CurrentOrganizationLogoUploadResponse>('/organizations/current/branding/logo', formData);
  },

  inviteCurrentOrganizationTeamMember(payload: InviteCurrentOrganizationTeamMemberPayload) {
    return api.post('/organizations/current/team', { body: payload });
  },

  removeCurrentOrganizationTeamMember(memberId: number) {
    return api.delete(`/organizations/current/team/${memberId}`);
  },

  updateCurrentUserPreferences(payload: UpdateCurrentUserPreferencesPayload) {
    return api.patch('/auth/me', {
      body: {
        preferences: payload,
      },
    });
  },
};

import { api } from '@/lib/api';

import type {
  MediaIntelligenceGlobalSettings,
  CurrentOrganizationBrandingAssetKind,
  CurrentOrganizationBrandingAssetUploadResponse,
  CurrentOrganizationLogoUploadResponse,
  InviteCurrentOrganizationTeamMemberPayload,
  OrganizationTeamMember,
  OrganizationTeamResponse,
  OrganizationTeamInvitation,
  OrganizationTeamInvitationResponse,
  UpdateMediaIntelligenceGlobalSettingsPayload,
  UpdateCurrentOrganizationBrandingPayload,
  UpdateCurrentOrganizationPayload,
  UpdateCurrentUserPreferencesPayload,
  TransferCurrentOrganizationOwnershipPayload,
} from './types';

export const settingsService = {
  listCurrentOrganizationTeam() {
    return api.getRaw<OrganizationTeamResponse>('/organizations/current/team');
  },

  listCurrentOrganizationTeamInvitations() {
    return api.getRaw<OrganizationTeamInvitationResponse>('/organizations/current/team/invitations');
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

  uploadCurrentOrganizationBrandingAsset(kind: CurrentOrganizationBrandingAssetKind, file: File) {
    const formData = new FormData();
    formData.append('kind', kind);
    formData.append('asset', file);

    return api.upload<CurrentOrganizationBrandingAssetUploadResponse>('/organizations/current/branding/assets', formData);
  },

  inviteCurrentOrganizationTeamMember(payload: InviteCurrentOrganizationTeamMemberPayload) {
    return api.post<OrganizationTeamInvitation>('/organizations/current/team', { body: payload });
  },

  resendCurrentOrganizationTeamInvitation(invitationId: number, sendViaWhatsApp = true) {
    return api.post<OrganizationTeamInvitation>(`/organizations/current/team/invitations/${invitationId}/resend`, {
      body: { send_via_whatsapp: sendViaWhatsApp },
    });
  },

  revokeCurrentOrganizationTeamInvitation(invitationId: number) {
    return api.post<OrganizationTeamInvitation>(`/organizations/current/team/invitations/${invitationId}/revoke`);
  },

  removeCurrentOrganizationTeamMember(memberId: number) {
    return api.delete(`/organizations/current/team/${memberId}`);
  },

  transferCurrentOrganizationOwnership(payload: TransferCurrentOrganizationOwnershipPayload) {
    return api.post<OrganizationTeamMember>('/organizations/current/team/ownership-transfer', { body: payload });
  },

  updateCurrentUserPreferences(payload: UpdateCurrentUserPreferencesPayload) {
    return api.patch('/auth/me', {
      body: {
        preferences: payload,
      },
    });
  },

  getMediaIntelligenceGlobalSettings() {
    return api.get<MediaIntelligenceGlobalSettings>('/media-intelligence/global-settings');
  },

  updateMediaIntelligenceGlobalSettings(payload: UpdateMediaIntelligenceGlobalSettingsPayload) {
    return api.patch<MediaIntelligenceGlobalSettings>('/media-intelligence/global-settings', {
      body: payload,
    });
  },
};

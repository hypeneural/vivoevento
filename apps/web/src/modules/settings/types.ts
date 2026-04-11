export interface OrganizationTeamMember {
  id: number;
  role_key: string;
  is_owner: boolean;
  status: string;
  invited_at: string | null;
  joined_at: string | null;
  user: {
    id: number;
    name: string;
    email: string | null;
    phone?: string | null;
    avatar_path?: string | null;
  } | null;
}

export interface OrganizationTeamResponse {
  success: boolean;
  data: OrganizationTeamMember[];
  meta?: Record<string, unknown>;
}

export interface OrganizationTeamInvitation {
  id: number;
  organization_id: number;
  status: string;
  role_key: InviteCurrentOrganizationTeamMemberRoleKey;
  role_label: string;
  role_description: string;
  existing_user_id: number | null;
  invitee: {
    name: string;
    email: string | null;
    phone: string | null;
  };
  delivery_channel: string | null;
  delivery_status: string | null;
  delivery_error: string | null;
  invitation_url: string | null;
  token_expires_at: string | null;
  last_sent_at: string | null;
  accepted_at: string | null;
  revoked_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface OrganizationTeamInvitationResponse {
  success: boolean;
  data: OrganizationTeamInvitation[];
  meta?: Record<string, unknown>;
}

export interface UpdateCurrentOrganizationPayload {
  name?: string;
  slug?: string;
  custom_domain?: string;
}

export interface UpdateCurrentOrganizationBrandingPayload {
  primary_color?: string;
  secondary_color?: string;
  custom_domain?: string;
}

export interface CurrentOrganizationLogoUploadResponse {
  logo_path: string;
  logo_url: string;
}

export type CurrentOrganizationBrandingAssetKind =
  | 'logo'
  | 'logo_dark'
  | 'favicon'
  | 'watermark'
  | 'cover';

export interface CurrentOrganizationBrandingAssetUploadResponse {
  kind: CurrentOrganizationBrandingAssetKind;
  path: string;
  url: string;
}

export type InviteCurrentOrganizationTeamMemberRoleKey =
  | 'partner-manager'
  | 'event-operator'
  | 'financeiro'
  | 'viewer';

export interface InviteCurrentOrganizationTeamMemberPayload {
  user: {
    name: string;
    email?: string;
    phone: string;
  };
  role_key: InviteCurrentOrganizationTeamMemberRoleKey;
  send_via_whatsapp?: boolean;
}

export interface TransferCurrentOrganizationOwnershipPayload {
  member_id: number;
}

export interface UpdateCurrentUserPreferencesPayload {
  email_notifications?: boolean;
  push_notifications?: boolean;
  compact_mode?: boolean;
}

export interface MediaIntelligenceGlobalSettings {
  id: number | null;
  reply_text_prompt: string;
  reply_text_fixed_templates: string[];
  created_at: string | null;
  updated_at: string | null;
}

export interface UpdateMediaIntelligenceGlobalSettingsPayload {
  reply_text_prompt: string;
  reply_text_fixed_templates: string[];
}

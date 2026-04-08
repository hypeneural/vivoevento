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

export interface InviteCurrentOrganizationTeamMemberPayload {
  user: {
    name: string;
    email: string;
    phone?: string;
  };
  role_key: 'partner-owner' | 'partner-manager' | 'event-operator' | 'financeiro' | 'viewer';
  is_owner?: boolean;
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

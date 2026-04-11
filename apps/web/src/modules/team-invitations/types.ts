import type { MeResponse } from '@/lib/api-types';

export interface PublicOrganizationInvitation {
  id: number;
  organization: {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
  };
  invited_by?: {
    name: string | null;
  } | null;
  invitee_name: string;
  invitee_contact: {
    email: string | null;
    phone_masked: string | null;
  };
  access: {
    role_key: string;
    role_label: string;
    description: string;
  };
  requires_existing_login: boolean;
  token_expires_at: string | null;
  invitation_url: string | null;
}

export interface AcceptOrganizationInvitationResult {
  accepted: boolean;
  token: string | null;
  next_path: string;
  session: MeResponse;
}

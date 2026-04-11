import type { MeResponse } from '@/lib/api-types';

export interface PublicEventInvitationDetails {
  id: number;
  status: string;
  requires_existing_login: boolean;
  invitee_name: string;
  invitee_contact: {
    email: string | null;
    phone_masked: string | null;
  };
  event: {
    id: number;
    title: string;
    date: string | null;
    status: string | null;
  };
  organization: {
    id: number;
    name: string;
    slug: string | null;
  };
  access: {
    preset_key: string;
    role_label: string;
    description: string;
    capabilities: string[];
  };
  next_path: string;
  token_expires_at: string | null;
}

export interface AcceptEventInvitationPayload {
  password?: string;
  password_confirmation?: string;
  device_name?: string;
}

export interface AcceptEventInvitationResult {
  accepted: boolean;
  token: string | null;
  next_path: string;
  session: MeResponse;
}

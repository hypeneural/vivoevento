export interface EventAccessPreset {
  key: string;
  scope: 'event' | 'organization';
  persisted_role: string;
  label: string;
  description: string;
  capabilities: string[];
}

export interface EventAccessPresetPayload {
  event: EventAccessPreset[];
  organization: EventAccessPreset[];
}

export interface EventAccessMember {
  id: number;
  event_id: number;
  role: string;
  role_key: string;
  role_label: string;
  capabilities: string[];
  user: {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    avatar_path?: string | null;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface EventAccessInvitation {
  id: number;
  event_id: number;
  organization_id: number;
  status: 'pending' | 'accepted' | 'revoked' | 'expired' | string;
  preset_key: string;
  persisted_role: string;
  role_label: string;
  capabilities: string[];
  existing_user_id: number | null;
  invitee: {
    name: string;
    email: string | null;
    phone: string | null;
  };
  delivery_channel: string | null;
  delivery_status: string | null;
  delivery_error: string | null;
  invitation_url: string;
  token_expires_at: string | null;
  last_sent_at: string | null;
  accepted_at: string | null;
  revoked_at: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface CreateEventAccessInvitationPayload {
  invitee: {
    name: string;
    email?: string;
    phone: string;
  };
  preset_key: string;
  send_via_whatsapp: boolean;
}

export interface ResendEventAccessInvitationPayload {
  send_via_whatsapp: boolean;
}

import type { PaginatedResponse } from '@/lib/api-types';

export type WhatsAppProviderKey = 'zapi' | 'evolution';
export type WhatsAppInstanceStatus =
  | 'draft'
  | 'configured'
  | 'connected'
  | 'disconnected'
  | 'invalid_credentials'
  | 'error';

export interface WhatsAppProviderSummary {
  id: number | null;
  key: WhatsAppProviderKey;
  name: string;
  label: string;
}

export interface WhatsAppProviderConfigView {
  instance_id?: string | null;
  base_url?: string | null;
  instance_token_configured?: boolean;
  instance_token_masked?: string | null;
  client_token_configured?: boolean;
  client_token_masked?: string | null;
  server_url?: string | null;
  auth_type?: 'global_apikey' | 'instance_apikey' | null;
  integration?: 'WHATSAPP-BAILEYS' | 'WHATSAPP-BUSINESS' | null;
  external_instance_name?: string | null;
  phone_e164?: string | null;
  api_key_configured?: boolean;
  api_key_masked?: string | null;
}

export interface WhatsAppInstanceItem {
  id: number;
  uuid: string;
  organization_id: number;
  provider_key: WhatsAppProviderKey;
  provider: WhatsAppProviderSummary;
  name: string;
  instance_name: string;
  external_instance_id: string;
  phone_number: string | null;
  formatted_phone: string | null;
  is_active: boolean;
  is_default: boolean;
  status: WhatsAppInstanceStatus;
  raw_status?: string | null;
  connected_at: string | null;
  disconnected_at: string | null;
  last_status_sync_at: string | null;
  last_health_check_at: string | null;
  last_health_status: string | null;
  last_error: string | null;
  notes: string | null;
  settings: {
    timeout_seconds?: number | null;
    webhook_url?: string | null;
    tags?: string[];
  };
  provider_config: WhatsAppProviderConfigView;
  provider_meta: Record<string, unknown>;
  created_by: number | null;
  updated_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface WhatsAppInstancesResponse extends PaginatedResponse<WhatsAppInstanceItem> {}

export interface WhatsAppListFilters {
  search?: string;
  provider_key?: WhatsAppProviderKey;
  status?: WhatsAppInstanceStatus;
  is_default?: boolean;
  is_active?: boolean;
  page?: number;
  per_page?: number;
}

export interface WhatsAppInstanceSettingsPayload {
  timeout_seconds?: number | null;
  webhook_url?: string | null;
  tags?: string[];
}

export interface WhatsAppZApiConfigPayload {
  instance_id: string;
  instance_token?: string;
  client_token?: string | null;
  base_url?: string | null;
}

export interface WhatsAppEvolutionConfigPayload {
  server_url: string;
  auth_type: 'global_apikey' | 'instance_apikey';
  api_key?: string;
  integration: 'WHATSAPP-BAILEYS' | 'WHATSAPP-BUSINESS';
  external_instance_name: string;
  instance_token?: string | null;
  phone_e164?: string | null;
}

export interface WhatsAppInstanceFormPayload {
  provider_key: WhatsAppProviderKey;
  name: string;
  instance_name: string;
  phone_number?: string | null;
  is_active: boolean;
  is_default?: boolean;
  notes?: string | null;
  settings?: WhatsAppInstanceSettingsPayload;
  provider_config: WhatsAppZApiConfigPayload | WhatsAppEvolutionConfigPayload;
}

export interface WhatsAppTestConnectionResult {
  success: boolean;
  connected: boolean;
  status: string;
  message: string | null;
  error: string | null;
  checked_at: string | null;
  instance: WhatsAppInstanceItem;
}

export interface WhatsAppConnectionState {
  provider: WhatsAppProviderKey;
  connected: boolean;
  checked_at: string;
  connection_source: string;
  instance_status: WhatsAppInstanceStatus;
  smartphone_connected: boolean;
  status_message: string | null;
  phone: string | null;
  formatted_phone: string | null;
  qr_code: string | null;
  qr_render_mode: 'image' | 'value' | 'bytes' | null;
  qr_available: boolean;
  qr_expires_in_sec: number | null;
  qr_error: string | null;
  profile: {
    lid: string | null;
    name: string | null;
    about: string | null;
    img_url: string | null;
    is_business: boolean;
  };
  device: {
    session_id: string | number | null;
    session_name: string | null;
    device_model: string | null;
    original_device: string | null;
  };
  device_error: string | null;
  last_status_sync_at: string | null;
  last_health_check_at: string | null;
  last_health_status: string | null;
  last_error: string | null;
}

export interface WhatsAppRemoteChat {
  id?: string | null;
  remoteJid?: string | null;
  jid?: string | null;
  name?: string | null;
  pushName?: string | null;
  formattedName?: string | null;
  isGroup?: boolean;
  unreadCount?: number | null;
  conversationTimestamp?: number | string | null;
  lastMessageTime?: number | string | null;
  raw?: Record<string, unknown>;
  [key: string]: unknown;
}

export interface WhatsAppRemoteChatsResponse {
  chats: WhatsAppRemoteChat[];
  page: number;
  page_size: number;
}

export interface WhatsAppRemoteParticipant {
  id: string | null;
  admin: string | null;
  name: string | null;
  notify: string | null;
  raw: Record<string, unknown>;
}

export interface WhatsAppRemoteGroup {
  id: string | null;
  subject: string | null;
  description: string | null;
  owner: string | null;
  size: number | null;
  announce: boolean | null;
  restrict: boolean | null;
  creation: number | string | null;
  invite_code: string | null;
  participants_count: number;
  participants: WhatsAppRemoteParticipant[];
  raw: Record<string, unknown>;
}

export interface WhatsAppRemoteGroupsResponse {
  includes_participants: boolean;
  groups: WhatsAppRemoteGroup[];
}

export interface WhatsAppRemoteGroupParticipantsResponse {
  group_id: string | null;
  participants: WhatsAppRemoteParticipant[];
}

export interface WhatsAppRemoteMessage {
  id: string | null;
  remote_jid: string | null;
  from_me: boolean;
  push_name: string | null;
  timestamp: number | string | null;
  message: Record<string, unknown> | null;
  raw: Record<string, unknown>;
}

export interface WhatsAppRemoteMessagesResponse {
  remote_jid: string | null;
  messages: WhatsAppRemoteMessage[];
}

export interface WhatsAppInvitationLinkResponse {
  success: boolean;
  invitation_link: string | null;
}

export const WHATSAPP_PROVIDER_OPTIONS: Array<{ value: WhatsAppProviderKey; label: string }> = [
  { value: 'zapi', label: 'Z-API' },
  { value: 'evolution', label: 'Evolution API' },
];

export const WHATSAPP_STATUS_LABELS: Record<WhatsAppInstanceStatus, string> = {
  draft: 'Rascunho',
  configured: 'Configurada',
  connected: 'Conectada',
  disconnected: 'Desconectada',
  invalid_credentials: 'Credenciais invalidas',
  error: 'Erro',
};

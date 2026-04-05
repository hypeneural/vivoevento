import { api } from '@/lib/api';

import type {
  WhatsAppConnectionState,
  WhatsAppInvitationLinkResponse,
  WhatsAppInstanceFormPayload,
  WhatsAppInstanceItem,
  WhatsAppListFilters,
  WhatsAppRemoteChatsResponse,
  WhatsAppRemoteGroupParticipantsResponse,
  WhatsAppRemoteGroupsResponse,
  WhatsAppRemoteMessagesResponse,
  WhatsAppTestConnectionResult,
  WhatsAppInstancesResponse,
} from './types';

export const whatsappService = {
  list(filters: WhatsAppListFilters = {}) {
    return api.getRaw<WhatsAppInstancesResponse>('/whatsapp/instances', {
      params: filters,
    });
  },

  get(instanceId: number | string) {
    return api.get<WhatsAppInstanceItem>(`/whatsapp/instances/${instanceId}`);
  },

  create(payload: WhatsAppInstanceFormPayload) {
    return api.post<WhatsAppInstanceItem>('/whatsapp/instances', {
      body: payload,
    });
  },

  update(instanceId: number | string, payload: Partial<WhatsAppInstanceFormPayload>) {
    return api.patch<WhatsAppInstanceItem>(`/whatsapp/instances/${instanceId}`, {
      body: payload,
    });
  },

  remove(instanceId: number | string) {
    return api.delete(`/whatsapp/instances/${instanceId}`);
  },

  testConnection(instanceId: number | string) {
    return api.post<WhatsAppTestConnectionResult>(`/whatsapp/instances/${instanceId}/test-connection`);
  },

  setDefault(instanceId: number | string) {
    return api.post<WhatsAppInstanceItem>(`/whatsapp/instances/${instanceId}/set-default`);
  },

  getConnectionState(instanceId: number | string) {
    return api.get<WhatsAppConnectionState>(`/whatsapp/instances/${instanceId}/connection-state`);
  },

  requestPhoneCode(instanceId: number | string, phone: string) {
    return api.post<{ success: boolean; message: string | null; raw: Record<string, unknown> }>(
      `/whatsapp/instances/${instanceId}/phone-code`,
      {
        body: { phone },
      },
    );
  },

  disconnect(instanceId: number | string) {
    return api.post<{ success: boolean; message: string | null }>(`/whatsapp/instances/${instanceId}/disconnect`);
  },

  getRemoteChats(instanceId: number | string, pageSize = 50) {
    return api.get<WhatsAppRemoteChatsResponse>('/whatsapp/chats', {
      params: {
        instance_id: instanceId,
        page_size: pageSize,
      },
    });
  },

  findRemoteMessages(
    instanceId: number | string,
    remoteJid: string,
    filters: {
      limit?: number;
      before_message_id?: string;
      from_me?: boolean;
    } = {},
  ) {
    return api.post<WhatsAppRemoteMessagesResponse>('/whatsapp/chats/find-messages', {
      body: {
        instance_id: instanceId,
        remote_jid: remoteJid,
        ...filters,
      },
    });
  },

  getRemoteGroups(instanceId: number | string, includeParticipants = false) {
    return api.get<WhatsAppRemoteGroupsResponse>('/whatsapp/group-management/catalog', {
      params: {
        instance_id: instanceId,
        include_participants: includeParticipants,
      },
    });
  },

  getRemoteGroupParticipants(instanceId: number | string, groupId: string) {
    return api.get<WhatsAppRemoteGroupParticipantsResponse>(`/whatsapp/group-management/${encodeURIComponent(groupId)}/participants`, {
      params: {
        instance_id: instanceId,
      },
    });
  },

  getInvitationLink(instanceId: number | string, groupId: string) {
    return api.get<WhatsAppInvitationLinkResponse>(`/whatsapp/group-management/${encodeURIComponent(groupId)}/invitation-link`, {
      params: {
        instance_id: instanceId,
      },
    });
  },
};

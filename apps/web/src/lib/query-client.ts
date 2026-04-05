/**
 * Query Client — Configuração global do TanStack Query.
 *
 * Define defaults de cache, staleTime, retry, e integração
 * com o tratamento de erros da API.
 */

import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Cache por 5 minutos por padrão
      staleTime: 5 * 60 * 1000,
      // Cache disponível por 10 minutos mesmo stale
      gcTime: 10 * 60 * 1000,
      // Retry apenas 1 vez, não retry em 401/403
      retry: (failureCount, error: any) => {
        if (error?.status === 401 || error?.status === 403) return false;
        return failureCount < 1;
      },
      // Não refetch ao focar a janela (evita spam)
      refetchOnWindowFocus: false,
      // Não refetch ao reconectar automaticamente
      refetchOnReconnect: true,
    },
    mutations: {
      retry: false,
    },
  },
});

// ─── Query Key Factories ───────────────────────────────────
// Pattern: [module, ...params] — garante cache isolado por módulo

export const queryKeys = {
  // Auth
  auth: {
    me: () => ['auth', 'me'] as const,
  },

  // Events
  events: {
    all: () => ['events'] as const,
    lists: () => [...queryKeys.events.all(), 'list'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.events.lists(), filters] as const,
    details: () => [...queryKeys.events.all(), 'detail'] as const,
    detail: (id: string) => [...queryKeys.events.details(), id] as const,
  },

  // Media
  media: {
    all: () => ['media'] as const,
    lists: () => [...queryKeys.media.all(), 'list'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.media.lists(), filters] as const,
    feeds: () => [...queryKeys.media.all(), 'feed'] as const,
    feed: (filters: Record<string, unknown>) => [...queryKeys.media.feeds(), filters] as const,
    detail: (id: string) => [...queryKeys.media.all(), 'detail', id] as const,
  },

  // Gallery
  gallery: {
    all: () => ['gallery'] as const,
    lists: () => [...queryKeys.gallery.all(), 'list'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.gallery.lists(), filters] as const,
    details: () => [...queryKeys.gallery.all(), 'detail'] as const,
    detail: (id: string) => [...queryKeys.gallery.details(), id] as const,
    byEvent: (eventId: string) => [...queryKeys.gallery.all(), eventId] as const,
  },

  // Wall
  wall: {
    all: () => ['wall'] as const,
    byEvent: (eventId: string) => [...queryKeys.wall.all(), eventId] as const,
    diagnostics: (eventId: string) => [...queryKeys.wall.byEvent(eventId), 'diagnostics'] as const,
    simulation: (eventId: string, fingerprint: string) => [...queryKeys.wall.byEvent(eventId), 'simulation', fingerprint] as const,
  },

  // Organizations
  organizations: {
    all: () => ['organizations'] as const,
    detail: (id: string) => [...queryKeys.organizations.all(), id] as const,
    members: (id: string) => [...queryKeys.organizations.all(), id, 'members'] as const,
  },

  // Partners
  partners: {
    all: () => ['partners'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.partners.all(), 'list', filters] as const,
    detail: (id: string) => [...queryKeys.partners.all(), id] as const,
  },

  // Clients
  clients: {
    all: () => ['clients'] as const,
    lists: () => [...queryKeys.clients.all(), 'list'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.clients.lists(), filters] as const,
  },

  // WhatsApp
  whatsapp: {
    all: () => ['whatsapp'] as const,
    lists: () => [...queryKeys.whatsapp.all(), 'list'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.whatsapp.lists(), filters] as const,
    details: () => [...queryKeys.whatsapp.all(), 'detail'] as const,
    detail: (id: number | string) => [...queryKeys.whatsapp.details(), id] as const,
    connection: (id: number | string) => [...queryKeys.whatsapp.detail(id), 'connection'] as const,
    remoteChats: (id: number | string, pageSize = 50) => [...queryKeys.whatsapp.detail(id), 'remote-chats', pageSize] as const,
    remoteMessages: (id: number | string, remoteJid: string, limit = 30) => [...queryKeys.whatsapp.detail(id), 'remote-messages', remoteJid, limit] as const,
    remoteGroups: (id: number | string, includeParticipants = false) => [...queryKeys.whatsapp.detail(id), 'remote-groups', includeParticipants] as const,
    remoteParticipants: (id: number | string, groupId: string) => [...queryKeys.whatsapp.detail(id), 'remote-participants', groupId] as const,
    invitation: (id: number | string, groupId: string) => [...queryKeys.whatsapp.detail(id), 'invitation-link', groupId] as const,
  },

  // Analytics
  analytics: {
    dashboard: () => ['analytics', 'dashboard'] as const,
    platform: (filters: Record<string, unknown>) => ['analytics', 'platform', filters] as const,
    event: (eventId: string | number, filters: Record<string, unknown>) => ['analytics', 'event', eventId, filters] as const,
    options: (entity: string, filters: Record<string, unknown>) => ['analytics', 'options', entity, filters] as const,
    byEvent: (eventId: string) => ['analytics', 'event', eventId] as const,
  },

  // Plans & Billing
  plans: {
    all: () => ['plans'] as const,
    billing: () => ['billing'] as const,
  },

  // Notifications
  notifications: {
    all: () => ['notifications'] as const,
    unread: () => ['notifications', 'unread'] as const,
  },

  // Audit
  audit: {
    all: () => ['audit'] as const,
    list: (filters: Record<string, unknown>) => [...queryKeys.audit.all(), 'list', filters] as const,
    filters: (organizationId?: number | null) => [...queryKeys.audit.all(), 'filters', organizationId ?? 'default'] as const,
  },
} as const;

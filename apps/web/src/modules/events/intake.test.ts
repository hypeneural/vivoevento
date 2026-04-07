import { describe, expect, it } from 'vitest';

import {
  buildDefaultIntakeChannels,
  buildDefaultIntakeBlacklist,
  buildDefaultIntakeDefaults,
  buildEventIntakeFromDetail,
  resolveEventIntakeEntitlements,
} from './intake';

describe('events intake helpers', () => {
  it('builds safe defaults for intake state', () => {
    expect(buildDefaultIntakeDefaults()).toEqual({
      whatsapp_instance_id: null,
      whatsapp_instance_mode: 'shared',
    });

    expect(buildDefaultIntakeChannels()).toEqual({
      whatsapp_groups: {
        enabled: false,
        groups: [],
      },
      whatsapp_direct: {
        enabled: false,
        media_inbox_code: null,
        session_ttl_minutes: 120,
      },
      public_upload: {
        enabled: false,
      },
      telegram: {
        enabled: false,
        bot_username: null,
        media_inbox_code: null,
        session_ttl_minutes: 180,
      },
    });

    expect(buildDefaultIntakeBlacklist()).toEqual({
      enabled: false,
      entries: [],
      senders: [],
    });
  });

  it('resolves channel entitlements from the event snapshot', () => {
    const entitlements = resolveEventIntakeEntitlements({
      channels: {
        whatsapp_groups: {
          enabled: true,
          max: 3,
        },
        whatsapp_direct: {
          enabled: true,
        },
        public_upload: {
          enabled: true,
        },
        telegram: {
          enabled: false,
        },
        blacklist: {
          enabled: true,
        },
        whatsapp: {
          shared_instance: {
            enabled: true,
          },
          dedicated_instance: {
            enabled: true,
            max_per_event: 1,
          },
          feedback: {
            reject_reply: {
              enabled: true,
              message: 'Sua midia nao segue as diretrizes do evento. 🛡️',
            },
          },
        },
      },
    });

    expect(entitlements).toEqual({
      whatsappGroupsEnabled: true,
      maxWhatsappGroups: 3,
      whatsappDirectEnabled: true,
      publicUploadEnabled: true,
      telegramEnabled: false,
      blacklistEnabled: true,
      sharedInstanceEnabled: true,
      dedicatedInstanceEnabled: true,
      dedicatedInstanceMaxPerEvent: 1,
      rejectReplyEnabled: true,
      rejectReplyMessage: 'Sua midia nao segue as diretrizes do evento. 🛡️',
    });
  });

  it('falls back to the legacy whatsapp channel flag', () => {
    const entitlements = resolveEventIntakeEntitlements({
      channels: {
        whatsapp: 'true',
      },
    });

    expect(entitlements.whatsappGroupsEnabled).toBe(true);
    expect(entitlements.whatsappDirectEnabled).toBe(true);
    expect(entitlements.sharedInstanceEnabled).toBe(true);
    expect(entitlements.publicUploadEnabled).toBe(false);
  });

  it('builds intake state from event detail without dropping saved values', () => {
    expect(buildEventIntakeFromDetail({
      intake_defaults: {
        whatsapp_instance_id: 12,
        whatsapp_instance_mode: 'dedicated',
      },
      intake_channels: {
        whatsapp_groups: {
          enabled: true,
          groups: [
            {
              group_external_id: 'grupo-1',
              group_name: 'Evento vivo 1',
              is_active: true,
              auto_feedback_enabled: true,
            },
          ],
        },
        whatsapp_direct: {
          enabled: true,
          media_inbox_code: 'ANAEJOAO',
          session_ttl_minutes: 180,
        },
        public_upload: {
          enabled: true,
        },
        telegram: {
          enabled: true,
          bot_username: 'eventovivoBot',
          media_inbox_code: 'TGTEST406',
          session_ttl_minutes: 180,
        },
      },
      intake_blacklist: {
        enabled: true,
        entries: [
          {
            id: 9,
            identity_type: 'lid',
            identity_value: '11111111111111@lid',
            normalized_phone: null,
            reason: 'Bloqueado manualmente',
            expires_at: '2026-04-06T15:00:00.000Z',
            is_active: true,
          },
        ],
        senders: [
          {
            sender_external_id: '11111111111111@lid',
            sender_phone: '554899999999',
            sender_lid: '11111111111111@lid',
            sender_name: 'Ana',
            sender_avatar_url: 'https://cdn.eventovivo.test/ana.jpg',
            inbound_count: 3,
            media_count: 2,
            last_seen_at: '2026-04-06T14:00:00.000Z',
            recommended_identity_type: 'lid',
            recommended_identity_value: '11111111111111@lid',
            recommended_normalized_phone: null,
            blocked: true,
            blocking_entry_id: 9,
            blocking_expires_at: '2026-04-06T15:00:00.000Z',
            blocking_reason: 'Bloqueado manualmente',
          },
        ],
      },
    })).toEqual({
      intake_defaults: {
        whatsapp_instance_id: 12,
        whatsapp_instance_mode: 'dedicated',
      },
      intake_channels: {
        whatsapp_groups: {
          enabled: true,
          groups: [
            {
              group_external_id: 'grupo-1',
              group_name: 'Evento vivo 1',
              is_active: true,
              auto_feedback_enabled: true,
            },
          ],
        },
        whatsapp_direct: {
          enabled: true,
          media_inbox_code: 'ANAEJOAO',
          session_ttl_minutes: 180,
        },
        public_upload: {
          enabled: true,
        },
        telegram: {
          enabled: true,
          bot_username: 'eventovivoBot',
          media_inbox_code: 'TGTEST406',
          session_ttl_minutes: 180,
        },
      },
      intake_blacklist: {
        enabled: true,
        entries: [
          {
            id: 9,
            identity_type: 'lid',
            identity_value: '11111111111111@lid',
            normalized_phone: null,
            reason: 'Bloqueado manualmente',
            expires_at: '2026-04-06T15:00:00.000Z',
            is_active: true,
          },
        ],
        senders: [
          {
            sender_external_id: '11111111111111@lid',
            sender_phone: '554899999999',
            sender_lid: '11111111111111@lid',
            sender_name: 'Ana',
            sender_avatar_url: 'https://cdn.eventovivo.test/ana.jpg',
            inbound_count: 3,
            media_count: 2,
            last_seen_at: '2026-04-06T14:00:00.000Z',
            recommended_identity_type: 'lid',
            recommended_identity_value: '11111111111111@lid',
            recommended_normalized_phone: null,
            blocked: true,
            blocking_entry_id: 9,
            blocking_expires_at: '2026-04-06T15:00:00.000Z',
            blocking_reason: 'Bloqueado manualmente',
          },
        ],
      },
    });
  });
});

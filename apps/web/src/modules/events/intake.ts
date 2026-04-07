export interface EventWhatsAppGroupInput {
  group_external_id: string;
  group_name?: string | null;
  is_active: boolean;
  auto_feedback_enabled: boolean;
}

export type EventBlacklistIdentityType = 'phone' | 'lid' | 'external_id';

export interface EventIntakeBlacklistEntry {
  id?: number | null;
  identity_type: EventBlacklistIdentityType;
  identity_value: string;
  normalized_phone?: string | null;
  reason?: string | null;
  expires_at?: string | null;
  is_active: boolean;
  is_currently_blocking?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface EventIntakeBlacklistSenderSummary {
  sender_external_id: string | null;
  sender_phone: string | null;
  sender_lid: string | null;
  sender_name: string | null;
  sender_avatar_url: string | null;
  inbound_count: number;
  media_count: number;
  last_seen_at: string | null;
  recommended_identity_type: EventBlacklistIdentityType;
  recommended_identity_value: string;
  recommended_normalized_phone?: string | null;
  blocked: boolean;
  blocking_entry_id?: number | null;
  blocking_expires_at?: string | null;
  blocking_reason?: string | null;
}

export interface EventIntakeBlacklist {
  enabled: boolean;
  entries: EventIntakeBlacklistEntry[];
  senders: EventIntakeBlacklistSenderSummary[];
}

export interface EventIntakeDefaults {
  whatsapp_instance_id: number | null;
  whatsapp_instance_mode: 'shared' | 'dedicated';
}

export interface EventIntakeChannels {
  whatsapp_groups: {
    enabled: boolean;
    groups: EventWhatsAppGroupInput[];
  };
  whatsapp_direct: {
    enabled: boolean;
    media_inbox_code: string | null;
    session_ttl_minutes: number | null;
  };
  public_upload: {
    enabled: boolean;
  };
  telegram: {
    enabled: boolean;
    bot_username: string | null;
    media_inbox_code: string | null;
    session_ttl_minutes: number | null;
  };
}

export interface EventIntakeState {
  intake_defaults: EventIntakeDefaults;
  intake_channels: EventIntakeChannels;
  intake_blacklist: EventIntakeBlacklist;
}

export interface EventIntakeEntitlements {
  whatsappGroupsEnabled: boolean;
  maxWhatsappGroups: number | null;
  whatsappDirectEnabled: boolean;
  publicUploadEnabled: boolean;
  telegramEnabled: boolean;
  blacklistEnabled: boolean;
  sharedInstanceEnabled: boolean;
  dedicatedInstanceEnabled: boolean;
  dedicatedInstanceMaxPerEvent: number | null;
  rejectReplyEnabled: boolean;
  rejectReplyMessage: string | null;
}

const DEFAULT_INTAKE_DEFAULTS: EventIntakeDefaults = {
  whatsapp_instance_id: null,
  whatsapp_instance_mode: 'shared',
};

const DEFAULT_INTAKE_CHANNELS: EventIntakeChannels = {
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
};

const DEFAULT_INTAKE_BLACKLIST: EventIntakeBlacklist = {
  enabled: false,
  entries: [],
  senders: [],
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? value as Record<string, unknown>
    : {};
}

function readPath(source: unknown, path: string[]): unknown {
  let current: unknown = source;

  for (const key of path) {
    current = asRecord(current)[key];
  }

  return current;
}

function coerceBoolean(value: unknown, fallback = false): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'string') {
    if (value === 'true') {
      return true;
    }

    if (value === 'false') {
      return false;
    }
  }

  if (typeof value === 'number') {
    return value !== 0;
  }

  return fallback;
}

function coerceNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function coerceString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== ''
    ? value
    : null;
}

export function buildDefaultIntakeDefaults(): EventIntakeDefaults {
  return {
    ...DEFAULT_INTAKE_DEFAULTS,
  };
}

export function buildDefaultIntakeChannels(): EventIntakeChannels {
  return {
    whatsapp_groups: {
      enabled: DEFAULT_INTAKE_CHANNELS.whatsapp_groups.enabled,
      groups: [],
    },
    whatsapp_direct: {
      ...DEFAULT_INTAKE_CHANNELS.whatsapp_direct,
    },
    public_upload: {
      ...DEFAULT_INTAKE_CHANNELS.public_upload,
    },
    telegram: {
      ...DEFAULT_INTAKE_CHANNELS.telegram,
    },
  };
}

export function buildDefaultIntakeBlacklist(): EventIntakeBlacklist {
  return {
    enabled: DEFAULT_INTAKE_BLACKLIST.enabled,
    entries: [],
    senders: [],
  };
}

export function resolveEventIntakeEntitlements(raw: Record<string, unknown> | null | undefined): EventIntakeEntitlements {
  const channels = asRecord(asRecord(raw).channels);
  const legacyWhatsappEnabled = coerceBoolean(channels.whatsapp, false);

  return {
    whatsappGroupsEnabled: coerceBoolean(readPath(channels, ['whatsapp_groups', 'enabled']), legacyWhatsappEnabled),
    maxWhatsappGroups: coerceNumber(readPath(channels, ['whatsapp_groups', 'max'])),
    whatsappDirectEnabled: coerceBoolean(readPath(channels, ['whatsapp_direct', 'enabled']), legacyWhatsappEnabled),
    publicUploadEnabled: coerceBoolean(readPath(channels, ['public_upload', 'enabled']), false),
    telegramEnabled: coerceBoolean(readPath(channels, ['telegram', 'enabled']), false),
    blacklistEnabled: coerceBoolean(readPath(channels, ['blacklist', 'enabled']), false),
    sharedInstanceEnabled: coerceBoolean(readPath(channels, ['whatsapp', 'shared_instance', 'enabled']), legacyWhatsappEnabled),
    dedicatedInstanceEnabled: coerceBoolean(readPath(channels, ['whatsapp', 'dedicated_instance', 'enabled']), false),
    dedicatedInstanceMaxPerEvent: coerceNumber(readPath(channels, ['whatsapp', 'dedicated_instance', 'max_per_event'])),
    rejectReplyEnabled: coerceBoolean(readPath(channels, ['whatsapp', 'feedback', 'reject_reply', 'enabled']), false),
    rejectReplyMessage: coerceString(readPath(channels, ['whatsapp', 'feedback', 'reject_reply', 'message'])),
  };
}

export function buildEventIntakeFromDetail(detail?: {
  intake_defaults?: EventIntakeDefaults | null;
  intake_channels?: EventIntakeChannels | null;
  intake_blacklist?: EventIntakeBlacklist | null;
} | null): EventIntakeState {
  return {
    intake_defaults: {
      whatsapp_instance_id: detail?.intake_defaults?.whatsapp_instance_id ?? null,
      whatsapp_instance_mode: detail?.intake_defaults?.whatsapp_instance_mode ?? 'shared',
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: detail?.intake_channels?.whatsapp_groups?.enabled ?? false,
        groups: detail?.intake_channels?.whatsapp_groups?.groups ?? [],
      },
      whatsapp_direct: {
        enabled: detail?.intake_channels?.whatsapp_direct?.enabled ?? false,
        media_inbox_code: detail?.intake_channels?.whatsapp_direct?.media_inbox_code ?? null,
        session_ttl_minutes: detail?.intake_channels?.whatsapp_direct?.session_ttl_minutes ?? 120,
      },
      public_upload: {
        enabled: detail?.intake_channels?.public_upload?.enabled ?? false,
      },
      telegram: {
        enabled: detail?.intake_channels?.telegram?.enabled ?? false,
        bot_username: detail?.intake_channels?.telegram?.bot_username ?? null,
        media_inbox_code: detail?.intake_channels?.telegram?.media_inbox_code ?? null,
        session_ttl_minutes: detail?.intake_channels?.telegram?.session_ttl_minutes ?? 180,
      },
    },
    intake_blacklist: {
      enabled: detail?.intake_blacklist?.enabled ?? false,
      entries: detail?.intake_blacklist?.entries ?? [],
      senders: detail?.intake_blacklist?.senders ?? [],
    },
  };
}

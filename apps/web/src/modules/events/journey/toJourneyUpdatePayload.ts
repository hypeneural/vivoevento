import type {
  EventBlacklistIdentityType,
  EventIntakeBlacklist,
  EventWhatsAppGroupInput,
} from '../intake';
import type { EventModerationMode, EventModuleKey } from '../types';
import type {
  EventJourneyAnalysisScope,
  EventJourneyContentModerationPatch,
  EventJourneyMediaIntelligenceMode,
  EventJourneyMediaIntelligencePatch,
  EventJourneyModerationSafetyMode,
  EventJourneyReplyTextMode,
  EventJourneyTextContextMode,
  EventJourneyUpdatePayload,
} from './types';

type DateLike = Date | string | null | undefined;

export interface EventJourneyInspectorBlacklistEntry {
  id?: number | null;
  identity_type: EventBlacklistIdentityType;
  identity_value: string;
  normalized_phone?: string | null;
  reason?: string | null;
  expires_at?: DateLike;
  is_active: boolean;
}

export interface EventJourneyInspectorContentModerationDraft {
  enabled: boolean;
  provider_key: 'openai' | 'noop' | string;
  mode: EventJourneyModerationSafetyMode;
  threshold_version: string | null;
  fallback_mode: 'review' | 'block' | string;
  analysis_scope?: EventJourneyAnalysisScope;
  normalized_text_context_mode?: EventJourneyTextContextMode;
  hard_block_thresholds: Record<'nudity' | 'violence' | 'self_harm', string | number>;
  review_thresholds: Record<'nudity' | 'violence' | 'self_harm', string | number>;
}

export interface EventJourneyInspectorMediaIntelligenceDraft {
  enabled: boolean;
  provider_key: 'vllm' | 'openrouter' | 'noop' | string;
  model_key: string;
  mode: EventJourneyMediaIntelligenceMode;
  prompt_version: string | null;
  approval_prompt: string | null;
  caption_style_prompt: string | null;
  response_schema_version: string | null;
  timeout_ms: string | number;
  fallback_mode: 'review' | 'skip' | string;
  require_json_output: boolean;
  reply_text_enabled: boolean;
  reply_text_mode: EventJourneyReplyTextMode;
  reply_prompt_override: string | null;
  reply_fixed_templates_text: string;
  reply_prompt_preset_id: string | number | null;
}

export interface EventJourneyInspectorDraft {
  moderation_mode: EventModerationMode | null;
  modules: Record<EventModuleKey, boolean>;
  intake_defaults: {
    whatsapp_instance_id: string | number | null;
    whatsapp_instance_mode: 'shared' | 'dedicated';
  };
  intake_channels: {
    whatsapp_groups: {
      enabled: boolean;
      groups: EventWhatsAppGroupInput[];
    };
    whatsapp_direct: {
      enabled: boolean;
      media_inbox_code: string | null;
      session_ttl_minutes: string | number | null;
    };
    public_upload: {
      enabled: boolean;
    };
    telegram: {
      enabled: boolean;
      bot_username: string | null;
      media_inbox_code: string | null;
      session_ttl_minutes: string | number | null;
    };
  };
  intake_blacklist: Pick<EventIntakeBlacklist, 'entries'> & {
    entries: EventJourneyInspectorBlacklistEntry[];
  };
  content_moderation: EventJourneyInspectorContentModerationDraft;
  media_intelligence: EventJourneyInspectorMediaIntelligenceDraft;
}

export type EventJourneyDirtyFields<T> =
  T extends readonly (infer U)[]
    ? boolean | Array<EventJourneyDirtyFields<U> | boolean | undefined>
    : T extends object
      ? { [K in keyof T]?: EventJourneyDirtyFields<T[K]> | boolean }
      : boolean;

function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function hasDirtyField(value: unknown): boolean {
  if (value === true) {
    return true;
  }

  if (Array.isArray(value)) {
    return value.some((item) => hasDirtyField(item));
  }

  if (isRecord(value)) {
    return Object.values(value).some((item) => hasDirtyField(item));
  }

  return false;
}

function normalizeString(value: string | null | undefined) {
  if (typeof value !== 'string') {
    return null;
  }

  const trimmed = value.trim();

  return trimmed.length > 0 ? trimmed : null;
}

function normalizeNumber(value: string | number | null | undefined) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();

    if (trimmed.length === 0) {
      return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function normalizeSelectInteger(value: string | number | null | undefined) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();

    if (trimmed.length === 0 || trimmed === 'none') {
      return null;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function normalizeTemplates(value: string) {
  return value
    .split(/\r?\n/u)
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function normalizeDateLike(value: DateLike) {
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value.toISOString();
  }

  return normalizeString(value);
}

function setNestedThresholdValue(
  target: Partial<Record<'nudity' | 'violence' | 'self_harm', number>>,
  key: 'nudity' | 'violence' | 'self_harm',
  source: Record<'nudity' | 'violence' | 'self_harm', string | number>,
) {
  const normalized = normalizeNumber(source[key]);

  if (normalized !== null) {
    target[key] = normalized;
  }
}

function buildModulesPatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['modules'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean') {
    return undefined;
  }

  const patch: Partial<Record<EventModuleKey, boolean>> = {};

  (Object.keys(draft.modules) as EventModuleKey[]).forEach((moduleKey) => {
    if (hasDirtyField(dirtyFields[moduleKey])) {
      patch[moduleKey] = draft.modules[moduleKey];
    }
  });

  return Object.keys(patch).length > 0 ? patch : undefined;
}

function buildIntakeDefaultsPatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['intake_defaults'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean') {
    return undefined;
  }

  const patch: EventJourneyUpdatePayload['intake_defaults'] = {};

  if (hasDirtyField(dirtyFields.whatsapp_instance_id)) {
    patch.whatsapp_instance_id = normalizeSelectInteger(draft.intake_defaults.whatsapp_instance_id);
  }

  if (hasDirtyField(dirtyFields.whatsapp_instance_mode)) {
    patch.whatsapp_instance_mode = draft.intake_defaults.whatsapp_instance_mode;
  }

  return Object.keys(patch).length > 0 ? patch : undefined;
}

function buildIntakeChannelsPatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['intake_channels'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean') {
    return undefined;
  }

  const patch: NonNullable<EventJourneyUpdatePayload['intake_channels']> = {};

  if (isRecord(dirtyFields.whatsapp_groups)) {
    const groupsPatch: NonNullable<NonNullable<EventJourneyUpdatePayload['intake_channels']>['whatsapp_groups']> = {};

    if (hasDirtyField(dirtyFields.whatsapp_groups.enabled)) {
      groupsPatch.enabled = draft.intake_channels.whatsapp_groups.enabled;
    }

    if (hasDirtyField(dirtyFields.whatsapp_groups.groups)) {
      groupsPatch.groups = draft.intake_channels.whatsapp_groups.groups
        .map((group) => ({
          group_external_id: group.group_external_id.trim(),
          group_name: normalizeString(group.group_name ?? null),
          is_active: group.is_active,
          auto_feedback_enabled: group.auto_feedback_enabled,
        }))
        .filter((group) => group.group_external_id.length > 0);
    }

    if (Object.keys(groupsPatch).length > 0) {
      patch.whatsapp_groups = groupsPatch;
    }
  }

  if (isRecord(dirtyFields.whatsapp_direct)) {
    const directPatch: NonNullable<NonNullable<EventJourneyUpdatePayload['intake_channels']>['whatsapp_direct']> = {};

    if (hasDirtyField(dirtyFields.whatsapp_direct.enabled)) {
      directPatch.enabled = draft.intake_channels.whatsapp_direct.enabled;
    }

    if (hasDirtyField(dirtyFields.whatsapp_direct.media_inbox_code)) {
      directPatch.media_inbox_code = normalizeString(draft.intake_channels.whatsapp_direct.media_inbox_code);
    }

    if (hasDirtyField(dirtyFields.whatsapp_direct.session_ttl_minutes)) {
      directPatch.session_ttl_minutes = normalizeNumber(draft.intake_channels.whatsapp_direct.session_ttl_minutes);
    }

    if (Object.keys(directPatch).length > 0) {
      patch.whatsapp_direct = directPatch;
    }
  }

  if (isRecord(dirtyFields.public_upload)) {
    const uploadPatch: NonNullable<NonNullable<EventJourneyUpdatePayload['intake_channels']>['public_upload']> = {};

    if (hasDirtyField(dirtyFields.public_upload.enabled)) {
      uploadPatch.enabled = draft.intake_channels.public_upload.enabled;
    }

    if (Object.keys(uploadPatch).length > 0) {
      patch.public_upload = uploadPatch;
    }
  }

  if (isRecord(dirtyFields.telegram)) {
    const telegramPatch: NonNullable<NonNullable<EventJourneyUpdatePayload['intake_channels']>['telegram']> = {};

    if (hasDirtyField(dirtyFields.telegram.enabled)) {
      telegramPatch.enabled = draft.intake_channels.telegram.enabled;
    }

    if (hasDirtyField(dirtyFields.telegram.bot_username)) {
      telegramPatch.bot_username = normalizeString(draft.intake_channels.telegram.bot_username);
    }

    if (hasDirtyField(dirtyFields.telegram.media_inbox_code)) {
      telegramPatch.media_inbox_code = normalizeString(draft.intake_channels.telegram.media_inbox_code);
    }

    if (hasDirtyField(dirtyFields.telegram.session_ttl_minutes)) {
      telegramPatch.session_ttl_minutes = normalizeNumber(draft.intake_channels.telegram.session_ttl_minutes);
    }

    if (Object.keys(telegramPatch).length > 0) {
      patch.telegram = telegramPatch;
    }
  }

  return Object.keys(patch).length > 0 ? patch : undefined;
}

function buildIntakeBlacklistPatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['intake_blacklist'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean' || !hasDirtyField(dirtyFields.entries)) {
    return undefined;
  }

  return {
    entries: draft.intake_blacklist.entries
      .map((entry) => ({
        id: entry.id ?? null,
        identity_type: entry.identity_type,
        identity_value: entry.identity_value.trim(),
        normalized_phone: normalizeString(entry.normalized_phone ?? null),
        reason: normalizeString(entry.reason ?? null),
        expires_at: normalizeDateLike(entry.expires_at),
        is_active: entry.is_active,
      }))
      .filter((entry) => entry.identity_value.length > 0),
  };
}

function buildContentModerationPatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['content_moderation'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean') {
    return undefined;
  }

  const patch: EventJourneyContentModerationPatch = {};

  if (hasDirtyField(dirtyFields.enabled)) {
    patch.enabled = draft.content_moderation.enabled;
  }

  if (hasDirtyField(dirtyFields.provider_key)) {
    patch.provider_key = draft.content_moderation.provider_key;
  }

  if (hasDirtyField(dirtyFields.mode)) {
    patch.mode = draft.content_moderation.mode;
  }

  if (hasDirtyField(dirtyFields.threshold_version)) {
    patch.threshold_version = normalizeString(draft.content_moderation.threshold_version);
  }

  if (hasDirtyField(dirtyFields.fallback_mode)) {
    patch.fallback_mode = draft.content_moderation.fallback_mode;
  }

  if (hasDirtyField(dirtyFields.analysis_scope)) {
    patch.analysis_scope = draft.content_moderation.analysis_scope;
  }

  if (hasDirtyField(dirtyFields.normalized_text_context_mode)) {
    patch.normalized_text_context_mode = draft.content_moderation.normalized_text_context_mode;
  }

  if (isRecord(dirtyFields.hard_block_thresholds)) {
    const thresholdPatch: Partial<Record<'nudity' | 'violence' | 'self_harm', number>> = {};

    (['nudity', 'violence', 'self_harm'] as const).forEach((key) => {
      if (hasDirtyField(dirtyFields.hard_block_thresholds?.[key])) {
        setNestedThresholdValue(thresholdPatch, key, draft.content_moderation.hard_block_thresholds);
      }
    });

    if (Object.keys(thresholdPatch).length > 0) {
      patch.hard_block_thresholds = thresholdPatch;
    }
  }

  if (isRecord(dirtyFields.review_thresholds)) {
    const thresholdPatch: Partial<Record<'nudity' | 'violence' | 'self_harm', number>> = {};

    (['nudity', 'violence', 'self_harm'] as const).forEach((key) => {
      if (hasDirtyField(dirtyFields.review_thresholds?.[key])) {
        setNestedThresholdValue(thresholdPatch, key, draft.content_moderation.review_thresholds);
      }
    });

    if (Object.keys(thresholdPatch).length > 0) {
      patch.review_thresholds = thresholdPatch;
    }
  }

  return Object.keys(patch).length > 0 ? patch : undefined;
}

function buildMediaIntelligencePatch(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>['media_intelligence'],
) {
  if (!dirtyFields || typeof dirtyFields === 'boolean') {
    return undefined;
  }

  const patch: EventJourneyMediaIntelligencePatch = {};

  if (hasDirtyField(dirtyFields.enabled)) {
    patch.enabled = draft.media_intelligence.enabled;
  }

  if (hasDirtyField(dirtyFields.provider_key)) {
    patch.provider_key = draft.media_intelligence.provider_key;
  }

  if (hasDirtyField(dirtyFields.model_key)) {
    patch.model_key = normalizeString(draft.media_intelligence.model_key) ?? '';
  }

  if (hasDirtyField(dirtyFields.mode)) {
    patch.mode = draft.media_intelligence.mode;
  }

  if (hasDirtyField(dirtyFields.prompt_version)) {
    patch.prompt_version = normalizeString(draft.media_intelligence.prompt_version);
  }

  if (hasDirtyField(dirtyFields.approval_prompt)) {
    patch.approval_prompt = normalizeString(draft.media_intelligence.approval_prompt);
  }

  if (hasDirtyField(dirtyFields.caption_style_prompt)) {
    patch.caption_style_prompt = normalizeString(draft.media_intelligence.caption_style_prompt);
  }

  if (hasDirtyField(dirtyFields.response_schema_version)) {
    patch.response_schema_version = normalizeString(draft.media_intelligence.response_schema_version);
  }

  if (hasDirtyField(dirtyFields.timeout_ms)) {
    patch.timeout_ms = normalizeNumber(draft.media_intelligence.timeout_ms) ?? 0;
  }

  if (hasDirtyField(dirtyFields.fallback_mode)) {
    patch.fallback_mode = draft.media_intelligence.fallback_mode;
  }

  if (hasDirtyField(dirtyFields.require_json_output)) {
    patch.require_json_output = draft.media_intelligence.require_json_output;
  }

  if (hasDirtyField(dirtyFields.reply_text_enabled)) {
    patch.reply_text_enabled = draft.media_intelligence.reply_text_enabled;
  }

  const replyBlockDirty = hasDirtyField(dirtyFields.reply_text_mode)
    || hasDirtyField(dirtyFields.reply_prompt_override)
    || hasDirtyField(dirtyFields.reply_fixed_templates_text)
    || hasDirtyField(dirtyFields.reply_prompt_preset_id)
    || hasDirtyField(dirtyFields.reply_text_enabled);

  if (replyBlockDirty) {
    patch.reply_text_mode = draft.media_intelligence.reply_text_mode;

    if (draft.media_intelligence.reply_text_mode === 'ai') {
      patch.reply_prompt_override = normalizeString(draft.media_intelligence.reply_prompt_override);
      patch.reply_prompt_preset_id = normalizeSelectInteger(draft.media_intelligence.reply_prompt_preset_id);
      patch.reply_fixed_templates = [];
    } else if (draft.media_intelligence.reply_text_mode === 'fixed_random') {
      patch.reply_prompt_override = null;
      patch.reply_prompt_preset_id = null;
      patch.reply_fixed_templates = normalizeTemplates(draft.media_intelligence.reply_fixed_templates_text);
    } else {
      patch.reply_prompt_override = null;
      patch.reply_prompt_preset_id = null;
      patch.reply_fixed_templates = [];
    }
  }

  return Object.keys(patch).length > 0 ? patch : undefined;
}

export function toJourneyUpdatePayload(
  draft: EventJourneyInspectorDraft,
  dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft>,
) {
  const payload: EventJourneyUpdatePayload = {};

  if (hasDirtyField(dirtyFields.moderation_mode)) {
    payload.moderation_mode = draft.moderation_mode;
  }

  const modules = buildModulesPatch(draft, dirtyFields.modules);

  if (modules) {
    payload.modules = modules;
  }

  const intakeDefaults = buildIntakeDefaultsPatch(draft, dirtyFields.intake_defaults);

  if (intakeDefaults) {
    payload.intake_defaults = intakeDefaults;
  }

  const intakeChannels = buildIntakeChannelsPatch(draft, dirtyFields.intake_channels);

  if (intakeChannels) {
    payload.intake_channels = intakeChannels;
  }

  const intakeBlacklist = buildIntakeBlacklistPatch(draft, dirtyFields.intake_blacklist);

  if (intakeBlacklist) {
    payload.intake_blacklist = intakeBlacklist;
  }

  const contentModeration = buildContentModerationPatch(draft, dirtyFields.content_moderation);

  if (contentModeration) {
    payload.content_moderation = contentModeration;
  }

  const mediaIntelligence = buildMediaIntelligencePatch(draft, dirtyFields.media_intelligence);

  if (mediaIntelligence) {
    payload.media_intelligence = mediaIntelligence;
  }

  return payload;
}

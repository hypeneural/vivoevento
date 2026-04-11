import type { EventJourneyProjection } from './types';
import type { EventJourneyInspectorDraft } from './toJourneyUpdatePayload';

function normalizeThresholdValue(value: unknown, fallback: string) {
  return typeof value === 'number' && Number.isFinite(value)
    ? String(value)
    : fallback;
}

export function buildJourneyInspectorDraft(projection: EventJourneyProjection): EventJourneyInspectorDraft {
  const contentModerationPreview = projection.settings.content_moderation;
  const mediaIntelligencePreview = projection.settings.media_intelligence;

  return {
    moderation_mode: projection.settings.moderation_mode ?? projection.event.moderation_mode ?? 'manual',
    modules: {
      ...projection.settings.modules,
    },
    intake_defaults: {
      whatsapp_instance_id: projection.intake_defaults.whatsapp_instance_id ?? 'none',
      whatsapp_instance_mode: projection.intake_defaults.whatsapp_instance_mode,
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: projection.intake_channels.whatsapp_groups.enabled,
        groups: projection.intake_channels.whatsapp_groups.groups.map((group) => ({
          group_external_id: group.group_external_id,
          group_name: group.group_name ?? null,
          is_active: group.is_active,
          auto_feedback_enabled: group.auto_feedback_enabled,
        })),
      },
      whatsapp_direct: {
        enabled: projection.intake_channels.whatsapp_direct.enabled,
        media_inbox_code: projection.intake_channels.whatsapp_direct.media_inbox_code ?? '',
        session_ttl_minutes: String(projection.intake_channels.whatsapp_direct.session_ttl_minutes ?? 120),
      },
      public_upload: {
        enabled: projection.intake_channels.public_upload.enabled,
      },
      telegram: {
        enabled: projection.intake_channels.telegram.enabled,
        bot_username: projection.intake_channels.telegram.bot_username ?? '',
        media_inbox_code: projection.intake_channels.telegram.media_inbox_code ?? '',
        session_ttl_minutes: String(projection.intake_channels.telegram.session_ttl_minutes ?? 180),
      },
    },
    intake_blacklist: {
      entries: [],
    },
    content_moderation: {
      enabled: contentModerationPreview.enabled,
      provider_key: contentModerationPreview.provider_key,
      mode: contentModerationPreview.mode ?? 'enforced',
      threshold_version: null,
      fallback_mode: contentModerationPreview.fallback_mode ?? 'review',
      analysis_scope: contentModerationPreview.analysis_scope ?? undefined,
      normalized_text_context_mode: contentModerationPreview.normalized_text_context_mode ?? undefined,
      hard_block_thresholds: {
        nudity: normalizeThresholdValue(undefined, '0.90'),
        violence: normalizeThresholdValue(undefined, '0.90'),
        self_harm: normalizeThresholdValue(undefined, '0.90'),
      },
      review_thresholds: {
        nudity: normalizeThresholdValue(undefined, '0.60'),
        violence: normalizeThresholdValue(undefined, '0.60'),
        self_harm: normalizeThresholdValue(undefined, '0.60'),
      },
    },
    media_intelligence: {
      enabled: mediaIntelligencePreview.enabled,
      provider_key: mediaIntelligencePreview.provider_key,
      model_key: mediaIntelligencePreview.model_key ?? '',
      mode: mediaIntelligencePreview.mode ?? 'enrich_only',
      prompt_version: null,
      approval_prompt: null,
      caption_style_prompt: null,
      response_schema_version: null,
      timeout_ms: 12000,
      fallback_mode: mediaIntelligencePreview.fallback_mode ?? 'review',
      require_json_output: true,
      reply_text_enabled: mediaIntelligencePreview.reply_text_enabled,
      reply_text_mode: mediaIntelligencePreview.reply_text_mode ?? 'disabled',
      reply_prompt_override: null,
      reply_fixed_templates_text: '',
      reply_prompt_preset_id: null,
    },
  };
}

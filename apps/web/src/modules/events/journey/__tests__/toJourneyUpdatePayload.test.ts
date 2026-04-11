import { describe, expect, it } from 'vitest';

import type { EventJourneyUpdatePayload } from '../types';
import {
  type EventJourneyDirtyFields,
  type EventJourneyInspectorDraft,
  toJourneyUpdatePayload,
} from '../toJourneyUpdatePayload';

function makeDraft(): EventJourneyInspectorDraft {
  return {
    moderation_mode: 'manual',
    modules: {
      live: true,
      wall: false,
      hub: true,
      play: false,
    },
    intake_defaults: {
      whatsapp_instance_id: 'none',
      whatsapp_instance_mode: 'shared',
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: true,
        groups: [
          {
            group_external_id: ' grupo-1 ',
            group_name: ' Familia ',
            is_active: true,
            auto_feedback_enabled: true,
          },
          {
            group_external_id: '   ',
            group_name: 'Ignorar',
            is_active: false,
            auto_feedback_enabled: false,
          },
        ],
      },
      whatsapp_direct: {
        enabled: true,
        media_inbox_code: ' NOIVA2026 ',
        session_ttl_minutes: '180',
      },
      public_upload: {
        enabled: true,
      },
      telegram: {
        enabled: true,
        bot_username: ' EventoVivoBot ',
        media_inbox_code: ' NOIVABOT ',
        session_ttl_minutes: '240',
      },
    },
    intake_blacklist: {
      entries: [
        {
          id: 10,
          identity_type: 'phone',
          identity_value: ' 5511999999999 ',
          normalized_phone: ' 5511999999999 ',
          reason: ' spam ',
          expires_at: new Date('2026-04-10T12:00:00.000Z'),
          is_active: true,
        },
        {
          id: null,
          identity_type: 'external_id',
          identity_value: '   ',
          normalized_phone: null,
          reason: null,
          expires_at: null,
          is_active: false,
        },
      ],
    },
    content_moderation: {
      enabled: true,
      provider_key: 'openai',
      mode: 'enforced',
      threshold_version: ' foundation-v1 ',
      fallback_mode: 'review',
      hard_block_thresholds: {
        nudity: '0.90',
        violence: '0.91',
        self_harm: '0.92',
      },
      review_thresholds: {
        nudity: '0.60',
        violence: '0.61',
        self_harm: '0.62',
      },
    },
    media_intelligence: {
      enabled: true,
      provider_key: 'vllm',
      model_key: ' Qwen/Qwen2.5-VL-3B-Instruct ',
      mode: 'gate',
      prompt_version: ' foundation-v1 ',
      approval_prompt: ' Avalie a imagem ',
      caption_style_prompt: ' Legenda curta ',
      response_schema_version: ' foundation-v1 ',
      timeout_ms: '12000',
      fallback_mode: 'review',
      require_json_output: true,
      reply_text_enabled: true,
      reply_text_mode: 'ai',
      reply_prompt_override: ' Obrigado por participar! ',
      reply_fixed_templates_text: 'Template A\nTemplate B',
      reply_prompt_preset_id: '42',
    },
  };
}

describe('toJourneyUpdatePayload', () => {
  it('returns an empty patch when nothing was touched in the inspector draft', () => {
    const payload = toJourneyUpdatePayload(makeDraft(), {});

    expect(payload).toEqual({});
  });

  it('emits only the dirty top-level fields for moderation mode and modules', () => {
    const draft = makeDraft();
    draft.moderation_mode = 'ai';
    draft.modules.wall = true;

    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      moderation_mode: true,
      modules: {
        wall: true,
      },
    };

    expect(toJourneyUpdatePayload(draft, dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      moderation_mode: 'ai',
      modules: {
        wall: true,
      },
    });
  });

  it('normalizes intake defaults and channel values without sending untouched channel branches', () => {
    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      intake_defaults: {
        whatsapp_instance_id: true,
        whatsapp_instance_mode: true,
      },
      intake_channels: {
        whatsapp_direct: {
          enabled: true,
          media_inbox_code: true,
          session_ttl_minutes: true,
        },
      },
    };

    expect(toJourneyUpdatePayload(makeDraft(), dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      intake_defaults: {
        whatsapp_instance_id: null,
        whatsapp_instance_mode: 'shared',
      },
      intake_channels: {
        whatsapp_direct: {
          enabled: true,
          media_inbox_code: 'NOIVA2026',
          session_ttl_minutes: 180,
        },
      },
    });
  });

  it('keeps content moderation payload minimal while coercing numeric threshold strings', () => {
    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      content_moderation: {
        threshold_version: true,
        review_thresholds: {
          violence: true,
        },
      },
    };

    expect(toJourneyUpdatePayload(makeDraft(), dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      content_moderation: {
        threshold_version: 'foundation-v1',
        review_thresholds: {
          violence: 0.61,
        },
      },
    });
  });

  it('cleans AI reply-only fields when the reply mode switches to fixed_random', () => {
    const draft = makeDraft();
    draft.media_intelligence.reply_text_mode = 'fixed_random';
    draft.media_intelligence.reply_prompt_override = 'Nao deve sair';
    draft.media_intelligence.reply_prompt_preset_id = 'none';
    draft.media_intelligence.reply_fixed_templates_text = '  Primeira  \n\n Segunda ';

    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      media_intelligence: {
        reply_text_mode: true,
        reply_fixed_templates_text: true,
      },
    };

    expect(toJourneyUpdatePayload(draft, dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      media_intelligence: {
        reply_text_mode: 'fixed_random',
        reply_prompt_override: null,
        reply_prompt_preset_id: null,
        reply_fixed_templates: ['Primeira', 'Segunda'],
      },
    });
  });

  it('cleans fixed templates when the reply mode stays in ai and preserves the preset normalization', () => {
    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      media_intelligence: {
        reply_text_mode: true,
        reply_prompt_override: true,
        reply_prompt_preset_id: true,
      },
    };

    expect(toJourneyUpdatePayload(makeDraft(), dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      media_intelligence: {
        reply_text_mode: 'ai',
        reply_prompt_override: 'Obrigado por participar!',
        reply_prompt_preset_id: 42,
        reply_fixed_templates: [],
      },
    });
  });

  it('treats dirty arrays as full replacements and normalizes blacklist and whatsapp groups collections', () => {
    const dirtyFields: EventJourneyDirtyFields<EventJourneyInspectorDraft> = {
      intake_channels: {
        whatsapp_groups: {
          groups: true,
        },
      },
      intake_blacklist: {
        entries: true,
      },
    };

    expect(toJourneyUpdatePayload(makeDraft(), dirtyFields)).toEqual<EventJourneyUpdatePayload>({
      intake_channels: {
        whatsapp_groups: {
          groups: [
            {
              group_external_id: 'grupo-1',
              group_name: 'Familia',
              is_active: true,
              auto_feedback_enabled: true,
            },
          ],
        },
      },
      intake_blacklist: {
        entries: [
          {
            id: 10,
            identity_type: 'phone',
            identity_value: '5511999999999',
            normalized_phone: '5511999999999',
            reason: 'spam',
            expires_at: '2026-04-10T12:00:00.000Z',
            is_active: true,
          },
        ],
      },
    });
  });
});

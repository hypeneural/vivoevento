import { describe, expect, it } from 'vitest';

import {
  buildJourneyTemplatePreview,
  mergeJourneyContentModerationSettings,
  mergeJourneyMediaIntelligenceSettings,
} from '../buildJourneyTemplatePreview';
import type { EventJourneyProjection } from '../types';

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 42,
      uuid: 'event-42',
      title: 'Casamento Ana e Pedro',
      status: 'active',
      moderation_mode: 'manual',
      modules: {
        live: true,
        wall: false,
        hub: true,
        play: false,
      },
    },
    intake_defaults: {
      whatsapp_instance_id: 7,
      whatsapp_instance_mode: 'shared',
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: false,
        groups: [],
      },
      whatsapp_direct: {
        enabled: true,
        media_inbox_code: 'ANA2026',
        session_ttl_minutes: 180,
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
    },
    settings: {
      moderation_mode: 'manual',
      modules: {
        live: true,
        wall: false,
        hub: true,
        play: false,
      },
      content_moderation: {
        enabled: false,
        mode: 'observe_only',
        fallback_mode: 'review',
        provider_key: 'openai',
        analysis_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      media_intelligence: {
        enabled: false,
        mode: 'enrich_only',
        fallback_mode: 'review',
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        reply_text_enabled: false,
        reply_text_mode: 'disabled',
        context_scope: 'image_and_text_context',
        reply_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      destinations: {
        gallery: true,
        wall: false,
        print: false,
      },
    },
    capabilities: {
      supports_manual_review: {
        id: 'supports_manual_review',
        label: 'Revisao manual',
        enabled: true,
        available: true,
        editable: true,
        reason: null,
        config_preview: {},
      },
      supports_ai_reply: {
        id: 'supports_ai_reply',
        label: 'Resposta por IA',
        enabled: false,
        available: true,
        editable: true,
        reason: null,
        config_preview: {},
      },
      supports_wall_output: {
        id: 'supports_wall_output',
        label: 'Telao',
        enabled: false,
        available: true,
        editable: true,
        reason: null,
        config_preview: {},
      },
    },
    stages: [
      {
        id: 'entry',
        label: 'Entrada',
        description: 'Entrada',
        position: 0,
        nodes: [
          {
            id: 'entry_whatsapp_direct',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp privado',
            description: 'Privado',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por codigo privado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_whatsapp_groups',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp grupos',
            description: 'Grupos',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'WhatsApp grupos desligado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_public_upload',
            stage: 'entry',
            kind: 'entry',
            label: 'Link de envio',
            description: 'Upload',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Link de envio desligado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_telegram',
            stage: 'entry',
            kind: 'entry',
            label: 'Telegram',
            description: 'Telegram',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Telegram desligado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_sender_blacklist',
            stage: 'entry',
            kind: 'policy',
            label: 'Bloqueio de remetentes',
            description: 'Blacklist',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Nenhum remetente bloqueado.',
            config_preview: { available: true, active_entries_count: 0 },
            branches: [
              { id: 'allowed', label: 'Permitido', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'blocked', label: 'Bloqueado', target_node_id: 'output_silence', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'processing',
        label: 'Processamento',
        description: 'Processamento',
        position: 1,
        nodes: [
          {
            id: 'processing_receive_feedback',
            stage: 'processing',
            kind: 'process',
            label: 'Confirmar recebimento',
            description: 'Feedback',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Envia feedback inicial quando a midia chega.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_safety_ai',
            stage: 'processing',
            kind: 'process',
            label: 'Safety',
            description: 'Safety',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Safety por IA desligado.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_media_intelligence',
            stage: 'processing',
            kind: 'process',
            label: 'MediaIntelligence',
            description: 'VLM',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'VLM desligado para este evento.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Decisao',
        position: 2,
        nodes: [
          {
            id: 'decision_event_moderation_mode',
            stage: 'decision',
            kind: 'decision',
            label: 'Modo de moderacao',
            description: 'Modo',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Envia midias para revisao humana antes de publicar.',
            config_preview: {},
            branches: [
              { id: 'approved', label: 'Aprovado', target_node_id: 'output_gallery', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'review', label: 'Revisao', target_node_id: 'output_gallery', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'blocked', label: 'Bloqueado', target_node_id: 'output_silence', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'default', label: 'Padrao', target_node_id: 'decision_safety_result', active: false, status: 'inactive', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_safety_result',
            stage: 'decision',
            kind: 'decision',
            label: 'Safety result',
            description: 'Safety result',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Sem decisao de Safety ativa.',
            config_preview: {},
            branches: [
              { id: 'safe', label: 'Seguro', target_node_id: 'decision_context_gate', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'review', label: 'Revisao', target_node_id: 'output_gallery', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'blocked', label: 'Bloqueado', target_node_id: 'output_silence', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'default', label: 'Padrao', target_node_id: 'decision_context_gate', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_context_gate',
            stage: 'decision',
            kind: 'decision',
            label: 'Contexto',
            description: 'Contexto',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'VLM nao esta usando gate de publicacao.',
            config_preview: {},
            branches: [
              { id: 'approved', label: 'Aprovado', target_node_id: 'output_gallery', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'review', label: 'Revisao', target_node_id: 'output_gallery', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'blocked', label: 'Bloqueado', target_node_id: 'output_silence', active: false, status: 'inactive', summary: null, conditions: {} },
              { id: 'default', label: 'Padrao', target_node_id: 'decision_media_type', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Saida',
        position: 3,
        nodes: [
          {
            id: 'output_reaction_final',
            stage: 'output',
            kind: 'output',
            label: 'Reacao final',
            description: 'Reacao',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Pode enviar feedback final no canal de origem.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_reply_text',
            stage: 'output',
            kind: 'output',
            label: 'Resposta',
            description: 'Resposta',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Resposta textual desligada.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_wall',
            stage: 'output',
            kind: 'output',
            label: 'Telao',
            description: 'Telao',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Telao desligado ou indisponivel.',
            config_preview: { available: true, enabled: false, module_enabled: false },
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
    ],
    warnings: [],
    simulation_presets: [],
    summary: {
      human_text: 'Resumo base.',
    },
  };
}

describe('buildJourneyTemplatePreview', () => {
  it('builds a local preview for the AI moderating template', () => {
    const preview = buildJourneyTemplatePreview(makeProjection(), 'ai-moderating');

    expect(preview.payload).toEqual(expect.objectContaining({
      moderation_mode: 'ai',
      content_moderation: expect.objectContaining({
        enabled: true,
        mode: 'enforced',
      }),
      media_intelligence: expect.objectContaining({
        enabled: true,
        mode: 'gate',
        reply_text_mode: 'ai',
      }),
    }));
    expect(preview.previewProjection.summary.human_text).toMatch(/analisa risco e contexto com IA antes de publicar/i);
    expect(
      preview.previewProjection.stages
        .flatMap((stage) => stage.nodes)
        .find((node) => node.id === 'processing_safety_ai')?.status,
    ).toBe('active');
    expect(
      preview.previewProjection.stages
        .flatMap((stage) => stage.nodes)
        .find((node) => node.id === 'output_reply_text')?.summary,
    ).toMatch(/texto gerado por IA/i);
  });

  it('does not enable wall output in the social template when the capability is unavailable', () => {
    const projection = makeProjection();
    projection.capabilities.supports_wall_output.available = false;
    const wallNode = projection.stages
      .flatMap((stage) => stage.nodes)
      .find((node) => node.id === 'output_wall');

    if (wallNode) {
      wallNode.status = 'locked';
      wallNode.config_preview = { ...wallNode.config_preview, available: false };
    }

    const preview = buildJourneyTemplatePreview(projection, 'social-simple');

    expect(preview.payload.modules?.wall).toBeUndefined();
    expect(preview.diff).toEqual(expect.arrayContaining([
      expect.objectContaining({
        kind: 'skipped',
        label: 'Telao',
      }),
    ]));
    expect(preview.previewProjection.settings.destinations.wall).toBe(false);
  });

  it('merges template draft values into detailed settings payloads for the inspector', () => {
    const contentSettings = mergeJourneyContentModerationSettings(
      {
        id: 1,
        event_id: 42,
        enabled: false,
        provider_key: 'openai',
        mode: 'observe_only',
        threshold_version: 'foundation-v1',
        hard_block_thresholds: { nudity: 0.9, violence: 0.9, self_harm: 0.9 },
        review_thresholds: { nudity: 0.6, violence: 0.6, self_harm: 0.6 },
        fallback_mode: 'review',
        created_at: null,
        updated_at: null,
      },
      { enabled: true, mode: 'enforced' },
    );
    const mediaSettings = mergeJourneyMediaIntelligenceSettings(
      {
        id: 2,
        event_id: 42,
        enabled: false,
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        mode: 'enrich_only',
        prompt_version: 'foundation-v1',
        approval_prompt: 'Prompt',
        caption_style_prompt: 'Legenda',
        response_schema_version: 'foundation-v1',
        timeout_ms: 12000,
        fallback_mode: 'review',
        require_json_output: true,
        reply_text_mode: 'disabled',
        reply_text_enabled: false,
        reply_prompt_override: null,
        reply_fixed_templates: [],
        reply_prompt_preset_id: null,
        created_at: null,
        updated_at: null,
      },
      { enabled: true, reply_text_enabled: true, reply_text_mode: 'ai' },
    );

    expect(contentSettings.enabled).toBe(true);
    expect(contentSettings.mode).toBe('enforced');
    expect(mediaSettings.enabled).toBe(true);
    expect(mediaSettings.reply_text_enabled).toBe(true);
    expect(mediaSettings.reply_text_mode).toBe('ai');
  });
});

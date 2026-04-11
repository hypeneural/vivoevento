import { describe, expect, it } from 'vitest';

import { buildJourneyGraph } from '../buildJourneyGraph';
import { buildJourneyScenarios, simulateJourneyScenario } from '../buildJourneyScenarios';
import { buildJourneySummary } from '../buildJourneySummary';
import type { EventJourneyProjection } from '../types';

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 31,
      uuid: 'journey-event-31',
      title: 'Casamento Ana e Pedro',
      status: 'active',
      moderation_mode: 'ai',
      modules: {
        live: true,
        wall: true,
        hub: true,
        play: false,
      },
    },
    intake_defaults: {
      whatsapp_instance_id: 18,
      whatsapp_instance_mode: 'shared',
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: true,
        available: true,
        editable: true,
        groups: [],
      },
      whatsapp_direct: {
        enabled: true,
        available: true,
        editable: true,
        media_inbox_code: 'NOIVA2026',
        session_ttl_minutes: 180,
      },
      public_upload: {
        enabled: true,
        available: true,
        editable: true,
      },
      telegram: {
        enabled: true,
        available: true,
        editable: true,
        bot_username: 'EventoVivoBot',
        media_inbox_code: 'NOIVABOT',
        session_ttl_minutes: 180,
      },
    },
    settings: {
      moderation_mode: 'ai',
      modules: {
        live: true,
        wall: true,
        hub: true,
        play: false,
      },
      content_moderation: {
        enabled: true,
        mode: 'enforced',
        fallback_mode: 'review',
        provider_key: 'openai',
        analysis_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      media_intelligence: {
        enabled: true,
        mode: 'gate',
        fallback_mode: 'review',
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        reply_text_enabled: true,
        reply_text_mode: 'ai',
        context_scope: 'image_and_text_context',
        reply_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
    },
    capabilities: {
      supports_wall_output: {
        id: 'supports_wall_output',
        label: 'Telao',
        enabled: true,
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
        description: 'Como fotos e videos chegam ao evento.',
        position: 0,
        nodes: [
          {
            id: 'entry_whatsapp_direct',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp privado',
            description: 'Recebe fotos por conversa privada.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por codigo privado.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_whatsapp_groups',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp grupos',
            description: 'Recebe fotos por grupos.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por grupos.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_telegram',
            stage: 'entry',
            kind: 'entry',
            label: 'Telegram',
            description: 'Recebe fotos pelo bot.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias pelo bot.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_public_upload',
            stage: 'entry',
            kind: 'entry',
            label: 'Link de envio',
            description: 'Recebe fotos por link.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por link.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_sender_blacklist',
            stage: 'entry',
            kind: 'policy',
            label: 'Bloqueio de remetentes',
            description: 'Impede avancar.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Existem remetentes bloqueados.',
            config_preview: {},
            branches: [
              {
                id: 'allowed',
                label: 'Permitido',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'blocked',
                label: 'Bloqueado',
                target_node_id: 'output_silence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_receive_feedback',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'processing',
        label: 'Processamento',
        description: 'Como a midia e salva, preparada e analisada.',
        position: 320,
        nodes: [
          {
            id: 'processing_receive_feedback',
            stage: 'processing',
            kind: 'process',
            label: 'Confirmar recebimento',
            description: 'Feedback inicial.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Envia feedback inicial.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_download_media',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_download_media',
            stage: 'processing',
            kind: 'process',
            label: 'Salvar midia',
            description: 'Etapa tecnica.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Obrigatorio.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'processing_prepare_variants',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_prepare_variants',
            stage: 'processing',
            kind: 'process',
            label: 'Preparar variantes',
            description: 'Etapa tecnica.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Obrigatorio.',
            config_preview: {},
            branches: [
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'decision_event_moderation_mode',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Como o evento decide aprovar, revisar ou bloquear.',
        position: 640,
        nodes: [
          {
            id: 'decision_event_moderation_mode',
            stage: 'decision',
            kind: 'decision',
            label: 'Modo de moderacao',
            description: 'Modo do evento.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Usa politicas de IA para aprovar, revisar ou bloquear.',
            config_preview: {
              moderation_mode: 'ai',
            },
            branches: [
              {
                id: 'approved',
                label: 'Aprovado',
                target_node_id: 'output_gallery',
                active: false,
                status: 'inactive',
                summary: null,
                conditions: {},
              },
              {
                id: 'review',
                label: 'Revisao',
                target_node_id: 'output_gallery',
                active: false,
                status: 'inactive',
                summary: null,
                conditions: {},
              },
              {
                id: 'blocked',
                label: 'Bloqueado',
                target_node_id: 'output_silence',
                active: false,
                status: 'inactive',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'decision_safety_result',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_safety_result',
            stage: 'decision',
            kind: 'decision',
            label: 'Resultado Safety',
            description: 'Interpreta sinais objetivos.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Pode aprovar, revisar ou bloquear conforme risco.',
            config_preview: {},
            branches: [
              {
                id: 'safe',
                label: 'Seguro',
                target_node_id: 'decision_context_gate',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'review',
                label: 'Revisao',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'blocked',
                label: 'Bloqueado',
                target_node_id: 'output_silence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'decision_context_gate',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_context_gate',
            stage: 'decision',
            kind: 'decision',
            label: 'Contexto do evento',
            description: 'Gate de contexto.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'VLM pode aprovar, revisar ou bloquear pelo contexto.',
            config_preview: {},
            branches: [
              {
                id: 'approved',
                label: 'Aprovado',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'review',
                label: 'Revisao',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'blocked',
                label: 'Bloqueado',
                target_node_id: 'output_silence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'decision_media_type',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_media_type',
            stage: 'decision',
            kind: 'decision',
            label: 'Foto ou video',
            description: 'Classificacao.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Detectado automaticamente.',
            config_preview: {},
            branches: [
              {
                id: 'photo',
                label: 'Foto',
                target_node_id: 'decision_caption_presence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'video',
                label: 'Video',
                target_node_id: 'decision_caption_presence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'decision_caption_presence',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_caption_presence',
            stage: 'decision',
            kind: 'decision',
            label: 'Legenda existe?',
            description: 'Caption.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Usado para simulacao.',
            config_preview: {},
            branches: [
              {
                id: 'with_caption',
                label: 'Com legenda',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'without_caption',
                label: 'Sem legenda',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
              {
                id: 'default',
                label: 'Padrao',
                target_node_id: 'output_gallery',
                active: true,
                status: 'active',
                summary: null,
                conditions: {},
              },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'O que acontece apos a decisao da midia.',
        position: 960,
        nodes: [
          {
            id: 'output_reaction_final',
            stage: 'output',
            kind: 'output',
            label: 'Reacao final',
            description: 'Feedback final.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Feedback final ativo.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_reply_text',
            stage: 'output',
            kind: 'output',
            label: 'Responder com mensagem',
            description: 'Resposta automatica.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Resposta automatica por IA.',
            config_preview: {
              reply_text_mode: 'ai',
            },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_gallery',
            stage: 'output',
            kind: 'output',
            label: 'Publicar na galeria',
            description: 'Destino base.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Destino base.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_wall',
            stage: 'output',
            kind: 'output',
            label: 'Enviar para telao',
            description: 'Destino wall.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Telao ativo.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_silence',
            stage: 'output',
            kind: 'output',
            label: 'Silenciar ou arquivar',
            description: 'Fallback.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Fallback seguro.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
    ],
    warnings: [],
    simulation_presets: [
      {
        id: 'photo_whatsapp_private_with_caption',
        label: 'Foto com legenda',
        description: 'Simula uma foto pelo WhatsApp privado com legenda.',
        input: {
          channel: 'whatsapp_direct',
          media_type: 'photo',
          has_caption: true,
        },
        expected_node_ids: [
          'entry_whatsapp_direct',
          'processing_download_media',
          'decision_caption_presence',
          'output_gallery',
        ],
      },
    ],
    summary: {
      human_text: 'Quando a midia chega por WhatsApp privado, o Evento Vivo analisa risco e contexto com IA antes de publicar, responde automaticamente com IA e publica na galeria e no telao.',
    },
  };
}

describe('journey scenarios and summary', () => {
  it('keeps the backend summary as the default human explanation', () => {
    const projection = makeProjection();

    expect(buildJourneySummary(projection)).toBe(projection.summary.human_text);
  });

  it('returns the curated V1 scenarios with stable highlight ids', () => {
    const projection = makeProjection();
    const graph = buildJourneyGraph(projection);
    const scenarios = buildJourneyScenarios(projection, graph);

    expect(scenarios.map((scenario) => scenario.id)).toEqual([
      'photo_whatsapp_private_with_caption',
      'photo_whatsapp_group_without_caption',
      'video_telegram',
      'blocked_sender',
      'safety_blocked',
      'safety_review',
      'vlm_gate_review',
      'approved_and_published',
      'rejected_with_reply',
    ]);

    expect(scenarios.find((scenario) => scenario.id === 'photo_whatsapp_private_with_caption')).toMatchObject({
      available: true,
      highlightedNodeIds: expect.arrayContaining([
        'entry_whatsapp_direct',
        'decision_caption_presence',
        'output_gallery',
      ]),
      highlightedEdgeIds: expect.arrayContaining([
        'decision_caption_presence:with_caption->output_gallery',
      ]),
    });
  });

  it('simulates a blocked sender through the blacklist policy and silence output', () => {
    const projection = makeProjection();
    const graph = buildJourneyGraph(projection);
    const simulation = simulateJourneyScenario(projection, graph, 'blocked_sender');

    expect(simulation.available).toBe(true);
    expect(simulation.outcome).toBe('blocked');
    expect(simulation.highlightedNodeIds).toEqual([
      'entry_whatsapp_direct',
      'entry_sender_blacklist',
      'output_silence',
    ]);
    expect(simulation.highlightedEdgeIds).toEqual([
      'entry_sender_blacklist:blocked->output_silence',
    ]);
    expect(simulation.humanText).toContain('remetente ja esta bloqueado');
  });

  it('marks VLM gate review as unavailable when context gate is inactive', () => {
    const projection = makeProjection();
    const contextNode = projection.stages[2]?.nodes.find((node) => node.id === 'decision_context_gate');

    if (!contextNode) {
      throw new Error('decision_context_gate fixture missing');
    }

    contextNode.active = false;
    contextNode.status = 'inactive';
    contextNode.summary = 'VLM nao esta usando gate de publicacao.';
    contextNode.branches = [
      {
        id: 'default',
        label: 'Padrao',
        target_node_id: 'decision_media_type',
        active: true,
        status: 'active',
        summary: null,
        conditions: {},
      },
    ];

    const graph = buildJourneyGraph(projection);
    const scenarios = buildJourneyScenarios(projection, graph);
    const scenario = scenarios.find((candidate) => candidate.id === 'vlm_gate_review');

    expect(scenario).toMatchObject({
      available: false,
      unavailableReason: 'O gate de contexto nao esta ativo na jornada atual.',
    });
  });

  it('explains approved publication with wall and automatic reply outputs when enabled', () => {
    const projection = makeProjection();
    const graph = buildJourneyGraph(projection);
    const simulation = simulateJourneyScenario(projection, graph, 'approved_and_published');

    expect(simulation.available).toBe(true);
    expect(simulation.outcome).toBe('approved');
    expect(simulation.highlightedNodeIds).toEqual(expect.arrayContaining([
      'output_gallery',
      'output_wall',
      'output_reply_text',
      'output_reaction_final',
    ]));
    expect(simulation.humanText).toContain('publica na galeria e no telao');
    expect(simulation.humanText).toContain('resposta automatica');
    expect(buildJourneySummary(projection, simulation)).toBe(simulation.humanText);
  });

  it('highlights rejection with automatic reply when a blocked branch and reply output are active', () => {
    const projection = makeProjection();
    const graph = buildJourneyGraph(projection);
    const simulation = simulateJourneyScenario(projection, graph, 'rejected_with_reply');

    expect(simulation.available).toBe(true);
    expect(simulation.outcome).toBe('blocked');
    expect(simulation.highlightedEdgeIds).toEqual(expect.arrayContaining([
      'decision_safety_result:blocked->output_silence',
    ]));
    expect(simulation.highlightedNodeIds).toEqual(expect.arrayContaining([
      'output_silence',
      'output_reply_text',
      'output_reaction_final',
    ]));
    expect(simulation.humanText).toContain('bloqueia a midia');
    expect(simulation.humanText).toContain('mensagem automatica');
  });
});

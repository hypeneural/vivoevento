import { describe, expect, it } from 'vitest';

import { buildJourneyGraph } from '../buildJourneyGraph';
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
        enabled: false,
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
        enabled: false,
        available: true,
        editable: true,
        bot_username: null,
        media_inbox_code: null,
        session_ttl_minutes: null,
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
            config_preview: {},
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
    simulation_presets: [],
    summary: {
      human_text: 'Quando a midia chega por WhatsApp privado, o Evento Vivo analisa risco e contexto com IA antes de publicar.',
    },
  };
}

describe('buildJourneyGraph', () => {
  it('keeps the four stage bands with deterministic y positions', () => {
    const graph = buildJourneyGraph(makeProjection());

    expect(graph.stages.map((stage) => stage.id)).toEqual(['entry', 'processing', 'decision', 'output']);
    expect(graph.stages.map((stage) => stage.y)).toEqual([0, 320, 640, 960]);
    expect(graph.nodes.find((node) => node.id === 'decision_context_gate')?.position).toEqual({
      x: 660,
      y: 640,
    });
  });

  it('creates stable handles and labeled edges for decision branches, including default', () => {
    const graph = buildJourneyGraph(makeProjection());
    const decisionNode = graph.nodes.find((node) => node.id === 'decision_event_moderation_mode');
    const defaultEdge = graph.edges.find((edge) => edge.id === 'decision_event_moderation_mode:default->decision_safety_result');

    expect(decisionNode?.sourceHandles).toContain('branch:default');
    expect(defaultEdge).toMatchObject({
      source: 'decision_event_moderation_mode',
      target: 'decision_safety_result',
      sourceHandle: 'branch:default',
      targetHandle: 'inbound',
      label: 'Padrao',
    });
  });

  it('keeps blocked safety branches visible but inactive when Safety is effectively disabled', () => {
    const projection = makeProjection();
    const safetyNode = projection.stages[2]?.nodes.find((node) => node.id === 'decision_safety_result');

    if (!safetyNode) {
      throw new Error('decision_safety_result fixture missing');
    }

    safetyNode.active = false;
    safetyNode.status = 'inactive';
    safetyNode.summary = 'Sem decisao de Safety ativa.';
    safetyNode.branches = safetyNode.branches.map((branch) =>
      branch.id === 'default'
        ? branch
        : {
            ...branch,
            active: false,
            status: 'inactive',
          },
    );

    const graph = buildJourneyGraph(projection);
    const blockedEdge = graph.edges.find((edge) => edge.id === 'decision_safety_result:blocked->output_silence');

    expect(blockedEdge).toBeDefined();
    expect(blockedEdge?.className).toContain('status-inactive');
    expect(blockedEdge?.className).toContain('is-inactive');
  });

  it('does not invent blocked or review context edges when VLM is enrich_only', () => {
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

    expect(graph.edges.find((edge) => edge.id === 'decision_context_gate:blocked->output_silence')).toBeUndefined();
    expect(graph.edges.find((edge) => edge.id === 'decision_context_gate:review->output_gallery')).toBeUndefined();
    expect(graph.edges.find((edge) => edge.id === 'decision_context_gate:default->decision_media_type')).toBeDefined();
  });

  it('projects review and blocked context paths when VLM gate is active', () => {
    const graph = buildJourneyGraph(makeProjection());

    expect(graph.edges.find((edge) => edge.id === 'decision_context_gate:review->output_gallery')).toBeDefined();
    expect(graph.edges.find((edge) => edge.id === 'decision_context_gate:blocked->output_silence')).toBeDefined();
  });

  it('keeps the wall output visible but inactive when the destination is disabled', () => {
    const projection = makeProjection();
    const wallNode = projection.stages[3]?.nodes.find((node) => node.id === 'output_wall');

    if (!wallNode) {
      throw new Error('output_wall fixture missing');
    }

    wallNode.active = false;
    wallNode.status = 'inactive';
    wallNode.summary = 'Telao desligado ou indisponivel.';

    const graph = buildJourneyGraph(projection);
    const graphWallNode = graph.nodes.find((node) => node.id === 'output_wall');

    expect(graphWallNode?.className).toContain('status-inactive');
    expect(graphWallNode?.className).toContain('is-inactive');
    expect(graphWallNode?.data.node.status).toBe('inactive');
  });
});

import type { ReactNode } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { buildJourneyGraph } from '../buildJourneyGraph';
import { JourneyFlowCanvas } from '../JourneyFlowCanvas';
import type { EventJourneyProjection } from '../types';

const fitViewMock = vi.fn();
const reactFlowPropsSpy = vi.fn();

vi.mock('@xyflow/react', () => {
  return {
    Background: () => <div data-testid="journey-flow-background" />,
    BaseEdge: ({ id }: { id: string }) => <div data-testid={`journey-edge-${id}`} />,
    EdgeLabelRenderer: ({ children }: { children: ReactNode }) => <div>{children}</div>,
    Handle: ({ id, type, position }: { id?: string; type: string; position: string }) => (
      <div data-testid={`journey-handle-${id ?? type}-${position}`} />
    ),
    getBezierPath: () => ['M 0,0 C 0,0 0,0 0,0', 120, 180],
    MarkerType: {
      ArrowClosed: 'arrow-closed',
    },
    Position: {
      Top: 'top',
      Bottom: 'bottom',
    },
    ReactFlow: (props: Record<string, unknown>) => {
      reactFlowPropsSpy(props);

      return (
        <div data-testid="react-flow-mock">
          <button
            type="button"
            onClick={() => (props.onInit as ((instance: { fitView: typeof fitViewMock }) => void) | undefined)?.({
              fitView: fitViewMock,
            })}
          >
            Inicializar flow
          </button>
          <button
            type="button"
            onClick={() => (props.onNodeClick as ((event: unknown, node: { id: string }) => void) | undefined)?.(
              {},
              { id: 'decision_event_moderation_mode' },
            )}
          >
            Clicar no
          </button>
          <button
            type="button"
            onClick={() => (props.onSelectionChange as ((params: { nodes: Array<{ id: string }> }) => void) | undefined)?.({
              nodes: [{ id: 'output_wall' }],
            })}
          >
            Alterar selecao
          </button>
          <button
            type="button"
            onClick={() => (props.onPaneClick as (() => void) | undefined)?.()}
          >
            Clicar no canvas
          </button>
          {props.children as ReactNode}
        </div>
      );
    },
  };
});

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 42,
      uuid: 'event-42',
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
      whatsapp_instance_id: 7,
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
        media_inbox_code: 'ANA2026',
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
        media_inbox_code: 'BOT2026',
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
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} }],
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
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'decision_event_moderation_mode', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Quais condicoes alteram o caminho da midia.',
        position: 640,
        nodes: [
          {
            id: 'decision_event_moderation_mode',
            stage: 'decision',
            kind: 'decision',
            label: 'Modo de moderacao do evento',
            description: 'Define se aprova direto ou manda para revisao.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Moderacao por IA ativa.',
            config_preview: {},
            branches: [{ id: 'approved', label: 'Aprovado', target_node_id: 'output_wall', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Como a jornada responde e publica.',
        position: 960,
        nodes: [
          {
            id: 'output_wall',
            stage: 'output',
            kind: 'output',
            label: 'Publicar no telao',
            description: 'Publica quando o modulo estiver ativo.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Telao habilitado.',
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
      human_text: 'Resumo base',
    },
  };
}

describe('JourneyFlowCanvas', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a locked React Flow wrapper with the expected interaction props', () => {
    const graph = buildJourneyGraph(makeProjection());

    render(
      <JourneyFlowCanvas
        graph={graph}
        selectedNodeId="decision_event_moderation_mode"
        highlightedNodeIds={['decision_event_moderation_mode']}
        highlightedEdgeIds={['decision_event_moderation_mode:approved->output_wall']}
      />,
    );

    expect(screen.getByTestId('journey-flow-canvas')).toBeInTheDocument();

    const props = reactFlowPropsSpy.mock.calls.at(-1)?.[0] as Record<string, unknown>;

    expect(props.nodesDraggable).toBe(false);
    expect(props.nodesConnectable).toBe(false);
    expect(props.elementsSelectable).toBe(true);
    expect(props.nodesFocusable).toBe(true);
    expect(props.edgesFocusable).toBe(false);
    expect(props.deleteKeyCode).toBeNull();
    expect(props.selectNodesOnDrag).toBe(false);
    expect(props.zoomOnDoubleClick).toBe(false);
    expect(props.preventScrolling).toBe(false);
    expect(props.onlyRenderVisibleElements).toBe(false);
    expect(props.fitView).toBe(true);
    expect(props.ariaLabelConfig).toEqual(
      expect.objectContaining({
        'controls.ariaLabel': 'Controles do canvas da jornada',
      }),
    );

    const nodes = props.nodes as Array<{ id: string; data: { isHighlighted: boolean }; selected: boolean }>;
    const edges = props.edges as Array<{ id: string; data: { isHighlighted: boolean }; animated: boolean }>;

    expect(nodes.find((node) => node.id === 'decision_event_moderation_mode')).toEqual(
      expect.objectContaining({
        selected: true,
        data: expect.objectContaining({ isHighlighted: true }),
      }),
    );
    expect(edges.find((edge) => edge.id === 'decision_event_moderation_mode:approved->output_wall')).toEqual(
      expect.objectContaining({
        animated: true,
        data: expect.objectContaining({ isHighlighted: true }),
      }),
    );
  });

  it('bridges init, selection and pane callbacks back to the page shell', () => {
    const graph = buildJourneyGraph(makeProjection());
    const onSelectedNodeIdChange = vi.fn();
    const onReady = vi.fn();

    render(
      <JourneyFlowCanvas
        graph={graph}
        selectedNodeId={null}
        onSelectedNodeIdChange={onSelectedNodeIdChange}
        onReady={onReady}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Inicializar flow' }));

    expect(onReady).toHaveBeenCalledTimes(1);

    const controls = onReady.mock.calls[0]?.[0] as { fitView: () => void };
    controls.fitView();

    expect(fitViewMock).toHaveBeenCalledWith(
      expect.objectContaining({
        padding: 0.1,
        duration: 250,
      }),
    );

    fireEvent.click(screen.getByRole('button', { name: 'Clicar no' }));
    fireEvent.click(screen.getByRole('button', { name: 'Alterar selecao' }));
    fireEvent.click(screen.getByRole('button', { name: 'Clicar no canvas' }));

    expect(onSelectedNodeIdChange).toHaveBeenNthCalledWith(1, 'decision_event_moderation_mode');
    expect(onSelectedNodeIdChange).toHaveBeenNthCalledWith(2, 'output_wall');
    expect(onSelectedNodeIdChange).toHaveBeenNthCalledWith(3, null);
  });

  it('shows the empty-state card when the graph has no nodes', () => {
    render(
      <JourneyFlowCanvas
        graph={{ stages: [], nodes: [], edges: [] }}
        selectedNodeId={null}
      />,
    );

    expect(screen.getByText('A jornada ainda nao tem etapas projetadas')).toBeInTheDocument();
  });
});

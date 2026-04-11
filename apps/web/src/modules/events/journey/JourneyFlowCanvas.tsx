import { memo, useCallback, useMemo } from 'react';
import {
  Background,
  BaseEdge,
  EdgeLabelRenderer,
  Handle,
  MarkerType,
  Position,
  ReactFlow,
  type Edge,
  type EdgeProps,
  type Node,
  type NodeProps,
  type ReactFlowInstance,
} from '@xyflow/react';

import '@xyflow/react/dist/style.css';

import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

import type { JourneyGraph, JourneyGraphEdge, JourneyGraphNode } from './buildJourneyGraph';

interface JourneyFlowNodeData {
  graphNode: JourneyGraphNode;
  isHighlighted: boolean;
}

interface JourneyFlowEdgeData {
  graphEdge: JourneyGraphEdge;
  isHighlighted: boolean;
}

type JourneyFlowNode = Node<JourneyFlowNodeData, 'journey'>;
type JourneyFlowEdge = Edge<JourneyFlowEdgeData, 'journey'>;

export interface JourneyFlowCanvasControls {
  fitView: () => void;
}

interface JourneyFlowCanvasProps {
  graph: JourneyGraph;
  selectedNodeId: string | null;
  highlightedNodeIds?: string[];
  highlightedEdgeIds?: string[];
  onSelectedNodeIdChange?: (nodeId: string | null) => void;
  onReady?: (controls: JourneyFlowCanvasControls) => void;
}

const JOURNEY_FLOW_ARIA_LABELS = {
  'node.a11yDescription.default': 'Etapa da jornada focavel. Use Enter para selecionar a etapa.',
  'edge.a11yDescription.default': 'Conexao entre etapas da jornada da midia.',
  'controls.ariaLabel': 'Controles do canvas da jornada',
  'controls.fitView.ariaLabel': 'Centralizar o fluxo da jornada',
  'minimap.ariaLabel': 'Mini mapa da jornada',
  'handle.ariaLabel': 'Handle da jornada',
} as const;

const JOURNEY_FLOW_FIT_VIEW_OPTIONS = {
  padding: 0.2,
  duration: 250,
} as const;

const TARGET_HANDLE_ID = 'inbound';

function resolveStageAccent(stage: JourneyGraphNode['stage']) {
  switch (stage) {
    case 'entry':
      return {
        border: 'border-sky-200',
        surface: 'bg-sky-50/90',
        badge: 'bg-sky-100 text-sky-800',
        handle: '#0ea5e9',
      };
    case 'processing':
      return {
        border: 'border-violet-200',
        surface: 'bg-violet-50/90',
        badge: 'bg-violet-100 text-violet-800',
        handle: '#8b5cf6',
      };
    case 'decision':
      return {
        border: 'border-amber-200',
        surface: 'bg-amber-50/90',
        badge: 'bg-amber-100 text-amber-800',
        handle: '#f59e0b',
      };
    case 'output':
    default:
      return {
        border: 'border-emerald-200',
        surface: 'bg-emerald-50/90',
        badge: 'bg-emerald-100 text-emerald-800',
        handle: '#10b981',
      };
  }
}

function resolveNodeStatusLabel(status: JourneyGraphNode['status']) {
  switch (status) {
    case 'active':
      return 'Ativo';
    case 'inactive':
      return 'Desativado';
    case 'locked':
      return 'Bloqueado';
    case 'required':
      return 'Obrigatorio';
    case 'unavailable':
    default:
      return 'Indisponivel';
  }
}

function resolveEdgeTone(graphEdge: JourneyGraphEdge, highlighted: boolean) {
  if (highlighted) {
    return {
      stroke: '#0f766e',
      labelClassName: 'border-emerald-200 bg-emerald-50 text-emerald-800 shadow-sm',
    };
  }

  switch (graphEdge.data.branch.status) {
    case 'locked':
    case 'unavailable':
      return {
        stroke: '#b45309',
        labelClassName: 'border-amber-200 bg-amber-50 text-amber-800',
      };
    case 'inactive':
      return {
        stroke: '#94a3b8',
        labelClassName: 'border-slate-200 bg-slate-50 text-slate-500',
      };
    case 'required':
    case 'active':
    default:
      return {
        stroke: '#475569',
        labelClassName: 'border-slate-200 bg-white text-slate-700',
      };
  }
}

const JourneyNodeComponent = memo(function JourneyNodeComponent({
  data,
  selected,
}: NodeProps<JourneyFlowNode>) {
  const { graphNode, isHighlighted } = data;
  const accent = resolveStageAccent(graphNode.stage);
  const branchCount = Math.max(graphNode.sourceHandles.length, 1);

  return (
    <div
      className={cn(
        'h-full w-full rounded-[22px] border bg-white/95 p-4 shadow-sm transition-all',
        accent.border,
        isHighlighted && 'ring-2 ring-emerald-400/70 ring-offset-2',
        selected && 'ring-2 ring-primary ring-offset-2',
      )}
    >
      <Handle
        id={TARGET_HANDLE_ID}
        type="target"
        position={Position.Top}
        isConnectable={false}
        aria-hidden="true"
        className="!h-3 !w-3 !border-2 !border-white"
        style={{
          background: accent.handle,
        }}
      />

      <div className="flex h-full flex-col">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <div className={cn('inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium', accent.badge)}>
              {graphNode.data.stage.label}
            </div>
            <h3 className="text-sm font-semibold text-foreground">{graphNode.data.node.label}</h3>
            <p className="text-xs text-muted-foreground">{graphNode.data.node.description}</p>
          </div>
          <div className={cn('rounded-full px-2.5 py-1 text-[11px] font-medium', accent.surface)}>
            {resolveNodeStatusLabel(graphNode.status)}
          </div>
        </div>

        <p className="mt-3 line-clamp-3 text-sm leading-5 text-foreground/90">
          {graphNode.data.node.summary}
        </p>

        <div className="mt-auto flex flex-wrap gap-2 pt-3">
          <span className="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] text-slate-600">
            {graphNode.data.node.editable ? 'Editavel' : 'Tecnico'}
          </span>
          {graphNode.data.node.warnings.length > 0 ? (
            <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] text-amber-800">
              {graphNode.data.node.warnings.length} alerta{graphNode.data.node.warnings.length > 1 ? 's' : ''}
            </span>
          ) : null}
        </div>
      </div>

      {graphNode.sourceHandles.map((sourceHandleId, index) => {
        const left = `${((index + 1) / (branchCount + 1)) * 100}%`;

        return (
          <Handle
            key={sourceHandleId}
            id={sourceHandleId}
            type="source"
            position={Position.Bottom}
            isConnectable={false}
            aria-hidden="true"
            className="!h-3 !w-3 !border-2 !border-white"
            style={{
              left,
              transform: 'translate(-50%, 50%)',
              background: accent.handle,
              visibility: 'visible',
            }}
          />
        );
      })}
    </div>
  );
});

const JourneyEdgeComponent = memo(function JourneyEdgeComponent({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  label,
  markerEnd,
  data,
}: EdgeProps<JourneyFlowEdge>) {
  const tone = resolveEdgeTone(data.graphEdge, data.isHighlighted);
  const [path, labelX, labelY] = [
    `M ${sourceX},${sourceY} C ${sourceX},${sourceY + 40} ${targetX},${targetY - 40} ${targetX},${targetY}`,
    (sourceX + targetX) / 2,
    (sourceY + targetY) / 2,
  ];

  return (
    <>
      <BaseEdge
        id={id}
        path={path}
        markerEnd={markerEnd}
        style={{
          stroke: tone.stroke,
          strokeWidth: data.isHighlighted ? 2.5 : 1.75,
        }}
      />
      <EdgeLabelRenderer>
        <div
          className={cn(
            'nodrag nopan absolute rounded-full border px-2 py-1 text-[11px] font-medium',
            tone.labelClassName,
          )}
          style={{
            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
            pointerEvents: 'all',
          }}
        >
          {label}
        </div>
      </EdgeLabelRenderer>
    </>
  );
});

const NODE_TYPES = {
  journey: JourneyNodeComponent,
} as const;

const EDGE_TYPES = {
  journey: JourneyEdgeComponent,
} as const;

function toReactFlowNode(
  graphNode: JourneyGraphNode,
  highlightedNodeIds: Set<string>,
  selectedNodeId: string | null,
): JourneyFlowNode {
  return {
    id: graphNode.id,
    type: 'journey',
    position: graphNode.position,
    width: graphNode.width,
    height: graphNode.height,
    draggable: false,
    selectable: true,
    focusable: true,
    selected: graphNode.id === selectedNodeId,
    ariaLabel: `${graphNode.data.node.label}. ${graphNode.data.node.summary}`,
    data: {
      graphNode,
      isHighlighted: highlightedNodeIds.has(graphNode.id),
    },
  };
}

function toReactFlowEdge(
  graphEdge: JourneyGraphEdge,
  highlightedEdgeIds: Set<string>,
): JourneyFlowEdge {
  const tone = resolveEdgeTone(graphEdge, highlightedEdgeIds.has(graphEdge.id));

  return {
    id: graphEdge.id,
    type: 'journey',
    source: graphEdge.source,
    target: graphEdge.target,
    sourceHandle: graphEdge.sourceHandle,
    targetHandle: graphEdge.targetHandle,
    label: graphEdge.label,
    selectable: false,
    focusable: false,
    animated: highlightedEdgeIds.has(graphEdge.id),
    markerEnd: {
      type: MarkerType.ArrowClosed,
      color: tone.stroke,
    },
    data: {
      graphEdge,
      isHighlighted: highlightedEdgeIds.has(graphEdge.id),
    },
  };
}

export function JourneyFlowCanvas({
  graph,
  selectedNodeId,
  highlightedNodeIds = [],
  highlightedEdgeIds = [],
  onSelectedNodeIdChange,
  onReady,
}: JourneyFlowCanvasProps) {
  const highlightedNodeIdSet = useMemo(() => new Set(highlightedNodeIds), [highlightedNodeIds]);
  const highlightedEdgeIdSet = useMemo(() => new Set(highlightedEdgeIds), [highlightedEdgeIds]);

  const nodes = useMemo(
    () => graph.nodes.map((graphNode) => toReactFlowNode(graphNode, highlightedNodeIdSet, selectedNodeId)),
    [graph.nodes, highlightedNodeIdSet, selectedNodeId],
  );
  const edges = useMemo(
    () => graph.edges.map((graphEdge) => toReactFlowEdge(graphEdge, highlightedEdgeIdSet)),
    [graph.edges, highlightedEdgeIdSet],
  );

  const handleInit = useCallback((instance: ReactFlowInstance<JourneyFlowNode, JourneyFlowEdge>) => {
    onReady?.({
      fitView: () => {
        void instance.fitView(JOURNEY_FLOW_FIT_VIEW_OPTIONS);
      },
    });
  }, [onReady]);

  const handleNodeClick = useCallback((_: unknown, node: JourneyFlowNode) => {
    onSelectedNodeIdChange?.(node.id);
  }, [onSelectedNodeIdChange]);

  const handleSelectionChange = useCallback(({ nodes: selectedNodes }: { nodes: JourneyFlowNode[] }) => {
    onSelectedNodeIdChange?.(selectedNodes[0]?.id ?? null);
  }, [onSelectedNodeIdChange]);

  const handlePaneClick = useCallback(() => {
    onSelectedNodeIdChange?.(null);
  }, [onSelectedNodeIdChange]);

  if (graph.nodes.length === 0) {
    return (
      <Card className="border-dashed border-muted-foreground/30 bg-background/60 shadow-none">
        <CardHeader>
          <CardTitle className="text-base">A jornada ainda nao tem etapas projetadas</CardTitle>
          <CardDescription>
            O backend respondeu sem `stages`. Antes de ligar o canvas interativo, precisamos revalidar a projection desse evento.
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <div className="h-full min-h-[640px]" data-testid="journey-flow-canvas">
      <ReactFlow<JourneyFlowNode, JourneyFlowEdge>
        nodes={nodes}
        edges={edges}
        nodeTypes={NODE_TYPES}
        edgeTypes={EDGE_TYPES}
        onInit={handleInit}
        onNodeClick={handleNodeClick}
        onSelectionChange={handleSelectionChange}
        onPaneClick={handlePaneClick}
        nodesDraggable={false}
        nodesConnectable={false}
        elementsSelectable
        nodesFocusable
        edgesFocusable={false}
        deleteKeyCode={null}
        selectionOnDrag={false}
        selectNodesOnDrag={false}
        zoomOnDoubleClick={false}
        fitView
        fitViewOptions={JOURNEY_FLOW_FIT_VIEW_OPTIONS}
        ariaLabelConfig={JOURNEY_FLOW_ARIA_LABELS}
        preventScrolling={false}
        onlyRenderVisibleElements={false}
        className="rounded-[26px] border border-slate-200 bg-[linear-gradient(180deg,rgba(248,250,252,0.95),rgba(241,245,249,0.92))]"
      >
        <Background gap={24} size={1} color="#cbd5e1" />
      </ReactFlow>
    </div>
  );
}

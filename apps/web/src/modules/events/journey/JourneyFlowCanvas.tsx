import { useCallback, useMemo } from 'react';
import {
  Background,
  ReactFlow,
  type ReactFlowInstance,
} from '@xyflow/react';

import '@xyflow/react/dist/style.css';

import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

import type { JourneyGraph, JourneyGraphEdge, JourneyGraphNode } from './buildJourneyGraph';
import {
  JOURNEY_EDGE_TYPES,
  toJourneyFlowEdge,
  type JourneyFlowEdge,
} from './JourneyFlowEdges';
import {
  JOURNEY_NODE_TYPES,
  type JourneyFlowNode,
} from './JourneyFlowNodes';

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
  padding: 0.1,
  duration: 250,
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
    style: {
      width: graphNode.width,
      height: graphNode.height,
    },
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
  return toJourneyFlowEdge(graphEdge, highlightedEdgeIds);
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
    <div className="h-full min-h-[840px]" data-testid="journey-flow-canvas">
      <ReactFlow<JourneyFlowNode, JourneyFlowEdge>
        nodes={nodes}
        edges={edges}
        nodeTypes={JOURNEY_NODE_TYPES}
        edgeTypes={JOURNEY_EDGE_TYPES}
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

import { memo, useEffect } from 'react';
import {
  Handle,
  Position,
  type Node,
  type NodeProps,
  useUpdateNodeInternals,
} from '@xyflow/react';

import type { JourneyGraphNode } from './buildJourneyGraph';
import { JourneyNodeCard, resolveJourneyStageAccent } from './JourneyNodeCard';

export interface JourneyFlowNodeData {
  graphNode: JourneyGraphNode;
  isHighlighted: boolean;
}

export type JourneyFlowNode = Node<JourneyFlowNodeData, 'journey'>;

function JourneyStepNode({
  data,
  selected,
}: NodeProps<JourneyFlowNode>) {
  const graphNode = data.graphNode;
  const accent = resolveJourneyStageAccent(graphNode.stage);
  const branchCount = Math.max(graphNode.sourceHandles.length, 1);
  const inboundHandleCount = graphNode.targetHandles.length;
  const updateNodeInternals = useUpdateNodeInternals();

  useEffect(() => {
    updateNodeInternals(graphNode.id);
  }, [
    graphNode.id,
    graphNode.sourceHandles.join('|'),
    graphNode.targetHandles.join('|'),
    updateNodeInternals,
  ]);

  return (
    <>
      {graphNode.targetHandles.map((targetHandleId, index) => {
        const left = `${((index + 1) / (inboundHandleCount + 1)) * 100}%`;

        return (
          <Handle
            key={targetHandleId}
            id={targetHandleId}
            type="target"
            position={Position.Top}
            isConnectable={false}
            aria-hidden="true"
            className="!h-3 !w-3 !border-2 !border-white"
            style={{
              left,
              transform: 'translate(-50%, -50%)',
              background: accent.handleColor,
              visibility: 'visible',
            }}
          />
        );
      })}

      <JourneyNodeCard
        nodeId={graphNode.id}
        stage={graphNode.stage}
        kind={graphNode.kind}
        status={graphNode.status}
        label={graphNode.data.node.label}
        description={graphNode.data.node.description}
        summary={graphNode.data.node.summary}
        editable={graphNode.data.node.editable}
        warningCount={graphNode.data.node.warnings.length}
        branchLabels={graphNode.data.node.branches.map((branch) => branch.label)}
        highlighted={data.isHighlighted}
        selected={selected}
      />

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
              background: accent.handleColor,
              visibility: 'visible',
            }}
          />
        );
      })}
    </>
  );
}

function JourneyDecisionNode(props: NodeProps<JourneyFlowNode>) {
  return <JourneyStepNode {...props} />;
}

const JourneyFlowNodeComponent = memo(function JourneyFlowNodeComponent(
  props: NodeProps<JourneyFlowNode>,
) {
  if (props.data.graphNode.kind === 'decision') {
    return <JourneyDecisionNode {...props} />;
  }

  return <JourneyStepNode {...props} />;
});

export const JOURNEY_NODE_TYPES = {
  journey: JourneyFlowNodeComponent,
} as const;

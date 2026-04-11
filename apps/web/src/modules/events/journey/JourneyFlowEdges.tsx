import { memo } from 'react';
import {
  BaseEdge,
  EdgeLabelRenderer,
  MarkerType,
  getBezierPath,
  type Edge,
  type EdgeProps,
} from '@xyflow/react';

import type { JourneyGraphEdge } from './buildJourneyGraph';
import { JourneyEdgeLabel } from './JourneyEdgeLabel';

export interface JourneyFlowEdgeData {
  graphEdge: JourneyGraphEdge;
  isHighlighted: boolean;
}

export type JourneyFlowEdge = Edge<JourneyFlowEdgeData, 'journey'>;

interface JourneyEdgeTone {
  stroke: string;
  labelClassName: string;
}

export function resolveJourneyEdgeTone(
  graphEdge: JourneyGraphEdge,
  highlighted: boolean,
): JourneyEdgeTone {
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

const JourneyEdgeComponent = memo(function JourneyEdgeComponent({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  label,
  markerEnd,
  data,
}: EdgeProps<JourneyFlowEdge>) {
  const tone = resolveJourneyEdgeTone(data.graphEdge, data.isHighlighted);
  const [path, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  });

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
        <JourneyEdgeLabel
          label={String(label ?? '')}
          x={labelX}
          y={labelY}
          className={tone.labelClassName}
        />
      </EdgeLabelRenderer>
    </>
  );
});

export const JOURNEY_EDGE_TYPES = {
  journey: JourneyEdgeComponent,
} as const;

export function toJourneyFlowEdge(
  graphEdge: JourneyGraphEdge,
  highlightedEdgeIds: Set<string>,
): JourneyFlowEdge {
  const isHighlighted = highlightedEdgeIds.has(graphEdge.id);
  const tone = resolveJourneyEdgeTone(graphEdge, isHighlighted);

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
    animated: isHighlighted,
    markerEnd: {
      type: MarkerType.ArrowClosed,
      color: tone.stroke,
    },
    data: {
      graphEdge,
      isHighlighted,
    },
  };
}

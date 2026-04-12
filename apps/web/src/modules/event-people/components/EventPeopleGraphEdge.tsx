import type { EdgeProps } from '@xyflow/react';
import {
  BaseEdge,
  EdgeLabelRenderer,
  getSmoothStepPath,
} from '@xyflow/react';

import type { EventPeopleFlowEdge } from './eventPeopleGraphFlow';

export function EventPeopleGraphEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  markerEnd,
  data,
}: EdgeProps<EventPeopleFlowEdge>) {
  const [path, labelX, labelY] = getSmoothStepPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  });
  const showLabel = Boolean(data?.isSelected);
  const stroke = data?.isSelected ? '#2563eb' : data?.relation.is_primary ? '#0f766e' : '#94a3b8';
  const strokeWidth = data?.isSelected ? 3 : data?.relation.is_primary ? 2.5 : 1.5;

  return (
    <>
      <BaseEdge
        id={id}
        path={path}
        markerEnd={markerEnd}
        style={{ stroke, strokeWidth }}
      />
      {showLabel ? (
        <EdgeLabelRenderer>
          <div
            className="rounded-full border border-slate-200 bg-background/95 px-2 py-1 text-[11px] font-medium text-slate-700 shadow-sm"
            style={{
              position: 'absolute',
              transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
              pointerEvents: 'none',
            }}
          >
            {data?.label}
          </div>
        </EdgeLabelRenderer>
      ) : null}
    </>
  );
}

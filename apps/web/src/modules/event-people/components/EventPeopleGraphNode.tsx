import type { NodeProps } from '@xyflow/react';
import { Handle, Position } from '@xyflow/react';

import { Badge } from '@/components/ui/badge';

import { formatEventPersonRoleFamily, formatEventPersonStatus } from '../labels';

import type { EventPeopleFlowNode } from './eventPeopleGraphFlow';

export function EventPeopleGraphNode({ data }: NodeProps<EventPeopleFlowNode>) {
  const { person, isSelected } = data;
  const initials = person.display_name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div
      className={`h-full rounded-[28px] border bg-background/95 p-4 shadow-sm transition ${
        isSelected ? 'border-primary shadow-[0_0_0_3px_rgba(59,130,246,0.16)]' : 'border-slate-200'
      }`}
      data-testid={`event-people-graph-node-${person.id}`}
    >
      <Handle type="target" position={Position.Top} className="opacity-0" />
      <div className="flex items-start gap-3">
        {person.avatar_url ? (
          <img
            src={person.avatar_url}
            alt={person.display_name}
            className="h-12 w-12 rounded-2xl object-cover"
            loading="lazy"
          />
        ) : (
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-700">
            {initials || 'PE'}
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-slate-950">{person.display_name}</p>
          <p className="truncate text-xs text-muted-foreground">{person.role_label}</p>
          <p className="mt-1 text-[11px] uppercase tracking-wide text-slate-500">{formatEventPersonRoleFamily(person.role_family)}</p>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Badge variant="outline">{person.media_count} fotos</Badge>
        {person.role_family === 'principal' ? <Badge variant="secondary">Principal</Badge> : null}
        {!person.has_primary_photo ? <Badge variant="destructive">Sem foto principal</Badge> : null}
        {person.status && person.status !== 'active' ? <Badge variant="outline">{formatEventPersonStatus(person.status)}</Badge> : null}
      </div>
      <Handle type="source" position={Position.Bottom} className="opacity-0" />
    </div>
  );
}

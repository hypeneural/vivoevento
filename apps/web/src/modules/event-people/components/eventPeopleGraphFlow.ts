import { MarkerType, type Edge, type Node } from '@xyflow/react';

import { formatEventPersonRelationType } from '../labels';
import type {
  EventPeopleGraphPerson,
  EventPeopleGraphRelation,
  EventPeopleGraphResponse,
} from '../types';

export interface EventPeopleGraphNodeData {
  person: EventPeopleGraphPerson;
  isSelected: boolean;
}

export interface EventPeopleGraphEdgeData {
  relation: EventPeopleGraphRelation;
  label: string;
  isSelected: boolean;
}

export type EventPeopleFlowNode = Node<EventPeopleGraphNodeData, 'person'>;
export type EventPeopleFlowEdge = Edge<EventPeopleGraphEdgeData, 'relation'>;

const FAMILY_X: Record<string, number> = {
  academico: -960,
  familia: -420,
  principal: 0,
  corte: 420,
  amigos: 840,
  corporativo: 420,
  equipe: 840,
  fornecedor: 1260,
  outros: 1260,
};

const SIDE_OFFSET: Record<string, number> = {
  bride_side: -90,
  groom_side: 90,
  host_side: -50,
  company_side: 50,
  neutral: 0,
};

const ROLE_ORDER = ['academico', 'familia', 'principal', 'corte', 'amigos', 'corporativo', 'equipe', 'fornecedor', 'outros'] as const;

export interface EventPeopleGraphLocalFilters {
  search?: string;
  status?: string;
  side?: string;
}

export interface EventPeopleFlowBuildResult {
  nodes: EventPeopleFlowNode[];
  edges: EventPeopleFlowEdge[];
  visiblePeople: EventPeopleGraphPerson[];
  visibleRelations: EventPeopleGraphRelation[];
}

export function buildEventPeopleFlow(
  graph: EventPeopleGraphResponse,
  filters: EventPeopleGraphLocalFilters,
  selectedPersonId: number | null,
  selectedRelationId: number | null,
): EventPeopleFlowBuildResult {
  const normalizedSearch = filters.search?.trim().toLowerCase() ?? '';

  const visiblePeople = graph.people
    .filter((person) => {
      if (filters.status && filters.status !== 'all' && person.status !== filters.status) return false;
      if (filters.side && filters.side !== 'all' && person.side !== filters.side) return false;
      if (!normalizedSearch) return true;

      return [
        person.display_name,
        person.role_label,
        person.type,
      ]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedSearch));
    })
    .sort((personA, personB) => {
      if (personB.importance_rank !== personA.importance_rank) return personB.importance_rank - personA.importance_rank;
      return personA.display_name.localeCompare(personB.display_name);
    });

  const visibleIds = new Set(visiblePeople.map((person) => person.id));
  const visibleRelations = graph.relations.filter(
    (relation) => visibleIds.has(relation.person_a_id) && visibleIds.has(relation.person_b_id),
  );

  const groupedPeople = new Map<string, EventPeopleGraphPerson[]>();
  visiblePeople.forEach((person) => {
    const family = person.role_family || 'outros';
    groupedPeople.set(family, [...(groupedPeople.get(family) ?? []), person]);
  });

  const positions = new Map<number, { x: number; y: number }>();
  ROLE_ORDER.forEach((family) => {
    const people = (groupedPeople.get(family) ?? []).sort((personA, personB) => {
      if (personB.importance_rank !== personA.importance_rank) return personB.importance_rank - personA.importance_rank;
      return personA.display_name.localeCompare(personB.display_name);
    });

    people.forEach((person, index) => {
      positions.set(person.id, {
        x: (FAMILY_X[family] ?? FAMILY_X.outros) + (SIDE_OFFSET[person.side ?? 'neutral'] ?? 0),
        y: index * 176 + (family === 'principal' ? 24 : 0),
      });
    });
  });

  return {
    nodes: visiblePeople.map((person) => ({
      id: String(person.id),
      type: 'person',
      position: positions.get(person.id) ?? { x: 0, y: 0 },
      draggable: false,
      selectable: true,
      focusable: true,
      selected: person.id === selectedPersonId,
      style: {
        width: 232,
        height: 132,
      },
      data: {
        person,
        isSelected: person.id === selectedPersonId,
      },
    })),
    edges: visibleRelations.map((relation) => ({
      id: String(relation.id),
      type: 'relation',
      source: String(relation.person_a_id),
      target: String(relation.person_b_id),
      selectable: true,
      focusable: true,
      animated: false,
      markerEnd: relation.directionality === 'directed'
        ? {
          type: MarkerType.ArrowClosed,
          color: '#94a3b8',
        }
        : undefined,
      data: {
        relation,
        label: formatEventPersonRelationType(relation.relation_type),
        isSelected: relation.id === selectedRelationId,
      },
    })),
    visiblePeople,
    visibleRelations,
  };
}

import type { ReactNode } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { EventPeopleGraphResponse } from '../types';
import { EventPeopleGraphView } from './EventPeopleGraphView';

const reactFlowPropsSpy = vi.fn();

vi.mock('@xyflow/react', () => ({
  Background: () => <div data-testid="event-people-graph-background" />,
  BaseEdge: ({ id }: { id: string }) => <div data-testid={`event-people-edge-${id}`} />,
  EdgeLabelRenderer: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  Handle: ({ id, type, position }: { id?: string; type: string; position: string }) => (
    <div data-testid={`event-people-handle-${id ?? type}-${position}`} />
  ),
  getSmoothStepPath: () => ['M 0,0 C 0,0 0,0 0,0', 120, 180],
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
      <div data-testid="event-people-react-flow-mock">
        <button
          type="button"
          onClick={() => (props.onNodeClick as ((event: unknown, node: { id: string }) => void) | undefined)?.({}, { id: '1' })}
        >
          Selecionar primeira pessoa
        </button>
        <button
          type="button"
          onClick={() => (props.onEdgeClick as ((event: unknown, edge: { id: string }) => void) | undefined)?.({}, { id: '31' })}
        >
          Selecionar primeira relacao
        </button>
        {props.children as ReactNode}
      </div>
    );
  },
}));

function makeGraph(): EventPeopleGraphResponse {
  return {
    people: [
      {
        id: 1,
        display_name: 'Noiva',
        role_key: 'bride',
        role_label: 'Noiva',
        role_family: 'principal',
        type: 'bride',
        side: 'neutral',
        status: 'active',
        avatar_url: null,
        importance_rank: 100,
        media_count: 8,
        published_media_count: 6,
        has_primary_photo: true,
      },
      {
        id: 2,
        display_name: 'Noivo',
        role_key: 'groom',
        role_label: 'Noivo',
        role_family: 'principal',
        type: 'groom',
        side: 'neutral',
        status: 'active',
        avatar_url: null,
        importance_rank: 100,
        media_count: 7,
        published_media_count: 5,
        has_primary_photo: false,
      },
    ],
    relations: [
      {
        id: 31,
        person_a_id: 1,
        person_b_id: 2,
        person_a_name: 'Noiva',
        person_b_name: 'Noivo',
        relation_type: 'spouse_of',
        directionality: 'undirected',
        source: 'manual',
        strength: 0.91,
        is_primary: true,
        notes: 'Casal principal',
        co_photo_count: 4,
      },
    ],
    groups: [
      {
        key: 'couple',
        label: 'Casal',
        role_family: 'principal',
        member_role_keys: ['bride', 'groom'],
        importance_rank: 100,
        current_member_count: 2,
      },
    ],
    stats: {
      people_count: 2,
      relation_count: 1,
      connected_people_count: 2,
      principal_people_count: 2,
      without_primary_photo_count: 1,
    },
    filters: {
      statuses: ['active'],
      sides: ['neutral'],
      role_families: ['principal'],
      relation_types: ['spouse_of'],
    },
  };
}

describe('EventPeopleGraphView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the complementary graph, opens node details and switches to relation details', async () => {
    const onOpenPerson = vi.fn();

    render(<EventPeopleGraphView graph={makeGraph()} onOpenPerson={onOpenPerson} />);

    expect(screen.getByText('Mapa de relacoes')).toBeInTheDocument();
    expect(screen.getByTestId('event-people-graph-sidebar-person')).toBeInTheDocument();
    expect(screen.getAllByText('Noiva').length).toBeGreaterThan(0);

    fireEvent.click(screen.getByRole('button', { name: 'Selecionar primeira relacao' }));

    await waitFor(() => {
      expect(screen.getByTestId('event-people-graph-sidebar-relation')).toBeInTheDocument();
    });
    expect(screen.getByText('Conjuge de')).toBeInTheDocument();
    expect(screen.getByText('4 fotos juntos')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Abrir Noiva' }));

    expect(onOpenPerson).toHaveBeenCalledWith(1);
  });

  it('filters visible nodes locally before sending them to React Flow', async () => {
    render(<EventPeopleGraphView graph={makeGraph()} />);

    const searchInput = screen.getByLabelText('Buscar no mapa');
    fireEvent.change(searchInput, { target: { value: 'Noivo' } });

    await waitFor(() => {
      const lastCall = reactFlowPropsSpy.mock.calls.at(-1)?.[0] as { nodes?: Array<{ id: string }>; edges?: Array<{ id: string }> } | undefined;
      expect(lastCall?.nodes).toHaveLength(1);
      expect(lastCall?.nodes?.[0]?.id).toBe('2');
      expect(lastCall?.edges).toHaveLength(0);
    });
  });
});

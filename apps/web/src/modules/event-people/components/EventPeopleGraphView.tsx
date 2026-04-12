import { useEffect, useMemo, useState } from 'react';
import { Background, ReactFlow } from '@xyflow/react';

import '@xyflow/react/dist/style.css';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import { formatEventPersonMeta, formatEventPersonRelationType, formatEventPersonRoleFamily, formatEventPersonStatus } from '../labels';
import type { EventPeopleGraphResponse } from '../types';

import { EventPeopleGraphEdge } from './EventPeopleGraphEdge';
import { EventPeopleGraphNode } from './EventPeopleGraphNode';
import {
  buildEventPeopleFlow,
  type EventPeopleFlowEdge,
  type EventPeopleFlowNode,
} from './eventPeopleGraphFlow';

const EVENT_PEOPLE_GRAPH_NODE_TYPES = {
  person: EventPeopleGraphNode,
} as const;

const EVENT_PEOPLE_GRAPH_EDGE_TYPES = {
  relation: EventPeopleGraphEdge,
} as const;

const EVENT_PEOPLE_GRAPH_ARIA_LABELS = {
  'node.a11yDescription.default': 'Pessoa focavel do mapa de relacoes. Use Enter para selecionar.',
  'edge.a11yDescription.default': 'Relacao focavel do mapa de relacoes.',
  'controls.ariaLabel': 'Controles do mapa de relacoes',
  'controls.fitView.ariaLabel': 'Centralizar o mapa de relacoes',
  'minimap.ariaLabel': 'Mini mapa de relacoes',
  'handle.ariaLabel': 'Handle do mapa de relacoes',
} as const;

interface EventPeopleGraphViewProps {
  graph: EventPeopleGraphResponse | null;
  isLoading?: boolean;
  isFetching?: boolean;
  initialSelectedPersonId?: number | null;
  onOpenPerson?: (personId: number) => void;
}

export function EventPeopleGraphView({
  graph,
  isLoading = false,
  isFetching = false,
  initialSelectedPersonId = null,
  onOpenPerson,
}: EventPeopleGraphViewProps) {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [sideFilter, setSideFilter] = useState('all');
  const [selectedPersonId, setSelectedPersonId] = useState<number | null>(initialSelectedPersonId);
  const [selectedRelationId, setSelectedRelationId] = useState<number | null>(null);

  useEffect(() => {
    if (!graph) return;
    if (initialSelectedPersonId && graph.people.some((person) => person.id === initialSelectedPersonId)) {
      setSelectedPersonId(initialSelectedPersonId);
      setSelectedRelationId(null);
      return;
    }

    if (selectedPersonId === null && selectedRelationId === null && graph.people[0]) {
      setSelectedPersonId(graph.people[0].id);
    }
  }, [graph, initialSelectedPersonId, selectedPersonId, selectedRelationId]);

  const flow = useMemo(() => {
    if (!graph) {
      return {
        nodes: [] as EventPeopleFlowNode[],
        edges: [] as EventPeopleFlowEdge[],
        visiblePeople: [],
        visibleRelations: [],
      };
    }

    return buildEventPeopleFlow(
      graph,
      { search, status: statusFilter, side: sideFilter },
      selectedPersonId,
      selectedRelationId,
    );
  }, [graph, search, selectedPersonId, selectedRelationId, sideFilter, statusFilter]);

  useEffect(() => {
    if (flow.visiblePeople.length === 0) {
      setSelectedPersonId(null);
      setSelectedRelationId(null);
      return;
    }

    if (selectedPersonId !== null && flow.visiblePeople.some((person) => person.id === selectedPersonId)) return;
    if (selectedRelationId !== null && flow.visibleRelations.some((relation) => relation.id === selectedRelationId)) return;

    setSelectedPersonId(flow.visiblePeople[0]?.id ?? null);
    setSelectedRelationId(null);
  }, [flow.visiblePeople, flow.visibleRelations, selectedPersonId, selectedRelationId]);

  const selectedPerson = flow.visiblePeople.find((person) => person.id === selectedPersonId) ?? null;
  const selectedRelation = flow.visibleRelations.find((relation) => relation.id === selectedRelationId) ?? null;
  const relatedPeople = selectedPerson
    ? flow.visibleRelations
      .filter((relation) => relation.person_a_id === selectedPerson.id || relation.person_b_id === selectedPerson.id)
      .map((relation) => ({
        relation,
        person: flow.visiblePeople.find((candidate) => candidate.id === (relation.person_a_id === selectedPerson.id ? relation.person_b_id : relation.person_a_id)) ?? null,
      }))
      .filter((entry) => entry.person)
    : [];

  if (isLoading && !graph) {
    return (
      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Mapa de relacoes</CardTitle>
          <CardDescription>Carregando o grafo complementar de pessoas e vinculos do evento.</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  if (!graph || graph.people.length === 0) {
    return (
      <Card className="border-dashed border-muted-foreground/30 bg-background/60 shadow-none">
        <CardHeader>
          <CardTitle className="text-base">Ainda nao foi possivel montar o mapa de relacoes</CardTitle>
          <CardDescription>
            O evento ainda nao tem pessoas suficientes para desenhar um mapa complementar.
          </CardDescription>
        </CardHeader>
      </Card>
    );
  }

  return (
    <div className="grid gap-4">
      <div className="grid gap-4 lg:grid-cols-4">
        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Pessoas no mapa</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-semibold">{graph.stats.people_count}</CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Relacoes mapeadas</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-semibold">{graph.stats.relation_count}</CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Pessoas principais</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-semibold">{graph.stats.principal_people_count}</CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm">Sem foto principal</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-semibold">{graph.stats.without_primary_photo_count}</CardContent>
        </Card>
      </div>

      <Card className="border-border/60">
        <CardHeader className="space-y-4">
          <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <CardTitle>Mapa de relacoes</CardTitle>
              <CardDescription>
                Vista complementar em `React Flow` para navegar por nucleos, pares fortes e pessoas principais.
              </CardDescription>
            </div>
            <div className="flex flex-wrap gap-2">
              {isFetching ? <Badge variant="secondary">Atualizando mapa</Badge> : null}
              <Badge variant="outline">{flow.visiblePeople.length} pessoas visiveis</Badge>
              <Badge variant="outline">{flow.visibleRelations.length} relacoes visiveis</Badge>
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="event-people-graph-search">Buscar no mapa</Label>
              <Input
                id="event-people-graph-search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Nome, papel ou tipo"
              />
            </div>
            <div className="space-y-2">
              <Label>Status</Label>
              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos</SelectItem>
                  {graph.filters.statuses.map((status) => (
                    <SelectItem key={status} value={status}>
                      {formatEventPersonStatus(status)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Lado</Label>
              <Select value={sideFilter} onValueChange={setSideFilter}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos os lados</SelectItem>
                  {graph.filters.sides.map((side) => (
                    <SelectItem key={side} value={side}>
                      {formatEventPersonMeta({ side }, side)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
          <div className="h-[760px] overflow-hidden rounded-[28px] border border-slate-200 bg-[linear-gradient(180deg,rgba(250,251,255,0.98),rgba(243,247,252,0.96))]">
            <ReactFlow<EventPeopleFlowNode, EventPeopleFlowEdge>
              nodes={flow.nodes}
              edges={flow.edges}
              nodeTypes={EVENT_PEOPLE_GRAPH_NODE_TYPES}
              edgeTypes={EVENT_PEOPLE_GRAPH_EDGE_TYPES}
              onNodeClick={(_, node) => {
                setSelectedPersonId(Number(node.id));
                setSelectedRelationId(null);
              }}
              onEdgeClick={(_, edge) => {
                setSelectedRelationId(Number(edge.id));
                setSelectedPersonId(null);
              }}
              onPaneClick={() => {
                setSelectedPersonId(null);
                setSelectedRelationId(null);
              }}
              onSelectionChange={({ nodes, edges }) => {
                if (nodes[0]) {
                  setSelectedPersonId(Number(nodes[0].id));
                  setSelectedRelationId(null);
                  return;
                }

                if (edges[0]) {
                  setSelectedRelationId(Number(edges[0].id));
                  setSelectedPersonId(null);
                }
              }}
              nodesDraggable={false}
              nodesConnectable={false}
              elementsSelectable
              nodesFocusable
              edgesFocusable
              deleteKeyCode={null}
              selectionOnDrag={false}
              selectNodesOnDrag={false}
              zoomOnDoubleClick={false}
              fitView
              fitViewOptions={{ padding: 0.08, duration: 250 }}
              ariaLabelConfig={EVENT_PEOPLE_GRAPH_ARIA_LABELS}
              className="h-full w-full"
            >
              <Background gap={24} size={1} color="#cbd5e1" />
            </ReactFlow>
          </div>

          <div className="space-y-4">
            {selectedPerson ? (
              <Card className="border-border/60" data-testid="event-people-graph-sidebar-person">
                <CardHeader>
                  <CardTitle>{selectedPerson.display_name}</CardTitle>
                  <CardDescription>{selectedPerson.role_label}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  {selectedPerson.avatar_url ? (
                    <img
                      src={selectedPerson.avatar_url}
                      alt={selectedPerson.display_name}
                      className="h-28 w-full rounded-3xl object-cover"
                      loading="lazy"
                    />
                  ) : null}
                  <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{formatEventPersonRoleFamily(selectedPerson.role_family)}</Badge>
                    <Badge variant="outline">{selectedPerson.media_count} fotos</Badge>
                    {!selectedPerson.has_primary_photo ? <Badge variant="destructive">Sem foto principal</Badge> : null}
                  </div>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl border border-border/60 bg-background px-3 py-3">
                      <p className="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                      <p className="mt-1 font-medium">{formatEventPersonStatus(selectedPerson.status)}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background px-3 py-3">
                      <p className="text-xs uppercase tracking-wide text-muted-foreground">Publicadas</p>
                      <p className="mt-1 font-medium">{selectedPerson.published_media_count}</p>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <p className="text-sm font-medium">Relacoes desta pessoa</p>
                    {relatedPeople.length === 0 ? (
                      <div className="rounded-2xl border border-dashed border-border/60 px-4 py-4 text-sm text-muted-foreground">
                        Nenhum vinculo visivel com os filtros atuais.
                      </div>
                    ) : (
                      relatedPeople.map(({ relation, person }) => (
                        <div key={relation.id} className="rounded-2xl border border-border/60 bg-background px-4 py-3">
                          <p className="font-medium">{person?.display_name}</p>
                          <p className="text-xs text-muted-foreground">{formatEventPersonRelationType(relation.relation_type)}</p>
                        </div>
                      ))
                    )}
                  </div>
                  <Button type="button" onClick={() => onOpenPerson?.(selectedPerson.id)}>
                    Abrir ficha completa
                  </Button>
                </CardContent>
              </Card>
            ) : null}

            {selectedRelation ? (
              <Card className="border-border/60" data-testid="event-people-graph-sidebar-relation">
                <CardHeader>
                  <CardTitle>{formatEventPersonRelationType(selectedRelation.relation_type)}</CardTitle>
                  <CardDescription>
                    {selectedRelation.person_a_name ?? 'Pessoa A'} com {selectedRelation.person_b_name ?? 'Pessoa B'}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2">
                    <Badge variant={selectedRelation.is_primary ? 'secondary' : 'outline'}>
                      {selectedRelation.is_primary ? 'Relacao principal' : 'Relacao manual'}
                    </Badge>
                    <Badge variant="outline">
                      {selectedRelation.co_photo_count ?? 0} fotos juntos
                    </Badge>
                  </div>
                  <div className="grid gap-3">
                    <div className="rounded-2xl border border-border/60 bg-background px-3 py-3">
                      <p className="text-xs uppercase tracking-wide text-muted-foreground">Direcao</p>
                      <p className="mt-1 font-medium">{selectedRelation.directionality}</p>
                    </div>
                    <div className="rounded-2xl border border-border/60 bg-background px-3 py-3">
                      <p className="text-xs uppercase tracking-wide text-muted-foreground">Origem</p>
                      <p className="mt-1 font-medium">{selectedRelation.source ?? 'manual'}</p>
                    </div>
                    {selectedRelation.notes ? (
                      <div className="rounded-2xl border border-border/60 bg-background px-3 py-3 text-sm text-muted-foreground">
                        {selectedRelation.notes}
                      </div>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenPerson?.(selectedRelation.person_a_id)}>
                      Abrir {selectedRelation.person_a_name ?? 'Pessoa A'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => onOpenPerson?.(selectedRelation.person_b_id)}>
                      Abrir {selectedRelation.person_b_name ?? 'Pessoa B'}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ) : null}

            {!selectedPerson && !selectedRelation ? (
              <Card className="border-border/60">
                <CardHeader>
                  <CardTitle>Selecione uma pessoa ou relacao</CardTitle>
                  <CardDescription>
                    O painel lateral mostra detalhe rapido do node ou da conexao selecionada.
                  </CardDescription>
                </CardHeader>
              </Card>
            ) : null}

            <Card className="border-border/60">
              <CardHeader>
                <CardTitle>Grupos sementes</CardTitle>
                <CardDescription>
                  Seeds do modelo do evento que vao alimentar grupos e coverage nas proximas fases.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-2">
                {graph.groups.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-border/60 px-4 py-4 text-sm text-muted-foreground">
                    Nenhum grupo semente definido para este modelo.
                  </div>
                ) : (
                  graph.groups.map((group) => (
                    <div key={group.key} className="rounded-2xl border border-border/60 bg-background px-4 py-3">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="font-medium">{group.label}</p>
                          <p className="text-xs text-muted-foreground">{formatEventPersonRoleFamily(group.role_family)}</p>
                        </div>
                        <Badge variant="outline">{group.current_member_count} no mapa</Badge>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
  AlertTriangle,
  GitBranch,
  Info,
  Loader2,
  PanelRightOpen,
  RefreshCcw,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from '@/components/ui/resizable';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/shared/components/PageHeader';

import { eventJourneyBuilderQueryOptions } from '../journey/api';
import type { JourneyGraphNode } from '../journey/buildJourneyGraph';
import { buildJourneyGraph } from '../journey/buildJourneyGraph';
import { buildJourneyScenarios } from '../journey/buildJourneyScenarios';
import { buildJourneySummary } from '../journey/buildJourneySummary';
import {
  JourneyFlowCanvas,
  type JourneyFlowCanvasControls,
} from '../journey/JourneyFlowCanvas';
import type {
  EventJourneyBuiltScenario,
  EventJourneyProjection,
  EventJourneyStageId,
} from '../journey/types';

const EMPTY_GRAPH = {
  stages: [],
  nodes: [],
  edges: [],
} as const;

const STAGE_LEGEND: Array<{
  id: EventJourneyStageId;
  label: string;
  badgeClassName: string;
}> = [
  {
    id: 'entry',
    label: 'Entrada',
    badgeClassName: 'border-sky-200 bg-sky-100 text-sky-800',
  },
  {
    id: 'processing',
    label: 'Processamento',
    badgeClassName: 'border-violet-200 bg-violet-100 text-violet-800',
  },
  {
    id: 'decision',
    label: 'Decisao',
    badgeClassName: 'border-amber-200 bg-amber-100 text-amber-800',
  },
  {
    id: 'output',
    label: 'Saida',
    badgeClassName: 'border-emerald-200 bg-emerald-100 text-emerald-800',
  },
];

function getErrorMessage(error: unknown) {
  const status = typeof error === 'object' && error !== null && 'status' in error
    ? (error as { status?: number }).status
    : undefined;

  if (status === 403) {
    return 'Voce nao tem permissao para visualizar a jornada deste evento.';
  }

  return 'Nao foi possivel carregar a jornada da midia.';
}

function JourneySummaryCard({
  projection,
  scenarios,
  selectedScenario,
  onScenarioSelect,
}: {
  projection: EventJourneyProjection;
  scenarios: EventJourneyBuiltScenario[];
  selectedScenario: EventJourneyBuiltScenario | null;
  onScenarioSelect: (scenario: EventJourneyBuiltScenario | null) => void;
}) {
  const summaryText = buildJourneySummary(projection, selectedScenario);

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="space-y-3 pb-3">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">Modo simples</Badge>
          <Badge variant="outline">{projection.version}</Badge>
        </div>
        <div className="space-y-1">
          <CardTitle className="text-base">Resumo humano da jornada</CardTitle>
          <CardDescription>
            O fluxo visual continua preso ao agregado real do evento. O resumo traduz esse caminho em linguagem operacional.
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <p className="text-sm leading-6 text-foreground">{summaryText}</p>

        <div className="space-y-2">
          <div className="flex items-center gap-2 text-sm font-medium text-foreground">
            <GitBranch className="h-4 w-4 text-primary" />
            Cenarios prontos
          </div>
          <div className="flex flex-wrap gap-2">
            {scenarios.map((scenario) => {
              const isActive = selectedScenario?.id === scenario.id;

              return (
                <Button
                  key={scenario.id}
                  type="button"
                  size="sm"
                  variant={isActive ? 'default' : 'outline'}
                  disabled={!scenario.available}
                  title={scenario.available ? scenario.description : scenario.unavailableReason ?? undefined}
                  onClick={() => onScenarioSelect(isActive ? null : scenario)}
                >
                  {scenario.label}
                </Button>
              );
            })}
          </div>
          {selectedScenario?.unavailableReason ? (
            <p className="text-xs text-muted-foreground">{selectedScenario.unavailableReason}</p>
          ) : null}
        </div>
      </CardContent>
    </Card>
  );
}

function JourneyInspectorShell({
  projection,
  selectedNode,
  scenarios,
  technicalDetailsOpen,
}: {
  projection: EventJourneyProjection;
  selectedNode: JourneyGraphNode | null;
  scenarios: EventJourneyBuiltScenario[];
  technicalDetailsOpen: boolean;
}) {
  return (
    <div className="flex h-full flex-col gap-4">
      {selectedNode ? (
        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">{selectedNode.data.node.label}</CardTitle>
            <CardDescription>
              {selectedNode.data.node.description}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <div className="flex flex-wrap gap-2">
              <Badge variant="secondary">{selectedNode.data.stage.label}</Badge>
              <Badge variant="outline">{selectedNode.data.node.editable ? 'Editavel' : 'Tecnico'}</Badge>
              <Badge variant="outline">{selectedNode.data.node.status}</Badge>
            </div>

            <p className="leading-6 text-foreground/90">{selectedNode.data.node.summary}</p>

            <div className="grid gap-3">
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Branches</p>
                <p className="mt-2">{selectedNode.data.node.branches.length} saida(s) configurada(s).</p>
              </div>

              {selectedNode.data.node.warnings.length > 0 ? (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-3">
                  <p className="text-xs font-medium uppercase tracking-[0.16em] text-amber-800">Warnings</p>
                  <ul className="mt-2 space-y-2 text-sm text-amber-900">
                    {selectedNode.data.node.warnings.map((warning) => (
                      <li key={warning} className="flex gap-2">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{warning}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-dashed border-muted-foreground/30 bg-background/60 shadow-none">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Nenhuma etapa selecionada</CardTitle>
            <CardDescription>
              Selecione um no no fluxo para abrir o inspector lateral e revisar o resumo dessa etapa.
            </CardDescription>
          </CardHeader>
        </Card>
      )}

      {technicalDetailsOpen ? (
        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Detalhes tecnicos da projection</CardTitle>
            <CardDescription>
              Dados de apoio para QA e para o encaixe futuro do inspector editavel.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Versao</p>
                <p className="mt-2 font-mono text-sm">{projection.version}</p>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Resumo</p>
                <p className="mt-2">{projection.summary.human_text}</p>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Warnings</p>
                <p className="mt-2 font-mono text-sm">{projection.warnings.length}</p>
              </div>
              <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Cenarios prontos</p>
                <p className="mt-2 font-mono text-sm">{scenarios.length}</p>
              </div>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
              <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Warnings ativos</p>
              {projection.warnings.length > 0 ? (
                <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                  {projection.warnings.map((warning) => (
                    <li key={warning} className="flex gap-2">
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                      <span>{warning}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="mt-2 text-muted-foreground">Nenhum warning ativo nesta projection.</p>
              )}
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}

export default function EventJourneyBuilderPage() {
  const { id } = useParams<{ id: string }>();
  const canvasControlsRef = useRef<JourneyFlowCanvasControls | null>(null);
  const [technicalDetailsOpen, setTechnicalDetailsOpen] = useState(false);
  const [selectedScenarioId, setSelectedScenarioId] = useState<string | null>(null);
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);

  const journeyQuery = useQuery({
    ...eventJourneyBuilderQueryOptions(id ?? 'missing-event'),
    enabled: Boolean(id),
  });

  const projection = journeyQuery.data ?? null;
  const graph = useMemo(
    () => (projection ? buildJourneyGraph(projection) : EMPTY_GRAPH),
    [projection],
  );
  const scenarios = useMemo(
    () => (projection ? buildJourneyScenarios(projection, graph) : []),
    [projection, graph],
  );
  const selectedScenario = useMemo(
    () => scenarios.find((scenario) => scenario.id === selectedScenarioId) ?? null,
    [scenarios, selectedScenarioId],
  );
  const selectedNode = useMemo(
    () => graph.nodes.find((node) => node.id === selectedNodeId) ?? null,
    [graph.nodes, selectedNodeId],
  );

  useEffect(() => {
    if (!selectedNodeId) {
      return;
    }

    if (!graph.nodes.some((node) => node.id === selectedNodeId)) {
      setSelectedNodeId(null);
    }
  }, [graph.nodes, selectedNodeId]);

  useEffect(() => {
    if (!selectedScenarioId) {
      return;
    }

    if (!scenarios.some((scenario) => scenario.id === selectedScenarioId)) {
      setSelectedScenarioId(null);
    }
  }, [scenarios, selectedScenarioId]);

  if (!id) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-destructive">
        Evento invalido para abrir a jornada da midia.
      </div>
    );
  }

  if (journeyQuery.isPending) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <div className="flex items-center gap-3 text-sm text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin text-primary" />
          Carregando jornada da midia...
        </div>
      </div>
    );
  }

  if (journeyQuery.isError || !journeyQuery.data) {
    return (
      <Card className="border-destructive/30 bg-destructive/5">
        <CardHeader>
          <CardTitle className="text-base text-destructive">Falha ao carregar a jornada</CardTitle>
          <CardDescription className="text-destructive/80">
            {getErrorMessage(journeyQuery.error)}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button type="button" variant="outline" onClick={() => void journeyQuery.refetch()}>
            <RefreshCcw className="mr-2 h-4 w-4" />
            Tentar novamente
          </Button>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Jornada da midia"
        description="Como o evento trata cada foto ou video recebido"
        actions={(
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">Builder guiado</Badge>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => canvasControlsRef.current?.fitView()}
            >
              <RefreshCcw className="mr-1.5 h-3.5 w-3.5" />
              Centralizar fluxo
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              aria-pressed={technicalDetailsOpen}
              onClick={() => setTechnicalDetailsOpen((current) => !current)}
            >
              <PanelRightOpen className="mr-1.5 h-3.5 w-3.5" />
              Ver detalhes tecnicos
            </Button>
            <Button variant="outline" size="sm" asChild>
              <Link to={`/events/${id}`}>Voltar ao evento</Link>
            </Button>
          </div>
        )}
      />

      <JourneySummaryCard
        projection={projection}
        scenarios={scenarios}
        selectedScenario={selectedScenario}
        onScenarioSelect={(scenario) => setSelectedScenarioId(scenario?.id ?? null)}
      />

      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center gap-2">
            <Info className="h-4 w-4 text-primary" />
            <CardTitle className="text-base">Legenda fixa da jornada</CardTitle>
          </div>
          <CardDescription>
            A leitura segue sempre o mesmo processo de negocio: entrada, processamento, decisao e saida.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap gap-2">
            {STAGE_LEGEND.map((stage) => (
              <Badge key={stage.id} className={cn('border', stage.badgeClassName)}>
                {stage.label}
              </Badge>
            ))}
          </div>
        </CardContent>
      </Card>

      <div className="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-sm">
        <ResizablePanelGroup direction="horizontal" className="min-h-[920px]">
          <ResizablePanel defaultSize={68} minSize={55}>
            <div className="h-full p-5">
              <div className="mb-4 flex items-center gap-2">
                <GitBranch className="h-4 w-4 text-primary" />
                <div>
                  <h2 className="text-base font-semibold text-foreground">Fluxo visual guiado</h2>
                  <p className="text-sm text-muted-foreground">
                    O renderer agora usa React Flow com canvas travado, mantendo a projection como fonte de verdade.
                  </p>
                </div>
              </div>

              <JourneyFlowCanvas
                graph={graph}
                selectedNodeId={selectedNodeId}
                highlightedNodeIds={selectedScenario?.highlightedNodeIds ?? []}
                highlightedEdgeIds={selectedScenario?.highlightedEdgeIds ?? []}
                onSelectedNodeIdChange={setSelectedNodeId}
                onReady={(controls) => {
                  canvasControlsRef.current = controls;
                }}
              />
            </div>
          </ResizablePanel>
          <ResizableHandle withHandle />
          <ResizablePanel defaultSize={32} minSize={25}>
            <div className="h-full bg-slate-50/80 p-5">
              <JourneyInspectorShell
                projection={projection}
                selectedNode={selectedNode}
                scenarios={scenarios}
                technicalDetailsOpen={technicalDetailsOpen}
              />
            </div>
          </ResizablePanel>
        </ResizablePanelGroup>
      </div>
    </div>
  );
}

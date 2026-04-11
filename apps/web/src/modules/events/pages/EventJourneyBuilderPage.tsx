import { useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
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
import { useIsMobile } from '@/hooks/use-mobile';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/shared/components/PageHeader';

import {
  eventJourneyBuilderQueryOptions,
  invalidateEventJourneyBuilderQueries,
  updateEventJourneyBuilder,
} from '../journey/api';
import { buildJourneyGraph } from '../journey/buildJourneyGraph';
import { buildJourneyScenarios } from '../journey/buildJourneyScenarios';
import { buildJourneySummary } from '../journey/buildJourneySummary';
import {
  buildJourneyTemplatePreview,
  type JourneyTemplateId,
} from '../journey/buildJourneyTemplatePreview';
import {
  JourneyFlowCanvas,
  type JourneyFlowCanvasControls,
} from '../journey/JourneyFlowCanvas';
import { JourneyInspector } from '../journey/JourneyInspector';
import { humanizeJourneyText } from '../journey/journeyCopy';
import { JourneyScenarioSimulator } from '../journey/JourneyScenarioSimulator';
import { JourneyTemplateRail } from '../journey/JourneyTemplateRail';
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
          <Badge variant="secondary">Edicao simplificada</Badge>
          <Badge variant="outline">Visao resumida</Badge>
        </div>
        <div className="space-y-1">
          <CardTitle className="text-base">Resumo humano da jornada</CardTitle>
          <CardDescription>
            Veja em poucas linhas como uma foto ou video passam pelas etapas do evento.
          </CardDescription>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <p className="text-sm leading-6 text-foreground">{humanizeJourneyText(summaryText)}</p>

        <div className="space-y-2">
          <JourneyScenarioSimulator
            scenarios={scenarios}
            selectedScenario={selectedScenario}
            onScenarioSelect={onScenarioSelect}
          />
        </div>
      </CardContent>
    </Card>
  );
}

export default function EventJourneyBuilderPage() {
  const { id } = useParams<{ id: string }>();
  const canvasControlsRef = useRef<JourneyFlowCanvasControls | null>(null);
  const queryClient = useQueryClient();
  const isMobile = useIsMobile();
  const { toast } = useToast();
  const [technicalDetailsOpen, setTechnicalDetailsOpen] = useState(false);
  const [selectedScenarioId, setSelectedScenarioId] = useState<string | null>(null);
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const [activeTemplateId, setActiveTemplateId] = useState<JourneyTemplateId | null>(null);

  const journeyQuery = useQuery({
    ...eventJourneyBuilderQueryOptions(id ?? 'missing-event'),
    enabled: Boolean(id),
  });

  const projection = journeyQuery.data ?? null;
  const activeTemplatePreview = useMemo(
    () => (projection && activeTemplateId ? buildJourneyTemplatePreview(projection, activeTemplateId) : null),
    [activeTemplateId, projection],
  );
  const effectiveProjection = activeTemplatePreview?.previewProjection ?? projection;
  const templateSaveMutation = useMutation({
    mutationFn: async () => {
      if (!activeTemplatePreview) {
        throw new Error('Nenhum modelo foi aplicado ao rascunho local.');
      }

      return updateEventJourneyBuilder(id, activeTemplatePreview.payload);
    },
    onSuccess: async (updatedProjection) => {
      queryClient.setQueryData(eventJourneyBuilderQueryOptions(id).queryKey, updatedProjection);
      await invalidateEventJourneyBuilderQueries(queryClient, id);
      setActiveTemplateId(null);
      toast({
        title: 'Modelo salvo',
        description: 'A jornada foi atualizada com o modelo escolhido.',
      });
    },
    onError: (error) => {
      const message = error instanceof Error ? error.message : 'Nao foi possivel salvar o modelo da jornada.';

      toast({
        title: 'Falha ao salvar modelo',
        description: message,
        variant: 'destructive',
      });
    },
  });
  const graph = useMemo(
    () => (effectiveProjection ? buildJourneyGraph(effectiveProjection) : EMPTY_GRAPH),
    [effectiveProjection],
  );
  const scenarios = useMemo(
    () => (effectiveProjection ? buildJourneyScenarios(effectiveProjection, graph) : []),
    [effectiveProjection, graph],
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
            <Badge variant="secondary">Edicao simplificada</Badge>
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
              Ver detalhes da configuracao
            </Button>
            <Button variant="outline" size="sm" asChild>
              <Link to={`/events/${id}`}>Voltar ao evento</Link>
            </Button>
          </div>
        )}
      />

      <JourneySummaryCard
        projection={effectiveProjection}
        scenarios={scenarios}
        selectedScenario={selectedScenario}
        onScenarioSelect={(scenario) => setSelectedScenarioId(scenario?.id ?? null)}
      />

      <JourneyTemplateRail
        projection={projection}
        activeTemplatePreview={activeTemplatePreview}
        isPending={templateSaveMutation.isPending}
        onApplyTemplate={(templateId) => setActiveTemplateId(templateId)}
        onDiscardTemplate={() => setActiveTemplateId(null)}
        onSaveTemplate={() => templateSaveMutation.mutate()}
      />

      <Card className="border-white/70 bg-white/90 shadow-sm">
        <CardHeader className="pb-3">
          <div className="flex items-center gap-2">
            <Info className="h-4 w-4 text-primary" />
            <CardTitle className="text-base">Como ler este mapa</CardTitle>
          </div>
          <CardDescription>
            A leitura segue sempre a mesma ordem: chegada da midia, tratamento, decisao e destino final.
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

      {isMobile ? (
        <div className="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-sm">
          <div className="p-5">
            <div className="mb-4 flex items-center gap-2">
              <GitBranch className="h-4 w-4 text-primary" />
              <div>
                <h2 className="text-base font-semibold text-foreground">Mapa visual da jornada</h2>
                <p className="text-sm text-muted-foreground">
                  Veja cada etapa em ordem, da chegada da midia ate o que acontece com ela no final.
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

          <JourneyInspector
            mode="drawer"
            open={Boolean(selectedNode || selectedScenario)}
            onOpenChange={(open) => {
              if (!open) {
                setSelectedNodeId(null);
                setSelectedScenarioId(null);
              }
            }}
            eventId={id}
            projection={effectiveProjection}
            selectedNode={selectedNode}
            selectedScenario={selectedScenario}
            onClearScenario={() => setSelectedScenarioId(null)}
            scenarios={scenarios}
            technicalDetailsOpen={technicalDetailsOpen}
            templateDraftPreview={activeTemplatePreview}
          />
        </div>
      ) : (
        <div className="overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-sm">
          <ResizablePanelGroup direction="horizontal" className="min-h-[1660px]">
            <ResizablePanel defaultSize={78} minSize={62}>
              <div className="h-full p-5">
                <div className="mb-4 flex items-center gap-2">
                  <GitBranch className="h-4 w-4 text-primary" />
                  <div>
                    <h2 className="text-base font-semibold text-foreground">Mapa visual da jornada</h2>
                    <p className="text-sm text-muted-foreground">
                      Veja cada etapa em ordem, da chegada da midia ate o que acontece com ela no final.
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
            <ResizablePanel defaultSize={22} minSize={20}>
              <JourneyInspector
                mode="panel"
                eventId={id}
                projection={effectiveProjection}
                selectedNode={selectedNode}
                selectedScenario={selectedScenario}
                onClearScenario={() => setSelectedScenarioId(null)}
                scenarios={scenarios}
                technicalDetailsOpen={technicalDetailsOpen}
                templateDraftPreview={activeTemplatePreview}
              />
            </ResizablePanel>
          </ResizablePanelGroup>
        </div>
      )}
    </div>
  );
}

import { Image as ImageIcon, Loader2 } from 'lucide-react';

import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import type { ApiWallSimulationPreviewItem, ApiWallSimulationResponse } from '@/lib/api-types';

import { getWallSourceMeta } from '../../../wall-source-meta';

interface WallUpcomingTimelineProps {
  selectionSummary: string;
  simulationSummary: ApiWallSimulationResponse['summary'] | null;
  simulationPreview: ApiWallSimulationPreviewItem[];
  simulationExplanation: string[];
  isLoading: boolean;
  isError: boolean;
  isRefreshing: boolean;
  isDraftPending: boolean;
}

export function WallUpcomingTimeline({
  selectionSummary,
  simulationSummary,
  simulationPreview,
  simulationExplanation,
  isLoading,
  isError,
  isRefreshing,
  isDraftPending,
}: WallUpcomingTimelineProps) {
  return (
    <div className="space-y-4">
      <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-2">
            <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Resumo da politica</p>
            <p className="text-sm leading-relaxed text-foreground/90">{selectionSummary}</p>
          </div>
          <span className={`rounded-full border px-3 py-1 text-[11px] font-medium ${isDraftPending || isRefreshing ? 'border-amber-500/30 bg-amber-500/10 text-amber-700' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'}`}>
            {isDraftPending || isRefreshing ? 'Atualizando' : 'Fila real'}
          </span>
        </div>
      </div>

      {isLoading && !simulationSummary ? (
        <div className="flex min-h-[120px] items-center justify-center rounded-2xl border border-dashed border-border/60 bg-muted/20 text-sm text-muted-foreground">
          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          Calculando a proxima ordem de exibicao...
        </div>
      ) : null}

      {!isLoading && isError ? (
        <div className="rounded-2xl border border-destructive/20 bg-destructive/5 px-4 py-4 text-sm text-destructive">
          Nao foi possivel gerar a simulacao com a fila atual do evento.
        </div>
      ) : null}

      {!isLoading && !isError && simulationSummary ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2">
            <MetricCard
              label="ETA media da primeira aparicao"
              value={formatDurationSeconds(simulationSummary.estimated_first_appearance_seconds)}
              detail={`${simulationSummary.active_senders} remetentes ativos na amostra atual.`}
            />
            <MetricCard
              label="Risco de monopolizacao"
              value={formatLevelLabel(simulationSummary.monopolization_risk)}
              detail={`Modo ${simulationSummary.selection_mode_label.toLowerCase()} em ${simulationSummary.event_phase_label.toLowerCase()} com ${simulationSummary.queue_items} itens na fila real.`}
            />
            <MetricCard
              label="Intensidade do frescor"
              value={formatLevelLabel(simulationSummary.freshness_intensity)}
              detail="Quanto o telao tende a parecer realmente ao vivo com a configuracao atual."
            />
            <MetricCard
              label="Nivel de fairness"
              value={formatLevelLabel(simulationSummary.fairness_level)}
              detail="Quanto a fila protege contra monopolizacao por remetente."
            />
          </div>

          <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
            <div className="flex items-center justify-between gap-3">
              <div className="space-y-1">
                <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">
                  Ordem mais provavel das proximas {simulationPreview.length} exibicoes
                </p>
                <p className="text-xs text-muted-foreground">
                  Arraste a timeline para o lado para acompanhar a fila prevista em sequencia.
                </p>
              </div>
              <span className="rounded-full border border-border/60 bg-background px-3 py-1 text-[11px] text-muted-foreground">
                {simulationPreview.length} itens
              </span>
            </div>

            <ScrollArea className="mt-4 w-full" aria-label="Timeline horizontal das proximas exibicoes">
              <div className="flex gap-4 pb-4">
                {simulationPreview.map((slide, index) => {
                  const sourceMeta = getWallSourceMeta(slide.source_type ?? 'whatsapp');

                  return (
                    <div key={`${slide.position}-${slide.item_id}`} className="relative min-w-[272px] max-w-[272px] flex-none">
                      <div className="rounded-2xl border border-border/60 bg-background/80 p-3 shadow-sm">
                        <div className="flex items-center gap-2">
                          <span className="inline-flex rounded-full border border-border/60 bg-background px-2.5 py-1 text-[11px] font-medium text-muted-foreground">
                            {slide.eta_seconds}s
                          </span>
                          <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium ${sourceMeta.chipClassName}`}>
                            <sourceMeta.Icon className="h-3.5 w-3.5" />
                            {sourceMeta.label}
                          </span>
                        </div>

                        <div className="mt-3 overflow-hidden rounded-2xl border border-border/50 bg-muted/20">
                          <div className="aspect-[4/3]">
                            {slide.preview_url ? (
                              <img
                                src={slide.preview_url}
                                alt={`Miniatura da proxima foto de ${slide.sender_name}`}
                                className="h-full w-full object-cover"
                              />
                            ) : (
                              <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                                <ImageIcon className="h-4 w-4" />
                              </div>
                            )}
                          </div>
                        </div>

                        <div className="mt-3 space-y-2">
                          <div className="space-y-1">
                            <p className="truncate text-sm font-semibold text-foreground">{slide.sender_name}</p>
                            <p className="text-xs text-muted-foreground">
                              Posicao {slide.position} da fila prevista para o telao.
                            </p>
                            {slide.caption ? (
                              <p className="line-clamp-2 text-xs leading-relaxed text-foreground/80">
                                {slide.caption}
                              </p>
                            ) : null}
                          </div>

                          <div className="flex flex-wrap gap-2">
                            {slide.layout_hint ? (
                              <span className="inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-[11px] font-medium text-sky-700">
                                Layout {formatLayoutHintLabel(slide.layout_hint)}
                              </span>
                            ) : null}
                            {slide.is_replay ? (
                              <span className="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-700">
                                Reprise
                              </span>
                            ) : null}
                            {slide.is_featured ? (
                              <span className="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-medium text-emerald-700">
                                Destaque
                              </span>
                            ) : null}
                          </div>
                        </div>
                      </div>

                      {index < simulationPreview.length - 1 ? (
                        <div className="pointer-events-none absolute left-full top-8 hidden h-px w-4 border-t border-dashed border-border/70 md:block" />
                      ) : null}
                    </div>
                  );
                })}
              </div>
              <ScrollBar orientation="horizontal" />
            </ScrollArea>
          </div>

          {simulationExplanation.length > 0 ? (
            <div className="space-y-2 rounded-2xl border border-border/60 bg-muted/20 p-4">
              {simulationExplanation.map((line) => (
                <p key={line} className="text-sm leading-relaxed text-muted-foreground">{formatSimulationExplanation(line)}</p>
              ))}
            </div>
          ) : null}
        </>
      ) : null}

      {!isLoading && !isError && !simulationSummary ? (
        <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-sm text-muted-foreground">
          Ajuste as configuracoes do telao para ver a previsao com a fila atual do evento.
        </div>
      ) : null}
    </div>
  );
}

function MetricCard({
  label,
  value,
  detail,
}: {
  label: string;
  value: string;
  detail: string;
}) {
  return (
    <div className="rounded-2xl border border-border/60 bg-background/60 p-4">
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
      <p className="mt-2 text-lg font-semibold">{value}</p>
      <p className="mt-1 text-xs leading-relaxed text-muted-foreground">{detail}</p>
    </div>
  );
}

function formatDurationSeconds(value?: number | null) {
  if (value == null) {
    return 'Sem dado';
  }

  if (value < 60) {
    return `${value}s`;
  }

  const minutes = Math.floor(value / 60);
  const seconds = value % 60;

  return `${minutes}m ${seconds}s`;
}

function formatLevelLabel(value: 'low' | 'medium' | 'high') {
  if (value === 'high') return 'Alta';
  if (value === 'medium') return 'Media';
  return 'Baixa';
}

function formatSimulationExplanation(line: string) {
  return line
    .replace(
      'a simulacao usou a fila real atual do evento com o draft das configuracoes do wall.',
      'a previsao usou a fila atual do evento com as configuracoes que voce esta ajustando no telao.',
    )
    .replace(/\bsimulacao\b/gi, 'previsao')
    .replace(/\bdraft das configuracoes do wall\b/gi, 'configuracoes que voce esta ajustando no telao')
    .replace(/\bdraft\b/gi, 'rascunho')
    .replace(/\bwall\b/gi, 'telao')
    .replace(/\bselector\b/gi, 'organizador da fila')
    .replace(/\breplay\b/gi, 'reprise');
}

function formatLayoutHintLabel(value: NonNullable<ApiWallSimulationPreviewItem['layout_hint']>) {
  switch (value) {
    case 'cinematic':
      return 'Cinematografico';
    case 'fullscreen':
      return 'Tela cheia';
    case 'split':
      return 'Tela dividida';
    case 'polaroid':
      return 'Polaroid';
    case 'kenburns':
      return 'Ken Burns';
    case 'spotlight':
      return 'Holofote';
    case 'gallery':
      return 'Galeria de arte';
    case 'carousel':
      return 'Carrossel';
    case 'mosaic':
      return 'Mosaico';
    case 'grid':
      return 'Grade';
    default:
      return value;
  }
}

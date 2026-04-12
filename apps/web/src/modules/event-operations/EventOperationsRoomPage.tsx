import { useRef } from 'react';
import { useParams } from 'react-router-dom';

import { EVENT_OPERATIONS_SCHEMA_VERSION } from '@eventovivo/shared-types/event-operations';

import { OperationsAlertStack } from './components/OperationsAlertStack';
import { OperationsFullscreenEntryOverlay } from './components/OperationsFullscreenEntryOverlay';
import { OperationsHud } from './components/OperationsHud';
import { OperationsRoomCanvas } from './components/OperationsRoomCanvas';
import { OperationsStatusPill } from './components/OperationsStatusPill';
import { OperationsTimelineRail } from './components/OperationsTimelineRail';
import { useControlRoomLifecycle } from './hooks/useControlRoomLifecycle';
import { useEventOperationsBoot } from './hooks/useEventOperationsBoot';
import { useReducedControlRoomMotion } from './hooks/useReducedControlRoomMotion';

export default function EventOperationsRoomPage() {
  const { id } = useParams<{ id: string }>();
  const roomRef = useRef<HTMLElement | null>(null);
  const lifecycle = useControlRoomLifecycle({ targetRef: roomRef });
  const motion = useReducedControlRoomMotion();
  const bootQuery = useEventOperationsBoot(id ?? '');

  const lifecycleLabel = lifecycle.lifecycleMode === 'hidden'
    ? 'Pausada'
    : lifecycle.lifecycleMode === 'degraded'
      ? 'Degradada'
      : 'Ativa';
  const dominantStation = bootQuery.data?.stations.find(
    (station) => station.station_key === bootQuery.data?.health.dominant_station_key,
  );

  return (
    <main
      ref={roomRef}
      data-motion-mode={motion.motionMode}
      className="min-h-[100dvh] overflow-hidden bg-[#07100f] text-white"
    >
      <div className="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,rgba(45,212,191,0.24),transparent_32%),linear-gradient(135deg,rgba(15,23,42,0.96),rgba(6,78,59,0.74))]" />
      <div className="relative mx-auto flex min-h-[100dvh] w-full max-w-7xl flex-col px-6 py-8">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.34em] text-cyan-200">
              EventOperations
            </p>
            <h1 className="mt-3 text-4xl font-semibold tracking-tight">
              Control room do evento
            </h1>
            <p className="mt-2 text-sm text-slate-300">Evento atual: {id ?? 'desconhecido'}</p>
          </div>
          <div className="flex flex-wrap justify-end gap-3">
            <OperationsStatusPill
              label="Contrato live congelado"
              value={<span className="font-mono">v{EVENT_OPERATIONS_SCHEMA_VERSION}</span>}
              tone="healthy"
            />
            <OperationsStatusPill
              label="Sala"
              value={lifecycle.isFullscreen ? 'Fullscreen' : 'Janela'}
              tone={lifecycle.isFullscreen ? 'healthy' : 'neutral'}
            />
            <OperationsStatusPill
              label="Lifecycle"
              value={lifecycleLabel}
              tone={lifecycle.lifecycleMode === 'degraded' ? 'attention' : 'neutral'}
              urgent={lifecycle.lifecycleMode === 'degraded'}
            />
            <OperationsStatusPill
              label="Motion"
              value={motion.prefersReducedMotion ? 'Reduzido' : 'Completo'}
              tone={motion.prefersReducedMotion ? 'attention' : 'neutral'}
            />
          </div>
        </header>

        {bootQuery.data ? (
          <div className="mt-6">
            <OperationsHud room={bootQuery.data} />
          </div>
        ) : null}

        <div className="mt-6 grid gap-6 xl:grid-cols-[1.35fr_0.85fr]">
          <section className="space-y-6">
            {bootQuery.data ? (
              <OperationsRoomCanvas
                room={bootQuery.data}
                motionMode={motion.motionMode}
                stationGestures={motion.stationGestures}
              />
            ) : null}

            <article className="rounded-3xl border border-white/10 bg-slate-950/75 p-6 shadow-2xl">
              <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">
                Contexto read-only
              </p>
              {bootQuery.isLoading ? (
                <div className="mt-4 space-y-3 text-sm text-slate-300">
                  <p>Carregando panorama operacional read-only...</p>
                  <p>Combinando journey builder, pipeline metrics, moderation, wall e timeline.</p>
                </div>
              ) : null}

              {bootQuery.isError ? (
                <div className="mt-4 rounded-2xl border border-amber-300/40 bg-amber-300/10 p-4 text-sm text-amber-100">
                  Sala degradada: nao foi possivel combinar o snapshot read-only.
                </div>
              ) : null}

              {bootQuery.data ? (
                <div className="mt-4 space-y-4">
                  <div>
                    <p className="text-sm font-semibold text-white">{bootQuery.data.health.summary}</p>
                    <p className="mt-1 text-sm text-slate-300">
                      {bootQuery.data.v0.journey_summary_text}
                    </p>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Estacao dominante
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {dominantStation?.label ?? 'Sem gargalo dominante agora'}
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        {bootQuery.data.v0.dominant_station_reason ?? 'A operacao segue sem um ponto unico de pressao.'}
                      </p>
                    </div>

                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Entradas ativas
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {bootQuery.data.v0.active_entry_channels.join(' / ') || 'Sem canais ativos'}
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        V0 em polling read-only combinando endpoints existentes.
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Wall atual
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {bootQuery.data.wall.current_item_id ?? 'Sem item atual'}
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        Proximo: {bootQuery.data.wall.next_item_id ?? 'sem previsao'} | Confianca {bootQuery.data.wall.confidence}
                      </p>
                    </div>

                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Pipeline tecnico
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {bootQuery.data.counters.processing_failures} falha(s) / {bootQuery.data.counters.backlog_total} backlog
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        A Query entra apenas no boot e no polling leve desta V0.
                      </p>
                    </div>
                  </div>
                </div>
              ) : null}
            </article>

            {bootQuery.data ? (
              <OperationsTimelineRail entries={bootQuery.data.timeline} />
            ) : null}
          </section>

          <aside className="space-y-6">
            <OperationsFullscreenEntryOverlay
              onEnterRoom={lifecycle.requestFullscreen}
              fullscreenError={lifecycle.fullscreenError}
            />

            <OperationsAlertStack alerts={bootQuery.data?.alerts ?? []} />
          </aside>
        </div>
      </div>
    </main>
  );
}

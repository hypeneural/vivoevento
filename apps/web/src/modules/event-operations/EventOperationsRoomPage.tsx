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
import { useEventOperationsFallback } from './hooks/useEventOperationsFallback';
import { useEventOperationsBoot } from './hooks/useEventOperationsBoot';
import { useEventOperationsRealtime } from './hooks/useEventOperationsRealtime';
import { useReducedControlRoomMotion } from './hooks/useReducedControlRoomMotion';
import { buildOperationsHudState, useEventOperationsHudSnapshot } from './stores/hud-store';
import { useEventOperationsRoomSnapshot } from './stores/room-store';
import { useEventOperationsTimelineSnapshot } from './stores/timeline-store';

export default function EventOperationsRoomPage() {
  const { id } = useParams<{ id: string }>();
  const roomRef = useRef<HTMLElement | null>(null);
  const lifecycle = useControlRoomLifecycle({ targetRef: roomRef });
  const motion = useReducedControlRoomMotion();
  const realtime = useEventOperationsRealtime(id ?? '');
  const fallback = useEventOperationsFallback(realtime.connectionState);
  const bootQuery = useEventOperationsBoot(id ?? '', fallback);
  const storedRoom = useEventOperationsRoomSnapshot();
  const storedTimeline = useEventOperationsTimelineSnapshot();
  const storedHud = useEventOperationsHudSnapshot();

  const room = storedRoom ?? bootQuery.data ?? null;
  const timelineEntries = storedTimeline.entries.length > 0
    ? storedTimeline.entries
    : bootQuery.timeline ?? room?.timeline ?? [];
  const hud = storedHud.hud ?? (room ? buildOperationsHudState(room) : null);

  const lifecycleLabel = lifecycle.lifecycleMode === 'hidden'
    ? 'Pausada'
    : lifecycle.lifecycleMode === 'degraded'
      ? 'Degradada'
      : 'Ativa';
  const dominantStation = room?.stations.find(
    (station) => station.station_key === room?.health.dominant_station_key,
  );
  const realtimeTone = realtime.connectionState === 'degraded' || realtime.connectionState === 'offline'
    ? 'critical'
    : realtime.connectionState === 'reconnecting' || realtime.connectionState === 'resyncing'
      ? 'attention'
      : 'neutral';

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
            {realtime.statusMessage ? (
              <OperationsStatusPill
                label="Realtime"
                value={realtime.statusMessage}
                tone={realtimeTone}
                urgent={realtimeTone === 'critical'}
              />
            ) : null}
          </div>
        </header>

        {room && hud ? (
          <div className="mt-6">
            <OperationsHud room={room} hud={hud} />
          </div>
        ) : null}

        <div className="mt-6 grid gap-6 xl:grid-cols-[1.35fr_0.85fr]">
          <section className="space-y-6">
            {room ? (
              <OperationsRoomCanvas
                room={room}
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
                  Sala degradada: nao foi possivel carregar o boot dedicado da sala.
                </div>
              ) : null}

              {room ? (
                <div className="mt-4 space-y-4">
                  <div>
                    <p className="text-sm font-semibold text-white">{room.health.summary}</p>
                    <p className="mt-1 text-sm text-slate-300">
                      {bootQuery.timelineQuery?.isError
                        ? 'Boot dedicado da sala carregado; rail historico dedicado indisponivel, usando o trilho do snapshot materializado.'
                        : fallback.isPollingFallbackActive
                          ? 'Realtime degradado; room e timeline voltaram para polling leve enquanto a sala tenta recuperar o live.'
                          : 'Boot dedicado carregado a partir de /operations/room, com rail inicial vindo de /operations/timeline e live incremental no websocket.'}
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
                        {dominantStation?.dominant_reason ?? 'A operacao segue sem um ponto unico de pressao.'}
                      </p>
                    </div>

                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Snapshot materializado
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        v{room.snapshot_version} | {room.timeline_cursor ?? 'sem cursor'}
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        Query fora do hot path: room store, timeline store e hud store recebem snapshots imutaveis.
                      </p>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Wall atual
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {room.wall.current_item_id ?? 'Sem item atual'}
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        Proximo: {room.wall.next_item_id ?? 'sem previsao'} | Confianca {room.wall.confidence}
                      </p>
                    </div>

                    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                      <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Pipeline tecnico
                      </p>
                      <p className="mt-2 text-base font-semibold text-white">
                        {room.counters.processing_failures} falha(s) / {room.counters.backlog_total} backlog
                      </p>
                      <p className="mt-1 text-sm text-slate-300">
                        Room em polling leve; timeline dedicada fica livre para history e patches imutaveis.
                      </p>
                    </div>
                  </div>
                </div>
              ) : null}
            </article>

            {timelineEntries.length > 0 ? (
              <OperationsTimelineRail entries={timelineEntries} />
            ) : null}
          </section>

          <aside className="space-y-6">
            <OperationsFullscreenEntryOverlay
              onEnterRoom={lifecycle.requestFullscreen}
              fullscreenError={lifecycle.fullscreenError}
            />

            <OperationsAlertStack alerts={room?.alerts ?? []} />
          </aside>
        </div>
      </div>
    </main>
  );
}

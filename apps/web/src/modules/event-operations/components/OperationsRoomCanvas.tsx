import { useEffect, useMemo, useRef } from 'react';

import type { EventOperationsStationKey } from '@eventovivo/shared-types/event-operations';

import type {
  ControlRoomMotionMode,
  ControlRoomStationGesture,
} from '../hooks/useReducedControlRoomMotion';
import type { EventOperationsV0Room } from '../types';
import { renderOperationsScene } from '../engine/renderer';
import { buildOperationsSceneRuntime } from '../engine/scene-runtime';

interface OperationsRoomCanvasProps {
  room: EventOperationsV0Room;
  motionMode: ControlRoomMotionMode;
  stationGestures: Record<EventOperationsStationKey, ControlRoomStationGesture>;
}

export function OperationsRoomCanvas({
  room,
  motionMode,
  stationGestures,
}: OperationsRoomCanvasProps) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const runtime = useMemo(
    () => buildOperationsSceneRuntime({
      room,
      motionMode,
      stationGestures,
    }),
    [motionMode, room, stationGestures],
  );

  useEffect(() => {
    const canvas = canvasRef.current;

    if (!canvas) {
      return;
    }

    canvas.width = runtime.size.width;
    canvas.height = runtime.size.height;

    let context: CanvasRenderingContext2D | null = null;

    try {
      context = canvas.getContext('2d');
    } catch {
      context = null;
    }

    if (!context) {
      return;
    }

    renderOperationsScene(context, runtime);
  }, [runtime]);

  return (
    <section
      aria-labelledby="operations-room-canvas-title"
      className="rounded-[2rem] border border-white/10 bg-slate-950/75 p-5 shadow-2xl"
    >
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-3xl">
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">
            Cena operacional
          </p>
          <h2
            id="operations-room-canvas-title"
            className="mt-2 text-2xl font-semibold tracking-tight text-white"
          >
            Canvas V0 com linguagem de equipe
          </h2>
          <p className="mt-2 text-sm text-slate-300">
            {runtime.narrative_summary}
          </p>
        </div>

        <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200">
          <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
            Modo visual
          </p>
          <p className="mt-1 font-semibold">{runtime.scene_mode_label}</p>
          <p className="mt-1 text-slate-400">
            {motionMode === 'reduced' ? 'Reduced-motion ativo' : 'Animacao funcional ativa'}
          </p>
        </div>
      </div>

      <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.8fr)]">
        <div className="relative overflow-hidden rounded-[1.75rem] border border-[#21443d] bg-[#081310]">
          <canvas
            ref={canvasRef}
            data-testid="operations-room-canvas"
            aria-hidden="true"
            className="block aspect-[16/9] w-full"
          />

          <div className="pointer-events-none absolute inset-0 flex flex-col justify-between p-4">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="max-w-xs rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 backdrop-blur-sm">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-cyan-200">
                  Leitura em 5s
                </p>
                <p className="mt-2 text-lg font-semibold text-white">{runtime.macro_reading.title}</p>
                <p className="mt-1 text-sm text-slate-300">{runtime.macro_reading.summary}</p>
              </div>

              <div className="max-w-xs rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-right backdrop-blur-sm">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-cyan-200">
                  Leitura em 15s
                </p>
                <p className="mt-2 text-lg font-semibold text-white">{runtime.meso_reading.title}</p>
                <p className="mt-1 text-sm text-slate-300">{runtime.meso_reading.summary}</p>
              </div>
            </div>

            <div className="max-w-sm rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 backdrop-blur-sm">
              <p className="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                Estado da cena
              </p>
              <p className="mt-1 text-sm font-semibold text-white">
                {runtime.calm_state ? 'Estado calmo ativo' : runtime.scene_mode_label}
              </p>
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
            <h3 className="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">
              Camadas da cena
            </h3>
            <ul aria-label="Camadas da cena" className="mt-3 grid gap-2 text-sm text-slate-200">
              {runtime.layers.map((layer) => (
                <li key={layer} className="rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 font-mono">
                  {layer}
                </li>
              ))}
            </ul>
          </div>

          <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
            <h3 className="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">
              Papeis visuais
            </h3>
            <ul aria-label="Papeis visuais" className="mt-3 grid gap-2 text-sm text-slate-200">
              {runtime.agents.map((agent) => (
                <li key={agent.role} className="rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2">
                  <span className="font-semibold text-white">{agent.label}</span>
                  <span className="ml-2 text-slate-400">{agent.summary}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>

      <div className="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4">
        <h3 className="text-sm font-semibold uppercase tracking-[0.24em] text-slate-300">
          Gestos por estacao
        </h3>
        <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {runtime.stations.map((station) => (
            <article
              key={station.station_key}
              className="rounded-2xl border border-white/10 bg-slate-950/60 px-4 py-3"
            >
              <p className="text-sm font-semibold text-white">{station.label}</p>
              <p className="mt-1 text-sm text-slate-300">{station.current_gesture_label}</p>
              <p className="mt-2 text-xs text-slate-500">
                Reduced: {station.reduced_motion_label}
              </p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

import { OperationsStatusPill } from './OperationsStatusPill';
import { buildOperationsHudState } from '../stores/hud-store';
import type { EventOperationsV0Room } from '../types';

interface OperationsHudProps {
  room: EventOperationsV0Room;
}

export function OperationsHud({ room }: OperationsHudProps) {
  const hud = buildOperationsHudState(room);

  return (
    <section
      aria-labelledby="operations-hud-title"
      className="rounded-3xl border border-white/10 bg-slate-950/75 p-5 shadow-2xl"
    >
      <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div className="max-w-2xl">
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">
            Control room live
          </p>
          <h2 id="operations-hud-title" className="mt-2 text-2xl font-semibold tracking-tight text-white">
            {hud.event_title}
          </h2>
          <div className="mt-4 space-y-2">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
              Status global
            </p>
            <p className="text-lg font-semibold text-white">{room.health.summary}</p>
            <p className="text-sm text-slate-300">{hud.global_status_label}</p>
            <p className="text-sm text-slate-400">
              Horario da sala
              <time className="ml-2 font-mono text-slate-200" dateTime={room.server_time}>
                {hud.server_clock_label}
              </time>
            </p>
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          <OperationsStatusPill
            label="Conexao"
            value={hud.connection_label}
            tone={hud.connection_tone}
            urgent={hud.connection_tone === 'critical'}
          />
          <OperationsStatusPill
            label="Wall health"
            value={hud.wall_label}
            tone={hud.wall_tone}
            urgent={hud.wall_tone === 'critical'}
          />
          <OperationsStatusPill
            label="Fila humana"
            value={hud.human_queue_label}
            tone={hud.human_queue_tone}
            urgent={hud.human_queue_tone === 'critical'}
          />
        </div>
      </div>
    </section>
  );
}

import type { EventOperationsAlert } from '@eventovivo/shared-types/event-operations';

interface OperationsAlertStackProps {
  alerts: EventOperationsAlert[];
}

const alertToneClass: Record<EventOperationsAlert['severity'], string> = {
  info: 'border-cyan-300/30 bg-cyan-300/10 text-cyan-100',
  warning: 'border-amber-300/40 bg-amber-300/10 text-amber-100',
  critical: 'border-red-300/40 bg-red-400/10 text-red-100',
};

export function OperationsAlertStack({ alerts }: OperationsAlertStackProps) {
  return (
    <section className="rounded-3xl border border-white/10 bg-slate-950/75 p-5 shadow-2xl">
      <div className="mb-4">
        <h2 className="text-lg font-semibold text-white">Alertas vivos</h2>
        <p className="text-sm text-slate-400">
          Problemas urgentes ficam fora da camada normal de status.
        </p>
      </div>

      {alerts.length === 0 ? (
        <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
          Nenhum alerta vivo.
        </p>
      ) : (
        <div className="grid gap-3">
          {alerts.map((alert) => (
            <article
              key={alert.id}
              role={alert.severity === 'critical' ? 'alert' : 'status'}
              className={`rounded-2xl border px-4 py-3 text-sm ${alertToneClass[alert.severity]}`}
            >
              <p className="font-semibold">{alert.title}</p>
              <p className="mt-1">{alert.summary}</p>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}

import type { EventOperationsTimelineEntry } from '@eventovivo/shared-types/event-operations';

interface OperationsTimelineRailProps {
  entries: EventOperationsTimelineEntry[];
}

const stationLabels: Record<EventOperationsTimelineEntry['station_key'], string> = {
  intake: 'Recepcao',
  download: 'Download',
  variants: 'Variantes',
  safety: 'Safety',
  intelligence: 'IA',
  human_review: 'Moderacao',
  gallery: 'Galeria',
  wall: 'Telao',
  feedback: 'Feedback',
  alerts: 'Alertas',
};

function formatOccurredAt(value: string): string {
  try {
    return new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(value));
  } catch {
    return value;
  }
}

export function OperationsTimelineRail({ entries }: OperationsTimelineRailProps) {
  const orderedEntries = [...entries].sort((left, right) => left.occurred_at.localeCompare(right.occurred_at));

  return (
    <section className="rounded-3xl border border-white/10 bg-slate-950/75 p-5 shadow-2xl">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 id="operations-timeline-title" className="text-lg font-semibold text-white">
            Timeline da operacao
          </h2>
          <p className="text-sm text-slate-400">
            Rail live read-only com append no fim da sequencia.
          </p>
        </div>
      </div>

      <ol
        role="log"
        aria-live="polite"
        aria-relevant="additions text"
        aria-labelledby="operations-timeline-title"
        className="grid gap-3"
      >
        {orderedEntries.map((entry) => (
          <li
            key={entry.id}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className="font-semibold text-white">{entry.title}</span>
              <time className="font-mono text-xs text-slate-400" dateTime={entry.occurred_at}>
                {formatOccurredAt(entry.occurred_at)}
              </time>
            </div>
            <p className="mt-1 text-slate-300">{entry.summary}</p>
            <p className="mt-2 text-xs uppercase tracking-[0.24em] text-slate-500">
              {stationLabels[entry.station_key]}
            </p>
          </li>
        ))}
      </ol>
    </section>
  );
}

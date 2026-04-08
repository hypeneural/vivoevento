import type { ApiWallInsightsResponse } from '@/lib/api-types';

import { WALL_INSIGHTS_COPY } from '@/modules/wall/wall-copy';
import { formatWallLastCaptureLabel } from '@/modules/wall/wall-view-models';

export function WallTotalMediaCard({
  totals,
  lastCaptureAt,
}: {
  totals: ApiWallInsightsResponse['totals'];
  lastCaptureAt?: string | null;
}) {
  const metrics = [
    { label: 'Recebidas', value: totals.received },
    { label: 'Aprovadas', value: totals.approved },
    { label: 'Na fila', value: totals.queued },
    { label: 'Exibidas', value: totals.displayed ?? '-' },
  ];

  return (
    <article className="rounded-3xl border border-border/60 bg-background/60 p-4">
      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{WALL_INSIGHTS_COPY.totalMedia}</p>
      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        {metrics.map((metric) => (
          <div key={metric.label} className="rounded-2xl border border-border/50 bg-muted/30 px-3 py-2">
            <p className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">{metric.label}</p>
            <p className="mt-1 text-base font-semibold text-foreground">{metric.value}</p>
          </div>
        ))}
      </div>
      <p className="mt-3 text-xs text-muted-foreground">
        Ultima chegada {formatWallLastCaptureLabel(lastCaptureAt)}.
      </p>
    </article>
  );
}

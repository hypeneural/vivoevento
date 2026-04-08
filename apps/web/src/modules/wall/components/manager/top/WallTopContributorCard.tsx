import type { ApiWallInsightsTopContributor } from '@/lib/api-types';

import { WALL_INSIGHTS_COPY } from '@/modules/wall/wall-copy';
import { getWallSourceMeta } from '@/modules/wall/wall-source-meta';
import { formatWallRelativeTime } from '@/modules/wall/wall-view-models';

export function WallTopContributorCard({
  contributor,
}: {
  contributor: ApiWallInsightsTopContributor | null;
}) {
  if (!contributor) {
    return (
      <article className="rounded-3xl border border-border/60 bg-background/60 p-4">
        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{WALL_INSIGHTS_COPY.topContributor}</p>
        <p className="mt-3 text-sm text-muted-foreground">
          Ainda nao existe lideranca de envio neste evento.
        </p>
      </article>
    );
  }

  const sourceMeta = getWallSourceMeta(contributor.source);

  return (
    <article className="rounded-3xl border border-border/60 bg-background/60 p-4">
      <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{WALL_INSIGHTS_COPY.topContributor}</p>
      <div className="mt-3 space-y-3">
        <div className="space-y-1">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h3 className="text-lg font-semibold text-foreground">
                {contributor.displayName || 'Convidado'}
              </h3>
              <p className="text-xs text-muted-foreground">
                {contributor.maskedContact || 'Contato oculto'}
              </p>
            </div>
            <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-medium ${sourceMeta.chipClassName}`}>
              <sourceMeta.Icon className="h-3.5 w-3.5" />
              {sourceMeta.label}
            </span>
          </div>
        </div>

        <div className="grid gap-2 sm:grid-cols-2">
          <div className="rounded-2xl border border-border/50 bg-muted/30 px-3 py-2">
            <p className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">Midias</p>
            <p className="mt-1 text-base font-semibold text-foreground">{contributor.mediaCount}</p>
          </div>
          <div className="rounded-2xl border border-border/50 bg-muted/30 px-3 py-2">
            <p className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">Ultima chegada</p>
            <p className="mt-1 text-sm font-medium text-foreground">
              {formatWallRelativeTime(contributor.lastSentAt)}
            </p>
          </div>
        </div>
      </div>
    </article>
  );
}

import type { ApiWallInsightsRecentItem } from '@/lib/api-types';
import { Star } from 'lucide-react';

import { cn } from '@/lib/utils';

import { formatWallRecentStatusLabel } from '@/modules/wall/wall-copy';
import { getWallSourceMeta } from '@/modules/wall/wall-source-meta';
import { formatWallRelativeTime } from '@/modules/wall/wall-view-models';

export function WallRecentMediaChip({
  item,
  selected,
  onSelect,
}: {
  item: ApiWallInsightsRecentItem;
  selected: boolean;
  onSelect?: (item: ApiWallInsightsRecentItem) => void;
}) {
  const sourceMeta = getWallSourceMeta(item.source);

  return (
    <button
      type="button"
      aria-pressed={selected}
      aria-label={`Selecionar midia recente de ${item.senderName || 'Convidado'}`}
      onClick={() => onSelect?.(item)}
      className={cn(
        'group flex min-w-[158px] flex-col gap-2 rounded-2xl border p-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        selected
          ? 'border-primary bg-primary/10 shadow-sm'
          : 'border-border/60 bg-background/70 hover:border-primary/40 hover:bg-background',
      )}
    >
      <div className="relative overflow-hidden rounded-2xl bg-muted">
        {item.previewUrl ? (
          <img
            src={item.previewUrl}
            alt={`Midia recente enviada por ${item.senderName || 'Convidado'}`}
            className="h-20 w-full object-cover"
          />
        ) : (
          <div className="flex h-20 items-center justify-center text-xs text-muted-foreground">
            Sem miniatura
          </div>
        )}

        <span className={`absolute left-2 top-2 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium ${sourceMeta.chipClassName}`}>
          <sourceMeta.Icon className="h-3 w-3" />
          {sourceMeta.label}
        </span>

        {item.isFeatured ? (
          <span className="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-700">
            <Star className="h-3 w-3" />
            Destaque
          </span>
        ) : null}
      </div>

      <div className="space-y-1">
        <p className="truncate text-sm font-medium text-foreground">
          {item.senderName || 'Convidado'}
        </p>
        <p className="text-[11px] text-muted-foreground">
          {formatWallRelativeTime(item.createdAt, 'Agora')}
        </p>
      </div>

      <span className="inline-flex w-fit rounded-full border border-border/50 bg-muted/30 px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
        {formatWallRecentStatusLabel(item.status)}
      </span>
    </button>
  );
}

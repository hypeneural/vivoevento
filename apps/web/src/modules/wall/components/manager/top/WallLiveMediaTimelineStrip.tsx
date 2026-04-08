import type { ApiWallInsightsRecentItem } from '@/lib/api-types';

import { ScrollArea } from '@/components/ui/scroll-area';

import { WALL_INSIGHTS_COPY } from '@/modules/wall/wall-copy';

import { WallRecentMediaChip } from './WallRecentMediaChip';

export function WallLiveMediaTimelineStrip({
  items,
  selectedMediaId,
  onSelectItem,
}: {
  items: ApiWallInsightsRecentItem[];
  selectedMediaId?: string | null;
  onSelectItem?: (item: ApiWallInsightsRecentItem) => void;
}) {
  return (
    <article className="rounded-3xl border border-border/60 bg-background/60 p-4">
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{WALL_INSIGHTS_COPY.recentMedia}</p>
        <p className="text-[11px] text-muted-foreground">
          Clique em uma miniatura para focar no palco.
        </p>
      </div>

      {items.length > 0 ? (
        <ScrollArea className="mt-3 w-full whitespace-nowrap">
          <div className="flex gap-3 pb-3">
            {items.map((item) => (
              <WallRecentMediaChip
                key={item.id}
                item={item}
                selected={selectedMediaId === item.id}
                onSelect={onSelectItem}
              />
            ))}
          </div>
        </ScrollArea>
      ) : (
        <div className="mt-3 rounded-2xl border border-dashed border-border/60 bg-muted/20 px-4 py-6 text-sm text-muted-foreground">
          Assim que chegarem novas midias, elas vao aparecer aqui em ordem de entrada.
        </div>
      )}
    </article>
  );
}

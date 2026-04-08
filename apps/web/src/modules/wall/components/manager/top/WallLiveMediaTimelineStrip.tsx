import type { ApiWallInsightsRecentItem } from '@/lib/api-types';

import { ScrollArea } from '@/components/ui/scroll-area';

import { WALL_INSIGHTS_COPY } from '@/modules/wall/wall-copy';
import { useWallRecentMediaTimeline } from '@/modules/wall/hooks/useWallRecentMediaTimeline';

import { WallRecentMediaChip } from './WallRecentMediaChip';

export function WallLiveMediaTimelineStrip({
  items,
  selectedMediaId,
  onSelectItem,
  onOpenItem,
}: {
  items: ApiWallInsightsRecentItem[];
  selectedMediaId?: string | null;
  onSelectItem?: (item: ApiWallInsightsRecentItem) => void;
  onOpenItem?: (item: ApiWallInsightsRecentItem) => void;
}) {
  const {
    isRecentStripPaused,
    registerItem,
    handleItemKeyDown,
    handlePointerEnter,
    handlePointerLeave,
    handleFocusCapture,
    handleBlurCapture,
  } = useWallRecentMediaTimeline({
    items,
    selectedMediaId,
    onSelectItem,
  });

  return (
    <article className="rounded-3xl border border-border/60 bg-background/60 p-4">
      <div className="flex items-center justify-between gap-3">
        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{WALL_INSIGHTS_COPY.recentMedia}</p>
        <p className="text-[11px] text-muted-foreground">
          {isRecentStripPaused
            ? 'Navegacao pausada para leitura.'
            : 'Clique para focar no palco. Duplo clique abre o detalhe.'}
        </p>
      </div>

      {items.length > 0 ? (
        <ScrollArea className="mt-3 w-full whitespace-nowrap">
          <div
            className="flex gap-3 pb-3"
            onMouseEnter={handlePointerEnter}
            onMouseLeave={handlePointerLeave}
            onFocusCapture={handleFocusCapture}
            onBlurCapture={handleBlurCapture}
          >
            {items.map((item, index) => (
              <WallRecentMediaChip
                key={item.id}
                ref={registerItem(index)}
                item={item}
                selected={selectedMediaId === item.id}
                onSelect={onSelectItem}
                onOpen={onOpenItem}
                onKeyDown={(event) => handleItemKeyDown(event, index)}
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

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type MouseEvent,
  type RefObject,
} from 'react';

import type { ApiEventMediaItem } from '@/lib/api-types';

import { ModerationMediaCard, type ModerationMediaAction } from './ModerationMediaCard';

const GRID_GAP_PX = 16;
const CARD_HEIGHT_PX = 480;
const ROW_HEIGHT_PX = CARD_HEIGHT_PX + GRID_GAP_PX;
const OVERSCAN_ROWS = 3;

interface ViewportState {
  scrollY: number;
  viewportHeight: number;
  containerTop: number;
}

export interface ModerationVirtualGridHandle {
  scrollToIndex: (index: number) => void;
}

interface ModerationVirtualGridProps {
  items: ApiEventMediaItem[];
  focusedMediaId: number | null;
  selectedSet: Set<number>;
  canModerate: boolean;
  loadMoreRef: RefObject<HTMLDivElement | null>;
  isBusy: (mediaId: number, action?: ModerationMediaAction) => boolean;
  onOpen: (itemId: number) => void;
  onToggleChecked: (itemId: number, event: MouseEvent<HTMLElement>) => void;
  onAction: (item: ApiEventMediaItem, action: ModerationMediaAction) => void;
}

function clamp(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max);
}

export const ModerationVirtualGrid = forwardRef<ModerationVirtualGridHandle, ModerationVirtualGridProps>(function ModerationVirtualGrid({
  items,
  focusedMediaId,
  selectedSet,
  canModerate,
  loadMoreRef,
  isBusy,
  onOpen,
  onToggleChecked,
  onAction,
}, ref) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const frameRef = useRef<number | null>(null);
  const [containerWidth, setContainerWidth] = useState(0);
  const [viewport, setViewport] = useState<ViewportState>({
    scrollY: 0,
    viewportHeight: 0,
    containerTop: 0,
  });

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const updateViewport = () => {
      const container = containerRef.current;

      if (!container) {
        return;
      }

      const rect = container.getBoundingClientRect();
      setContainerWidth((current) => (current === rect.width ? current : rect.width));

      const nextState = {
        scrollY: window.scrollY,
        viewportHeight: window.innerHeight,
        containerTop: rect.top + window.scrollY,
      };

      setViewport((current) => (
        current.scrollY === nextState.scrollY
        && current.viewportHeight === nextState.viewportHeight
        && current.containerTop === nextState.containerTop
      ) ? current : nextState);
    };

    const scheduleViewportUpdate = () => {
      if (frameRef.current !== null) {
        return;
      }

      frameRef.current = window.requestAnimationFrame(() => {
        frameRef.current = null;
        updateViewport();
      });
    };

    const resizeObserver = new ResizeObserver((entries) => {
      const width = entries[0]?.contentRect.width ?? 0;

      setContainerWidth((current) => (current === width ? current : width));
      scheduleViewportUpdate();
    });

    if (containerRef.current) {
      resizeObserver.observe(containerRef.current);
    }

    scheduleViewportUpdate();
    window.addEventListener('scroll', scheduleViewportUpdate, { passive: true });
    window.addEventListener('resize', scheduleViewportUpdate);

    return () => {
      if (frameRef.current !== null) {
        window.cancelAnimationFrame(frameRef.current);
      }

      resizeObserver.disconnect();
      window.removeEventListener('scroll', scheduleViewportUpdate);
      window.removeEventListener('resize', scheduleViewportUpdate);
    };
  }, []);

  const columnCount = useMemo(() => {
    if (containerWidth >= 1180) {
      return 3;
    }

    if (containerWidth >= 640) {
      return 2;
    }

    return 1;
  }, [containerWidth]);

  const rowCount = useMemo(() => Math.ceil(items.length / columnCount), [columnCount, items.length]);

  const { startRow, endRow } = useMemo(() => {
    if (rowCount === 0) {
      return { startRow: 0, endRow: 0 };
    }

    const viewportTop = viewport.scrollY - viewport.containerTop;
    const viewportBottom = viewportTop + viewport.viewportHeight;
    const calculatedStartRow = clamp(Math.floor(viewportTop / ROW_HEIGHT_PX) - OVERSCAN_ROWS, 0, rowCount);
    const calculatedEndRow = clamp(
      Math.ceil(viewportBottom / ROW_HEIGHT_PX) + OVERSCAN_ROWS,
      calculatedStartRow + 1,
      rowCount,
    );

    return {
      startRow: calculatedStartRow,
      endRow: calculatedEndRow,
    };
  }, [rowCount, viewport.containerTop, viewport.scrollY, viewport.viewportHeight]);

  const startIndex = startRow * columnCount;
  const endIndex = Math.min(endRow * columnCount, items.length);
  const visibleItems = useMemo(() => items.slice(startIndex, endIndex), [endIndex, items, startIndex]);
  const topPadding = startRow * ROW_HEIGHT_PX;
  const bottomPadding = Math.max(0, (rowCount - endRow) * ROW_HEIGHT_PX);

  useImperativeHandle(ref, () => ({
    scrollToIndex: (index: number) => {
      if (!containerRef.current || typeof window === 'undefined' || items.length === 0) {
        return;
      }

      const boundedIndex = clamp(index, 0, items.length - 1);
      const rowIndex = Math.floor(boundedIndex / columnCount);
      const rect = containerRef.current.getBoundingClientRect();
      const containerTop = rect.top + window.scrollY;
      const centeredOffset = Math.max((window.innerHeight - CARD_HEIGHT_PX) / 2, 0);

      window.scrollTo({
        top: Math.max(containerTop + (rowIndex * ROW_HEIGHT_PX) - centeredOffset, 0),
        behavior: 'smooth',
      });
    },
  }), [columnCount, items.length]);

  return (
    <div ref={containerRef} className="px-4 py-4 sm:px-5">
      <div style={{ height: topPadding }} aria-hidden />

      <div
        className="grid gap-4"
        style={{ gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))` }}
      >
        {visibleItems.map((item) => (
          <ModerationMediaCard
            key={item.id}
            media={item}
            focused={item.id === focusedMediaId}
            checked={selectedSet.has(item.id)}
            canModerate={canModerate}
            isBusy={(action) => isBusy(item.id, action)}
            onOpen={() => onOpen(item.id)}
            onToggleChecked={(event) => onToggleChecked(item.id, event)}
            onAction={(action) => onAction(item, action)}
          />
        ))}
      </div>

      <div style={{ height: bottomPadding }} aria-hidden />
      <div ref={loadMoreRef} className="h-10" />
    </div>
  );
});

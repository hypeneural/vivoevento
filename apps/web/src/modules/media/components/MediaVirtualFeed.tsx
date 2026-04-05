import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
  type RefObject,
} from 'react';

import type { ApiEventMediaItem } from '@/lib/api-types';

const GRID_GAP_PX = 16;
const GRID_CARD_HEIGHT_PX = 352;
const GRID_ROW_HEIGHT_PX = GRID_CARD_HEIGHT_PX + GRID_GAP_PX;
const LIST_ROW_HEIGHT_PX = 120;
const OVERSCAN_ROWS = 4;

interface ViewportState {
  scrollY: number;
  viewportHeight: number;
  containerTop: number;
}

interface MediaVirtualFeedProps {
  items: ApiEventMediaItem[];
  view: 'grid' | 'list';
  loadMoreRef: RefObject<HTMLDivElement | null>;
  renderItem: (item: ApiEventMediaItem) => ReactNode;
}

function clamp(value: number, min: number, max: number) {
  return Math.min(Math.max(value, min), max);
}

export function MediaVirtualFeed({
  items,
  view,
  loadMoreRef,
  renderItem,
}: MediaVirtualFeedProps) {
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
    if (view === 'list') {
      return 1;
    }

    if (containerWidth >= 1536) {
      return 4;
    }

    if (containerWidth >= 1280) {
      return 3;
    }

    if (containerWidth >= 640) {
      return 2;
    }

    return 1;
  }, [containerWidth, view]);

  const rowHeight = view === 'list'
    ? LIST_ROW_HEIGHT_PX
    : GRID_ROW_HEIGHT_PX;

  const rowCount = useMemo(
    () => Math.ceil(items.length / columnCount),
    [columnCount, items.length],
  );

  const { startRow, endRow } = useMemo(() => {
    if (rowCount === 0) {
      return { startRow: 0, endRow: 0 };
    }

    const viewportTop = viewport.scrollY - viewport.containerTop;
    const viewportBottom = viewportTop + viewport.viewportHeight;
    const calculatedStartRow = clamp(Math.floor(viewportTop / rowHeight) - OVERSCAN_ROWS, 0, rowCount);
    const calculatedEndRow = clamp(
      Math.ceil(viewportBottom / rowHeight) + OVERSCAN_ROWS,
      calculatedStartRow + 1,
      rowCount,
    );

    return {
      startRow: calculatedStartRow,
      endRow: calculatedEndRow,
    };
  }, [rowCount, rowHeight, viewport.containerTop, viewport.scrollY, viewport.viewportHeight]);

  const startIndex = startRow * columnCount;
  const endIndex = Math.min(endRow * columnCount, items.length);
  const visibleItems = useMemo(
    () => items.slice(startIndex, endIndex),
    [endIndex, items, startIndex],
  );
  const topPadding = startRow * rowHeight;
  const bottomPadding = Math.max(0, (rowCount - endRow) * rowHeight);

  if (view === 'list') {
    return (
      <div
        ref={containerRef}
        className="glass overflow-hidden rounded-3xl border border-border/60"
      >
        <div style={{ height: topPadding }} aria-hidden />
        {visibleItems.map(renderItem)}
        <div style={{ height: bottomPadding }} aria-hidden />
        <div ref={loadMoreRef} className="h-10" />
      </div>
    );
  }

  return (
    <div ref={containerRef}>
      <div style={{ height: topPadding }} aria-hidden />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        {visibleItems.map(renderItem)}
      </div>
      <div style={{ height: bottomPadding }} aria-hidden />
      <div ref={loadMoreRef} className="h-10" />
    </div>
  );
}

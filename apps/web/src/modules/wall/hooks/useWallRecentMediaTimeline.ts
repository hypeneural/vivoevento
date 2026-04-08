import { useCallback, useEffect, useRef, useState } from 'react';

import type { ApiWallInsightsRecentItem } from '@/lib/api-types';

interface UseWallRecentMediaTimelineOptions {
  items: ApiWallInsightsRecentItem[];
  selectedMediaId?: string | null;
  onSelectItem?: (item: ApiWallInsightsRecentItem) => void;
}

export function useWallRecentMediaTimeline({
  items,
  selectedMediaId,
  onSelectItem,
}: UseWallRecentMediaTimelineOptions) {
  const itemRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const [isHoverPaused, setIsHoverPaused] = useState(false);
  const [isFocusPaused, setIsFocusPaused] = useState(false);

  const isRecentStripPaused = isHoverPaused || isFocusPaused;

  const focusAndSelectItem = useCallback((index: number) => {
    const item = items[index];
    const element = itemRefs.current[index];

    if (!item || !element) {
      return;
    }

    element.focus();
    onSelectItem?.(item);
  }, [items, onSelectItem]);

  const registerItem = useCallback((index: number) => (element: HTMLButtonElement | null) => {
    itemRefs.current[index] = element;
  }, []);

  const handleItemKeyDown = useCallback((
    event: React.KeyboardEvent<HTMLButtonElement>,
    index: number,
  ) => {
    if (items.length === 0) {
      return;
    }

    switch (event.key) {
      case 'ArrowRight': {
        event.preventDefault();
        focusAndSelectItem((index + 1) % items.length);
        break;
      }
      case 'ArrowLeft': {
        event.preventDefault();
        focusAndSelectItem((index - 1 + items.length) % items.length);
        break;
      }
      case 'Home': {
        event.preventDefault();
        focusAndSelectItem(0);
        break;
      }
      case 'End': {
        event.preventDefault();
        focusAndSelectItem(items.length - 1);
        break;
      }
      default:
        break;
    }
  }, [focusAndSelectItem, items.length]);

  const handlePointerEnter = useCallback(() => {
    setIsHoverPaused(true);
  }, []);

  const handlePointerLeave = useCallback(() => {
    setIsHoverPaused(false);
  }, []);

  const handleFocusCapture = useCallback(() => {
    setIsFocusPaused(true);
  }, []);

  const handleBlurCapture = useCallback((event: React.FocusEvent<HTMLDivElement>) => {
    if (event.currentTarget.contains(event.relatedTarget as Node | null)) {
      return;
    }

    setIsFocusPaused(false);
  }, []);

  useEffect(() => {
    if (!selectedMediaId) {
      return;
    }

    const selectedIndex = items.findIndex((item) => item.id === selectedMediaId);

    if (selectedIndex < 0) {
      return;
    }

    const selectedElement = itemRefs.current[selectedIndex];

    if (typeof selectedElement?.scrollIntoView === 'function') {
      selectedElement.scrollIntoView({
        block: 'nearest',
        inline: 'nearest',
      });
    }
  }, [items, selectedMediaId]);

  return {
    isRecentStripPaused,
    registerItem,
    handleItemKeyDown,
    handlePointerEnter,
    handlePointerLeave,
    handleFocusCapture,
    handleBlurCapture,
  };
}

import { type KeyboardEvent, type ReactNode, useRef } from 'react';

import { cn } from '@/lib/utils';

const TOOLBAR_ITEM_SELECTOR = [
  'a[href]',
  'button:not(:disabled)',
  '[role="button"]:not([aria-disabled="true"])',
].join(',');

function getToolbarItems(container: HTMLDivElement | null) {
  if (!container) {
    return [];
  }

  return Array.from(container.querySelectorAll<HTMLElement>(TOOLBAR_ITEM_SELECTOR));
}

export function WallCommandToolbar({
  ariaLabel,
  children,
  className,
}: {
  ariaLabel: string;
  children: ReactNode;
  className?: string;
}) {
  const toolbarRef = useRef<HTMLDivElement | null>(null);

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      return;
    }

    const items = getToolbarItems(toolbarRef.current);

    if (items.length === 0) {
      return;
    }

    const currentItem = (event.target as HTMLElement).closest<HTMLElement>(TOOLBAR_ITEM_SELECTOR);

    if (!currentItem) {
      return;
    }

    const currentIndex = items.indexOf(currentItem);

    if (currentIndex < 0) {
      return;
    }

    event.preventDefault();

    if (event.key === 'Home') {
      items[0]?.focus();
      return;
    }

    if (event.key === 'End') {
      items[items.length - 1]?.focus();
      return;
    }

    const nextIndex = event.key === 'ArrowRight'
      ? (currentIndex + 1) % items.length
      : (currentIndex - 1 + items.length) % items.length;

    items[nextIndex]?.focus();
  }

  return (
    <div
      ref={toolbarRef}
      role="toolbar"
      aria-label={ariaLabel}
      onKeyDown={handleKeyDown}
      className={cn('flex flex-wrap items-center gap-2', className)}
    >
      {children}
    </div>
  );
}

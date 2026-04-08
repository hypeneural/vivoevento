import { renderHook } from '@testing-library/react';
import { act } from 'react';
import { describe, expect, it, vi } from 'vitest';

import { useWallRecentMediaTimeline } from './useWallRecentMediaTimeline';

const timelineItems = [
  {
    id: 'media-1',
    previewUrl: 'https://cdn.example.com/media-1.jpg',
    senderName: 'Ana',
    senderKey: 'whatsapp:ana',
    source: 'whatsapp' as const,
    createdAt: '2026-04-08T10:00:00Z',
    approvedAt: null,
    displayedAt: null,
    status: 'queued' as const,
    isFeatured: false,
    isReplay: false,
  },
  {
    id: 'media-2',
    previewUrl: 'https://cdn.example.com/media-2.jpg',
    senderName: 'Bruno',
    senderKey: 'upload:bruno',
    source: 'upload' as const,
    createdAt: '2026-04-08T10:01:00Z',
    approvedAt: null,
    displayedAt: null,
    status: 'received' as const,
    isFeatured: false,
    isReplay: false,
  },
  {
    id: 'media-3',
    previewUrl: 'https://cdn.example.com/media-3.jpg',
    senderName: 'Carla',
    senderKey: 'gallery:carla',
    source: 'gallery' as const,
    createdAt: '2026-04-08T10:02:00Z',
    approvedAt: null,
    displayedAt: null,
    status: 'approved' as const,
    isFeatured: true,
    isReplay: false,
  },
];

describe('useWallRecentMediaTimeline', () => {
  it('pausa o trilho em hover e foco, retomando ao sair', () => {
    const { result } = renderHook(() => useWallRecentMediaTimeline({
      items: timelineItems,
      selectedMediaId: 'media-1',
    }));

    expect(result.current.isRecentStripPaused).toBe(false);

    act(() => {
      result.current.handlePointerEnter();
    });
    expect(result.current.isRecentStripPaused).toBe(true);

    act(() => {
      result.current.handlePointerLeave();
    });
    expect(result.current.isRecentStripPaused).toBe(false);

    act(() => {
      result.current.handleFocusCapture();
    });
    expect(result.current.isRecentStripPaused).toBe(true);

    act(() => {
      result.current.handleBlurCapture({
        currentTarget: {
          contains: () => false,
        },
        relatedTarget: null,
      } as unknown as React.FocusEvent<HTMLDivElement>);
    });
    expect(result.current.isRecentStripPaused).toBe(false);
  });

  it('navega com teclado e seleciona o proximo item com seta, Home e End', () => {
    const onSelectItem = vi.fn();
    const focusFirst = vi.fn();
    const focusSecond = vi.fn();
    const focusThird = vi.fn();

    const { result } = renderHook(() => useWallRecentMediaTimeline({
      items: timelineItems,
      selectedMediaId: 'media-1',
      onSelectItem,
    }));

    act(() => {
      result.current.registerItem(0)({
        focus: focusFirst,
        scrollIntoView: vi.fn(),
      } as unknown as HTMLButtonElement);
      result.current.registerItem(1)({
        focus: focusSecond,
        scrollIntoView: vi.fn(),
      } as unknown as HTMLButtonElement);
      result.current.registerItem(2)({
        focus: focusThird,
        scrollIntoView: vi.fn(),
      } as unknown as HTMLButtonElement);
    });

    const preventDefault = vi.fn();

    act(() => {
      result.current.handleItemKeyDown({
        key: 'ArrowRight',
        preventDefault,
      } as unknown as React.KeyboardEvent<HTMLButtonElement>, 0);
    });

    expect(preventDefault).toHaveBeenCalled();
    expect(focusSecond).toHaveBeenCalled();
    expect(onSelectItem).toHaveBeenCalledWith(timelineItems[1]);

    act(() => {
      result.current.handleItemKeyDown({
        key: 'End',
        preventDefault,
      } as unknown as React.KeyboardEvent<HTMLButtonElement>, 0);
    });

    expect(focusThird).toHaveBeenCalled();
    expect(onSelectItem).toHaveBeenCalledWith(timelineItems[2]);

    act(() => {
      result.current.handleItemKeyDown({
        key: 'Home',
        preventDefault,
      } as unknown as React.KeyboardEvent<HTMLButtonElement>, 2);
    });

    expect(focusFirst).toHaveBeenCalled();
    expect(onSelectItem).toHaveBeenCalledWith(timelineItems[0]);
  });

  it('mantem o item selecionado visivel ao rolar o trilho', () => {
    const scrollIntoView = vi.fn();

    const { result, rerender } = renderHook(
      ({ selectedMediaId }) => useWallRecentMediaTimeline({
        items: timelineItems,
        selectedMediaId,
      }),
      {
        initialProps: {
          selectedMediaId: 'media-1',
        },
      },
    );

    act(() => {
      result.current.registerItem(0)({
        focus: vi.fn(),
        scrollIntoView: vi.fn(),
      } as unknown as HTMLButtonElement);
      result.current.registerItem(1)({
        focus: vi.fn(),
        scrollIntoView,
      } as unknown as HTMLButtonElement);
    });

    rerender({ selectedMediaId: 'media-2' });

    expect(scrollIntoView).toHaveBeenCalledWith({
      block: 'nearest',
      inline: 'nearest',
    });
  });
});

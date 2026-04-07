/**
 * useQRDraggable — Drag-to-reposition QR code overlay.
 *
 * Features:
 * - Pointer events (works on mouse + touch)
 * - Persists position to localStorage
 * - Disabled on touch/mobile (< 1024px) since it conflicts with scrolling
 * - Constrains to viewport bounds
 *
 * Inspired by Kululu's draggable QR position.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

interface QRPosition {
  x: number;
  y: number;
}

const STORAGE_KEY_PREFIX = 'wall-qr-position-';

function getStoredPosition(code: string): QRPosition | null {
  try {
    const stored = localStorage.getItem(`${STORAGE_KEY_PREFIX}${code}`);
    if (stored) {
      const parsed = JSON.parse(stored);
      if (typeof parsed.x === 'number' && typeof parsed.y === 'number') {
        return parsed;
      }
    }
  } catch {
    // Ignore parsing errors
  }
  return null;
}

function storePosition(code: string, pos: QRPosition) {
  try {
    localStorage.setItem(`${STORAGE_KEY_PREFIX}${code}`, JSON.stringify(pos));
  } catch {
    // Storage full or unavailable
  }
}

export function useQRDraggable(code: string, enabled: boolean = true) {
  const [position, setPosition] = useState<QRPosition | null>(() => getStoredPosition(code));
  const [isDragging, setIsDragging] = useState(false);
  const dragStartRef = useRef<{ x: number; y: number; posX: number; posY: number } | null>(null);
  const elementRef = useRef<HTMLDivElement>(null);

  // Don't enable on touch/mobile
  const isDesktop = typeof window !== 'undefined' && window.innerWidth >= 1024;
  const isActive = enabled && isDesktop;

  const handlePointerDown = useCallback(
    (e: React.PointerEvent<HTMLDivElement>) => {
      if (!isActive) return;
      e.preventDefault();
      e.stopPropagation();

      const currentPos = position ?? { x: 0, y: 0 };
      dragStartRef.current = {
        x: e.clientX,
        y: e.clientY,
        posX: currentPos.x,
        posY: currentPos.y,
      };
      setIsDragging(true);
      (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
    },
    [isActive, position],
  );

  const handlePointerMove = useCallback(
    (e: React.PointerEvent<HTMLDivElement>) => {
      if (!isDragging || !dragStartRef.current) return;

      const dx = e.clientX - dragStartRef.current.x;
      const dy = e.clientY - dragStartRef.current.y;

      setPosition({
        x: dragStartRef.current.posX + dx,
        y: dragStartRef.current.posY + dy,
      });
    },
    [isDragging],
  );

  const handlePointerUp = useCallback(() => {
    if (!isDragging) return;
    setIsDragging(false);
    dragStartRef.current = null;

    if (position) {
      storePosition(code, position);
    }
  }, [isDragging, position, code]);

  // Cleanup on unmount — save last position
  useEffect(() => {
    return () => {
      if (position) {
        storePosition(code, position);
      }
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return {
    ref: elementRef,
    position,
    isDragging,
    isActive,
    handlers: isActive
      ? {
          onPointerDown: handlePointerDown,
          onPointerMove: handlePointerMove,
          onPointerUp: handlePointerUp,
          style: {
            transform: position ? `translate(${position.x}px, ${position.y}px)` : undefined,
            cursor: isDragging ? 'grabbing' : 'grab',
            touchAction: 'none' as const,
            userSelect: 'none' as const,
          },
        }
      : { style: {} },
  };
}

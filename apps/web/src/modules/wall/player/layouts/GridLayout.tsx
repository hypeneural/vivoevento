/**
 * GridLayout — 3 equal columns with glassmorphism cards.
 *
 * Features:
 * - 3 equal columns, responsive gap
 * - Glassmorphism cards with border + shadow
 * - Empty cells with distinct subtle style
 * - Caption gradient bottom with truncated text
 *
 * Inspired by MomentLoop's Grid template.
 */

import { AnimatePresence, motion } from 'framer-motion';
import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { resolveStrongAnimationSlotIndexes } from '../themes/board/board-utils';

interface GridLayoutProps {
  items: (WallRuntimeItem | null)[];
  activeSlotIndexes?: number[];
  maxStrongAnimations?: number;
}

function GridCell({
  item,
  slotIndex,
  isStrongAnimation,
}: {
  item: WallRuntimeItem | null;
  slotIndex: number;
  isStrongAnimation: boolean;
}) {
  if (!item) {
    return (
      <div
        data-testid={`grid-cell-${slotIndex}`}
        data-strong-animation="false"
        className="grid-cell-empty overflow-hidden rounded-[18px]"
        style={{
          background: 'rgba(255,255,255,0.03)',
          border: '1px solid rgba(255,255,255,0.08)',
        }}
      />
    );
  }

  return (
    <div
      data-testid={`grid-cell-${slotIndex}`}
      data-strong-animation={isStrongAnimation ? 'true' : 'false'}
      className="relative overflow-hidden rounded-[18px]"
      style={{
        background: 'rgba(255,255,255,0.06)',
        border: '1px solid rgba(255,255,255,0.18)',
        boxShadow: '0 12px 30px rgba(0,0,0,0.35)',
      }}
    >
      <AnimatePresence mode="wait">
        <motion.div
          key={item.id}
          className="absolute inset-0"
          initial={isStrongAnimation ? { opacity: 0, scale: 0.92, y: 18 } : { opacity: 0 }}
          animate={isStrongAnimation ? { opacity: 1, scale: 1, y: 0 } : { opacity: 1 }}
          exit={isStrongAnimation ? { opacity: 0, scale: 0.98 } : { opacity: 0 }}
          transition={{ duration: isStrongAnimation ? 0.32 : 0.16 }}
        >
          <MediaSurface media={item} fit="cover" />
        </motion.div>
      </AnimatePresence>

      {/* Caption gradient */}
      {item.sender_name ? (
        <div
          className="absolute inset-x-0 bottom-0 z-10 px-4 pb-3 pt-10"
          style={{
            background: 'linear-gradient(transparent, rgba(0,0,0,0.75))',
          }}
        >
          <p
            className="truncate font-semibold text-white"
            style={{ fontSize: 'clamp(0.8rem, 1.1vw, 1.1rem)' }}
          >
            {item.sender_name}
          </p>
          {item.caption ? (
            <p
              className="mt-0.5 truncate text-white/60"
              style={{ fontSize: 'clamp(0.65rem, 0.85vw, 0.9rem)' }}
            >
              {item.caption}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export function GridLayout({
  items,
  activeSlotIndexes = [],
  maxStrongAnimations = activeSlotIndexes.length,
}: GridLayoutProps) {
  const strongSlots = new Set(
    resolveStrongAnimationSlotIndexes(activeSlotIndexes, maxStrongAnimations),
  );

  return (
    <div
      className="absolute inset-0"
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 'clamp(10px, 1.2vw, 20px)',
        padding: 'clamp(10px, 1.2vw, 20px)',
        background: '#070707',
      }}
    >
      <GridCell item={items[0] ?? null} slotIndex={0} isStrongAnimation={strongSlots.has(0)} />
      <GridCell item={items[1] ?? null} slotIndex={1} isStrongAnimation={strongSlots.has(1)} />
      <GridCell item={items[2] ?? null} slotIndex={2} isStrongAnimation={strongSlots.has(2)} />
    </div>
  );
}

export default GridLayout;

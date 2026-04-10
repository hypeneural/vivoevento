/**
 * MosaicLayout — 1 large + 2 small grid (2x2 with span).
 *
 * Features:
 * - Cell-0 spans 2 rows (left column, full height)
 * - Cells 1,2 stacked in right column
 * - Round-robin slot updates
 * - Caption gradient bottom on each cell
 *
 * Inspired by MomentLoop's Mosaic template.
 */

import { AnimatePresence, motion } from 'framer-motion';
import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { resolveStrongAnimationSlotIndexes } from '../themes/board/board-utils';

interface MosaicLayoutProps {
  items: (WallRuntimeItem | null)[];
  activeSlotIndexes?: number[];
  maxStrongAnimations?: number;
}

function MosaicCell({
  item,
  isPrimary,
  slotIndex,
  isStrongAnimation,
}: {
  item: WallRuntimeItem | null;
  isPrimary: boolean;
  slotIndex: number;
  isStrongAnimation: boolean;
}) {
  if (!item) {
    return (
      <div
        data-testid={`mosaic-cell-${slotIndex}`}
        data-strong-animation="false"
        className="overflow-hidden"
        style={{
          borderRadius: 'clamp(8px, 1vw, 16px)',
          background: 'rgba(255,255,255,0.03)',
          border: '1px dashed rgba(255,255,255,0.08)',
          gridRow: isPrimary ? '1 / 3' : undefined,
        }}
      />
    );
  }

  return (
    <div
      data-testid={`mosaic-cell-${slotIndex}`}
      data-strong-animation={isStrongAnimation ? 'true' : 'false'}
      className="relative overflow-hidden"
      style={{ borderRadius: 'clamp(8px, 1vw, 16px)', gridRow: isPrimary ? '1 / 3' : undefined }}
    >
      <AnimatePresence mode="wait">
        <motion.div
          key={item.id}
          className="absolute inset-0"
          initial={isStrongAnimation ? { opacity: 0, scale: 0.94, x: isPrimary ? -24 : 12 } : { opacity: 0 }}
          animate={isStrongAnimation ? { opacity: 1, scale: 1, x: 0 } : { opacity: 1 }}
          exit={isStrongAnimation ? { opacity: 0, scale: 0.98 } : { opacity: 0 }}
          transition={{ duration: isStrongAnimation ? 0.34 : 0.18 }}
        >
          <MediaSurface media={item} fit="cover" />
        </motion.div>
      </AnimatePresence>

      {/* Caption gradient */}
      {item.sender_name ? (
        <div
          className="absolute inset-x-0 bottom-0 z-10 px-4 pb-3 pt-10"
          style={{
            background: 'linear-gradient(transparent, rgba(0,0,0,0.8))',
          }}
        >
          <p
            className="truncate font-semibold text-white"
            style={{
              fontSize: isPrimary
                ? 'clamp(1rem, 1.8vw, 1.8rem)'
                : 'clamp(0.7rem, 1.2vw, 1rem)',
            }}
          >
            {item.sender_name}
          </p>
        </div>
      ) : null}
    </div>
  );
}

export function MosaicLayout({
  items,
  activeSlotIndexes = [],
  maxStrongAnimations = activeSlotIndexes.length,
}: MosaicLayoutProps) {
  const strongSlots = new Set(
    resolveStrongAnimationSlotIndexes(activeSlotIndexes, maxStrongAnimations),
  );

  return (
    <div
      className="absolute inset-0"
      style={{
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gridTemplateRows: '1fr 1fr',
        gap: 'clamp(4px, 0.5vw, 8px)',
        padding: 'clamp(4px, 0.5vw, 8px)',
        background: '#0a0a0a',
      }}
    >
      <MosaicCell item={items[0] ?? null} slotIndex={0} isPrimary={true} isStrongAnimation={strongSlots.has(0)} />
      <MosaicCell item={items[1] ?? null} slotIndex={1} isPrimary={false} isStrongAnimation={strongSlots.has(1)} />
      <MosaicCell item={items[2] ?? null} slotIndex={2} isPrimary={false} isStrongAnimation={strongSlots.has(2)} />
    </div>
  );
}

export default MosaicLayout;

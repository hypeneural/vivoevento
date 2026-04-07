/**
 * SideThumbnails — Vertical thumbnail strips on left and right.
 *
 * Features:
 * - 2 columns (absolute left/right), 4 thumbs each
 * - Square thumbnails with rounded corners and subtle border
 * - Opacity 0.7 to not compete with center media
 * - Fade transition on thumbnail swap (0.5s)
 * - Hidden on screens < 1024px
 *
 * Inspired by Kululu's side thumbnail streams.
 */

import { AnimatePresence, motion } from 'framer-motion';
import type { SideThumbnailItem } from '../hooks/useSideThumbnails';

interface SideThumbnailsProps {
  leftItems: SideThumbnailItem[];
  rightItems: SideThumbnailItem[];
}

function ThumbnailColumn({
  items,
  side,
}: {
  items: SideThumbnailItem[];
  side: 'left' | 'right';
}) {
  const posClass = side === 'left'
    ? 'left-[max(12px,1.2vw)]'
    : 'right-[max(12px,1.2vw)]';

  return (
    <div
      className={`absolute ${posClass} top-1/2 z-10 hidden -translate-y-1/2 flex-col lg:flex`}
      style={{ gap: 'clamp(6px, 0.8vh, 14px)' }}
    >
      <AnimatePresence mode="popLayout">
        {items.map((item) => (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 0.7, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            transition={{ duration: 0.5, ease: 'easeOut' }}
            className="overflow-hidden rounded-xl border-2 border-white/12 shadow-[0_4px_20px_rgba(0,0,0,0.4)]"
            style={{
              width: 'clamp(70px, 7vmin, 130px)',
              height: 'clamp(70px, 7vmin, 130px)',
            }}
          >
            <img
              src={item.url}
              alt={item.sender_name || ''}
              className="h-full w-full object-cover"
              loading="lazy"
            />
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  );
}

export function SideThumbnails({ leftItems, rightItems }: SideThumbnailsProps) {
  if (leftItems.length === 0 && rightItems.length === 0) return null;

  return (
    <>
      <ThumbnailColumn items={leftItems} side="left" />
      <ThumbnailColumn items={rightItems} side="right" />
    </>
  );
}

export default SideThumbnails;

/**
 * CarouselLayout — 3D perspective carousel with 3 slides.
 *
 * Features:
 * - Center slide large, laterals smaller + rotated + dimmed
 * - Perspective 3D transforms
 * - Smooth transition on advance
 * - Caption from center slide
 *
 * Inspired by MomentLoop's Carousel template.
 */

import { AnimatePresence, motion } from 'framer-motion';
import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { resolveStrongAnimationSlotIndexes } from '../themes/board/board-utils';
import './carousel.css';

interface CarouselLayoutProps {
  items: (WallRuntimeItem | null)[];
  activeSlot: number;
  activeSlotIndexes?: number[];
  maxStrongAnimations?: number;
}

const POSITIONS = ['carousel-left', 'carousel-center', 'carousel-right'] as const;

export function CarouselLayout({
  items,
  activeSlot,
  activeSlotIndexes = [],
  maxStrongAnimations = activeSlotIndexes.length,
}: CarouselLayoutProps) {
  // items[0] = left, items[1] = center, items[2] = right
  const centerItem = items[1];
  const strongSlots = new Set(
    resolveStrongAnimationSlotIndexes(activeSlotIndexes, maxStrongAnimations),
  );

  return (
    <div className="carousel-container">
      <div className="carousel-track">
        {POSITIONS.map((posClass, i) => {
          const item = items[i];
          if (!item) {
            return (
              <div key={`empty-${i}`} className={`carousel-slide ${posClass} carousel-empty`} />
            );
          }

          return (
            <AnimatePresence key={`slot-${i}`} mode="wait">
              <motion.div
                key={item.id}
                className={`carousel-slide ${posClass}`}
                data-strong-animation={strongSlots.has(i) ? 'true' : 'false'}
                initial={strongSlots.has(i) ? { opacity: 0, scale: 0.9, y: i === activeSlot ? 18 : 0 } : { opacity: 0 }}
                animate={strongSlots.has(i) ? { opacity: 1, scale: 1, y: 0 } : { opacity: 1 }}
                exit={strongSlots.has(i) ? { opacity: 0, scale: 0.98 } : { opacity: 0 }}
                transition={{ duration: strongSlots.has(i) ? 0.42 : 0.2 }}
              >
                <MediaSurface media={item} fit="cover" />
              </motion.div>
            </AnimatePresence>
          );
        })}
      </div>

      {/* Caption from center slide */}
      {centerItem?.sender_name ? (
        <div className="carousel-caption">
          <span className="carousel-sender">{centerItem.sender_name}</span>
          {centerItem.caption ? (
            <span className="carousel-text">{centerItem.caption}</span>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export default CarouselLayout;

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
import './carousel.css';

interface CarouselLayoutProps {
  items: (WallRuntimeItem | null)[];
  activeSlot: number;
}

const POSITIONS = ['carousel-left', 'carousel-center', 'carousel-right'] as const;

export function CarouselLayout({ items, activeSlot }: CarouselLayoutProps) {
  // items[0] = left, items[1] = center, items[2] = right
  const centerItem = items[1];

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
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.5 }}
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

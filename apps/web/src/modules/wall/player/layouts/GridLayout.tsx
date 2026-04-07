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

interface GridLayoutProps {
  items: (WallRuntimeItem | null)[];
}

function GridCell({ item }: { item: WallRuntimeItem | null }) {
  if (!item) {
    return (
      <div
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
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.25 }}
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
          <p className="truncate text-sm font-semibold text-white">
            {item.sender_name}
          </p>
          {item.caption ? (
            <p className="mt-0.5 truncate text-xs text-white/60">
              {item.caption}
            </p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export function GridLayout({ items }: GridLayoutProps) {
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
      <GridCell item={items[0] ?? null} />
      <GridCell item={items[1] ?? null} />
      <GridCell item={items[2] ?? null} />
    </div>
  );
}

export default GridLayout;

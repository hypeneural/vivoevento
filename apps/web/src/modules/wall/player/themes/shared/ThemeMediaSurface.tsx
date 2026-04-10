import { useState } from 'react';
import { motion } from 'framer-motion';

import { cn } from '@/lib/utils';

import type { WallRuntimeItem } from '../../types';

interface ThemeMediaSurfaceProps {
  media?: WallRuntimeItem | null;
  clipPathId?: string;
  className?: string;
}

export function ThemeMediaSurface({
  media,
  clipPathId,
  className,
}: ThemeMediaSurfaceProps) {
  const [loaded, setLoaded] = useState(false);
  const [failed, setFailed] = useState(false);

  const src = media?.type === 'video'
    ? media.preview_url ?? null
    : media?.url ?? null;

  return (
    <div
      data-testid="theme-media-surface"
      className={cn('relative h-full w-full overflow-hidden bg-black/35', className)}
      style={clipPathId ? { clipPath: `url(#${clipPathId})` } : undefined}
    >
      {src && !failed ? (
        <motion.img
          src={src}
          alt={media?.sender_name || 'Midia do tema'}
          className="h-full w-full object-cover"
          initial={{ opacity: 0.12, scale: 1.02 }}
          animate={{ opacity: loaded ? 1 : 0.18, scale: 1.04 }}
          transition={{ duration: 0.38, ease: 'easeOut' }}
          onLoad={() => setLoaded(true)}
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.12),rgba(0,0,0,0.82))]" />
      )}

      {!loaded && !failed ? (
        <div className="absolute inset-0 animate-pulse bg-[linear-gradient(135deg,rgba(255,255,255,0.08),rgba(255,255,255,0.02),rgba(0,0,0,0.18))]" />
      ) : null}
    </div>
  );
}

export default ThemeMediaSurface;

/**
 * MediaSurface — Renders a single image or video.
 * Handles the fade-in animation for images via framer-motion.
 */

import { useMemo } from 'react';
import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';
import type { WallRuntimeItem } from '../types';

interface MediaSurfaceProps {
  media: WallRuntimeItem;
  fit?: 'contain' | 'cover';
  className?: string;
  imageClassName?: string;
}

export function MediaSurface({
  media,
  fit = 'contain',
  className,
  imageClassName,
}: MediaSurfaceProps) {
  const sharedClass = useMemo(
    () => cn('h-full w-full', fit === 'cover' ? 'object-cover' : 'object-contain', imageClassName),
    [fit, imageClassName],
  );

  if (media.type === 'video') {
    return (
      <div className={cn('relative h-full w-full overflow-hidden', className)}>
        <video
          key={media.id}
          src={media.url}
          className={sharedClass}
          autoPlay
          muted
          playsInline
          loop
        />
      </div>
    );
  }

  return (
    <div className={cn('relative h-full w-full overflow-hidden', className)}>
      <motion.img
        key={`${media.id}-${media.url}`}
        src={media.url}
        alt={media.sender_name || 'Foto do evento'}
        className={cn(sharedClass, 'will-change-[opacity,transform]')}
        initial={{ opacity: 0.18, scale: 0.992 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.42, ease: 'easeOut' }}
      />
    </div>
  );
}

export default MediaSurface;

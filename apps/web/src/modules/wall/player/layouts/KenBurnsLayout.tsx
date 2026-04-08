/**
 * KenBurnsLayout — Cinematic pan/zoom over static images.
 *
 * Features:
 * - 4 cycling CSS animations (zoom-in, zoom-out, pan-left, pan-right)
 * - Radial vignette overlay
 * - Animation duration synced to interval_ms
 * - Reduced motion: static image, no KB animation
 * - Video: gentle zoom only (scale 1→1.08 in 20s)
 *
 * Inspired by MomentLoop's Ken Burns template.
 */

import { useMemo } from 'react';
import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { resolveKenBurnsAnimationClass } from '../engine/motion';
import './ken-burns.css';

interface KenBurnsLayoutProps {
  media: WallRuntimeItem;
  intervalMs?: number;
  reducedMotion?: boolean;
}

let kbCounter = 0;

export function KenBurnsLayout({
  media,
  intervalMs = 8000,
  reducedMotion = false,
}: KenBurnsLayoutProps) {
  const animationClass = useMemo(() => {
    const cls = resolveKenBurnsAnimationClass(kbCounter, reducedMotion);
    kbCounter += 1;
    return cls;
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [media.id, reducedMotion]);

  const durationVar = `${intervalMs / 1000}s`;
  const isVideo = media.type === 'video';
  const containerClass = `kb-container kb-vignette${reducedMotion ? ' kb-reduced' : ''}`;

  return (
    <div className={containerClass}>
      <div
        className="absolute inset-0"
        style={{ '--kb-duration': durationVar } as React.CSSProperties}
      >
        {isVideo ? (
          <div className="kb-media kb-video-zoom">
            <MediaSurface media={media} fit="cover" />
          </div>
        ) : (
          <div className={`kb-media ${animationClass ?? ''}`}>
            <MediaSurface media={media} fit="cover" />
          </div>
        )}
      </div>

      {/* Caption overlay — gradient bottom with large cinematic text */}
      {(media.sender_name || media.caption) ? (
        <div className="kb-caption-overlay">
          {media.sender_name ? (
            <div className="kb-sender-name">{media.sender_name}</div>
          ) : null}
          {media.caption ? (
            <div className="kb-caption-text">{media.caption}</div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export default KenBurnsLayout;

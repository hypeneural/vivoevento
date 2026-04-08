/**
 * AdOverlay — Fullscreen overlay for displaying ads between slideshow photos.
 *
 * Features:
 * - Image ads: display for duration_seconds, then fire onFinished
 * - Video ads: autoplay muted, fire onFinished on ended
 * - Video safety timeout (5 min) in case onended never fires
 * - Fade in/out transition
 * - Respects prefers-reduced-motion
 */

import { useEffect, useRef, useCallback } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import type { WallAdItem } from '../types';
import { resolveAdVideoAttrs, AD_VIDEO_SAFETY_TIMEOUT_MS } from '../engine/autoplay';

interface AdOverlayProps {
  ad: WallAdItem;
  onFinished: () => void;
  reducedMotion?: boolean;
}

export function AdOverlay({ ad, onFinished, reducedMotion = false }: AdOverlayProps) {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const safetyTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const finishedRef = useRef(false);

  const handleFinished = useCallback(() => {
    if (finishedRef.current) return;
    finishedRef.current = true;
    if (timerRef.current) clearTimeout(timerRef.current);
    if (safetyTimerRef.current) clearTimeout(safetyTimerRef.current);
    onFinished();
  }, [onFinished]);

  // Image: auto-advance after duration_seconds
  useEffect(() => {
    if (ad.media_type !== 'image') return;

    timerRef.current = setTimeout(handleFinished, ad.duration_seconds * 1000);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [ad, handleFinished]);

  // Video: safety timeout
  useEffect(() => {
    if (ad.media_type !== 'video') return;

    safetyTimerRef.current = setTimeout(handleFinished, AD_VIDEO_SAFETY_TIMEOUT_MS);

    return () => {
      if (safetyTimerRef.current) clearTimeout(safetyTimerRef.current);
    };
  }, [ad, handleFinished]);

  // Reset finished flag on ad change
  useEffect(() => {
    finishedRef.current = false;
  }, [ad.id]);

  const transition = reducedMotion ? 0 : 0.3;
  const videoAttrs = resolveAdVideoAttrs();

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={`ad-${ad.id}`}
        className="absolute inset-0 z-40 flex items-center justify-center bg-black"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: transition }}
      >
        {ad.media_type === 'video' ? (
          <video
            src={ad.url}
            onEnded={handleFinished}
            className="h-full w-full object-contain"
            {...videoAttrs}
          />
        ) : (
          <img
            src={ad.url}
            alt="Anúncio"
            className="h-full w-full object-contain"
          />
        )}

        {/* Ad indicator badge */}
        <div className="absolute right-[max(12px,1.5vw)] top-[max(12px,1.5vh)] z-50">
          <div className="rounded-md bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wider text-white/50 backdrop-blur-sm">
            Patrocinador
          </div>
        </div>
      </motion.div>
    </AnimatePresence>
  );
}

export default AdOverlay;

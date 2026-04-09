/**
 * LayoutRenderer — Switches between layouts with animated transitions.
 * Uses framer-motion AnimatePresence for smooth slide transitions.
 *
 * Supports both:
 * - Single-item layouts (fullscreen, cinematic, split, polaroid, kenburns, spotlight, gallery)
 * - Multi-item layouts (carousel, mosaic, grid) via useMultiSlot hook
 *
 * Phase 0.2: Respects prefers-reduced-motion via resolveEffectiveTransition.
 */

import { useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import type { WallRuntimeItem, WallSettings, WallTransition } from '../types';
import type { MediaSurfaceVideoControlProps } from './MediaSurface';
import CarouselLayout from '../layouts/CarouselLayout';
import CinematicLayout from '../layouts/CinematicLayout';
import FullscreenLayout from '../layouts/FullscreenLayout';
import GalleryLayout from '../layouts/GalleryLayout';
import GridLayout from '../layouts/GridLayout';
import KenBurnsLayout from '../layouts/KenBurnsLayout';
import MosaicLayout from '../layouts/MosaicLayout';
import PolaroidLayout from '../layouts/PolaroidLayout';
import SpotlightLayout from '../layouts/SpotlightLayout';
import SplitLayout from '../layouts/SplitLayout';
import { resolveRenderableLayout, isMultiItemLayout } from '../engine/layoutStrategy';
import { resolveEffectiveTransition } from '../engine/motion';
import { useMultiSlot } from '../hooks/useMultiSlot';

const MULTI_ITEM_SLOT_COUNT = 3;

function renderSingleLayout(
  layout: string,
  media: WallRuntimeItem,
  settings: WallSettings,
  reducedMotion: boolean,
  videoControl?: MediaSurfaceVideoControlProps | null,
) {
  switch (layout) {
    case 'cinematic':
      return <CinematicLayout media={media} videoControl={videoControl} />;
    case 'split':
      return <SplitLayout media={media} videoControl={videoControl} />;
    case 'polaroid':
      return <PolaroidLayout media={media} videoControl={videoControl} />;
    case 'kenburns':
      return (
        <KenBurnsLayout
          media={media}
          intervalMs={settings.interval_ms}
          reducedMotion={reducedMotion}
          videoControl={videoControl}
        />
      );
    case 'spotlight':
      return <SpotlightLayout media={media} videoControl={videoControl} />;
    case 'gallery':
      return <GalleryLayout media={media} videoControl={videoControl} />;
    case 'fullscreen':
    default:
      return <FullscreenLayout media={media} videoControl={videoControl} />;
  }
}

function renderMultiLayout(
  layout: string,
  slots: (WallRuntimeItem | null)[],
  activeSlot: number,
) {
  switch (layout) {
    case 'carousel':
      return <CarouselLayout items={slots} activeSlot={activeSlot} />;
    case 'mosaic':
      return <MosaicLayout items={slots} />;
    case 'grid':
      return <GridLayout items={slots} />;
    default:
      return null;
  }
}

function transitionVariants(effect: WallTransition) {
  switch (effect) {
    case 'slide':
      return {
        initial: { opacity: 0, x: 60 },
        animate: { opacity: 1, x: 0 },
        exit: { opacity: 0, x: -60 },
      };
    case 'zoom':
      return {
        initial: { opacity: 0, scale: 0.92 },
        animate: { opacity: 1, scale: 1 },
        exit: { opacity: 0, scale: 1.08 },
      };
    case 'flip':
      return {
        initial: { opacity: 0, rotateY: 90 },
        animate: { opacity: 1, rotateY: 0 },
        exit: { opacity: 0, rotateY: -90 },
      };
    case 'none':
      return {
        initial: { opacity: 1 },
        animate: { opacity: 1 },
        exit: { opacity: 1 },
      };
    case 'fade':
    default:
      return {
        initial: { opacity: 0, scale: 0.996 },
        animate: { opacity: 1, scale: 1 },
        exit: { opacity: 0 },
      };
  }
}

interface LayoutRendererProps {
  media: WallRuntimeItem;
  settings: WallSettings;
  reducedMotion?: boolean;
  /** Full items array, needed for multi-item layouts */
  allItems?: WallRuntimeItem[];
  videoControl?: MediaSurfaceVideoControlProps | null;
}

export function LayoutRenderer({
  media,
  settings,
  reducedMotion = false,
  allItems = [],
  videoControl = null,
}: LayoutRendererProps) {
  const resolvedLayout = resolveRenderableLayout(
    settings.layout,
    media,
    settings.video_multi_layout_policy ?? 'disallow',
  );
  const isMulti = isMultiItemLayout(resolvedLayout);

  // Track advance triggers for multi-slot by counting media.id changes
  const advanceCountRef = useRef(0);
  const lastMediaIdRef = useRef(media.id);
  if (media.id !== lastMediaIdRef.current) {
    lastMediaIdRef.current = media.id;
    advanceCountRef.current += 1;
  }

  const multiSlot = useMultiSlot(
    isMulti ? allItems : [],
    MULTI_ITEM_SLOT_COUNT,
    advanceCountRef.current,
  );

  // Multi-item layouts handle their own transitions
  if (isMulti) {
    return (
      <div className="absolute inset-0">
        {renderMultiLayout(resolvedLayout, multiSlot.slots, multiSlot.nextSlotIndex)}
      </div>
    );
  }

  // Single-item layouts with AnimatePresence transition
  const effectiveTransition = resolveEffectiveTransition(settings.transition_effect, reducedMotion);
  const variants = transitionVariants(effectiveTransition);

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={`${resolvedLayout}:${media.id}:${media.url}`}
        initial={variants.initial}
        animate={variants.animate}
        exit={variants.exit}
        transition={{ duration: effectiveTransition === 'none' ? 0 : 0.4, ease: 'easeOut' }}
        className="absolute inset-0"
      >
        {renderSingleLayout(resolvedLayout, media, settings, reducedMotion, videoControl)}
      </motion.div>
    </AnimatePresence>
  );
}

export default LayoutRenderer;

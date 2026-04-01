/**
 * LayoutRenderer — Switches between layouts with animated transitions.
 * Uses framer-motion AnimatePresence for smooth slide transitions.
 */

import { AnimatePresence, motion } from 'framer-motion';
import type { WallRuntimeItem, WallSettings, WallTransition } from '../types';
import CinematicLayout from '../layouts/CinematicLayout';
import FullscreenLayout from '../layouts/FullscreenLayout';
import PolaroidLayout from '../layouts/PolaroidLayout';
import SplitLayout from '../layouts/SplitLayout';
import { resolveRenderableLayout } from '../engine/layoutStrategy';

function renderLayout(layout: string, media: WallRuntimeItem) {
  switch (layout) {
    case 'cinematic':
      return <CinematicLayout media={media} />;
    case 'split':
      return <SplitLayout media={media} />;
    case 'polaroid':
      return <PolaroidLayout media={media} />;
    case 'fullscreen':
    default:
      return <FullscreenLayout media={media} />;
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
}

export function LayoutRenderer({ media, settings }: LayoutRendererProps) {
  const resolvedLayout = resolveRenderableLayout(settings.layout, media);
  const variants = transitionVariants(settings.transition_effect);

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={`${resolvedLayout}:${media.id}:${media.url}`}
        initial={variants.initial}
        animate={variants.animate}
        exit={variants.exit}
        transition={{ duration: 0.4, ease: 'easeOut' }}
        className="absolute inset-0"
      >
        {renderLayout(resolvedLayout, media)}
      </motion.div>
    </AnimatePresence>
  );
}

export default LayoutRenderer;

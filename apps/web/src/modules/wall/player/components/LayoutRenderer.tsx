/**
 * LayoutRenderer - Resolves the active wall layout through the registry.
 *
 * Single layouts keep AnimatePresence transitions here.
 * Board layouts keep their own cell-level transitions and receive a stable
 * 3-slot scheduler until the dedicated board subsystem lands.
 */

import { useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

import { resolveLayoutTransition } from '../engine/motion';
import { isMultiItemLayout, resolveRenderableLayout } from '../engine/layoutStrategy';
import { useMultiSlot } from '../hooks/useMultiSlot';
import { getWallLayoutDefinition } from '../themes/registry';
import type { WallRuntimeItem, WallSettings } from '../types';
import type { MediaSurfaceVideoControlProps } from './MediaSurface';

const MULTI_ITEM_SLOT_COUNT = 3;

interface LayoutRendererProps {
  media: WallRuntimeItem;
  settings: WallSettings;
  reducedMotion?: boolean;
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
  const definition = getWallLayoutDefinition(resolvedLayout);
  const LayoutComponent = definition.renderer;
  const isBoardLayout = definition.kind === 'board';

  const advanceCountRef = useRef(0);
  const lastMediaIdRef = useRef(media.id);

  if (media.id !== lastMediaIdRef.current) {
    lastMediaIdRef.current = media.id;
    advanceCountRef.current += 1;
  }

  const multiSlot = useMultiSlot(
    isBoardLayout || isMultiItemLayout(resolvedLayout) ? allItems : [],
    MULTI_ITEM_SLOT_COUNT,
    advanceCountRef.current,
  );

  if (isBoardLayout) {
    return (
      <div className="absolute inset-0">
        <LayoutComponent
          media={media}
          settings={settings}
          reducedMotion={reducedMotion}
          videoControl={videoControl}
          slots={multiSlot.slots}
          activeSlot={multiSlot.nextSlotIndex}
        />
      </div>
    );
  }

  const resolvedTransition = resolveLayoutTransition(
    settings.transition_effect,
    definition.motion,
    reducedMotion,
  );

  return (
    <AnimatePresence mode="wait">
      <motion.div
        key={`${resolvedLayout}:${media.id}:${media.url}`}
        initial={resolvedTransition.variants.initial}
        animate={resolvedTransition.variants.animate}
        exit={resolvedTransition.variants.exit}
        transition={resolvedTransition.transition}
        className="absolute inset-0"
      >
        <LayoutComponent
          media={media}
          settings={settings}
          reducedMotion={reducedMotion}
          videoControl={videoControl}
        />
      </motion.div>
    </AnimatePresence>
  );
}

export default LayoutRenderer;

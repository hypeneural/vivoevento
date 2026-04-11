/**
 * LayoutRenderer - Resolves the active wall layout through the registry.
 *
 * Single layouts keep AnimatePresence transitions here.
 * Board layouts share a dedicated board scheduler so the first dense layout
 * does not become a one-off subsystem.
 */

import { useRef } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

import { resolveLayoutTransition } from '../engine/motion';
import { resolveRenderableLayout } from '../engine/layoutStrategy';
import { resolveWallRuntimeBudget } from '../runtime-capabilities';
import { getWallLayoutDefinition } from '../themes/registry';
import {
  createBoardInstanceKey,
  createLinearAdjacencyMap,
} from '../themes/board/types';
import { useWallBoard } from '../themes/board/useWallBoard';
import { usePuzzleBoard } from '../themes/puzzle/usePuzzleBoard';
import type {
  WallPerformanceTier,
  WallRuntimeItem,
  WallSettings,
  WallTransition,
} from '../types';
import type { MediaSurfaceVideoControlProps } from './MediaSurface';

const MULTI_ITEM_SLOT_COUNT = 3;

interface LayoutRendererProps {
  media: WallRuntimeItem;
  settings: WallSettings;
  reducedMotion?: boolean;
  allItems?: WallRuntimeItem[];
  videoControl?: MediaSurfaceVideoControlProps | null;
  eventId?: string | number | null;
  performanceTier?: WallPerformanceTier;
  activeTransitionEffect?: WallTransition | null;
}

export function LayoutRenderer({
  media,
  settings,
  reducedMotion = false,
  allItems = [],
  videoControl = null,
  eventId = null,
  performanceTier = reducedMotion ? 'performance' : 'premium',
  activeTransitionEffect = null,
}: LayoutRendererProps) {
  const resolvedLayout = resolveRenderableLayout(
    settings.layout,
    media,
    settings.video_multi_layout_policy ?? 'disallow',
  );
  const definition = getWallLayoutDefinition(resolvedLayout);
  const LayoutComponent = definition.renderer;
  const isBoardLayout = definition.kind === 'board';
  const runtimeBudget = resolveWallRuntimeBudget(performanceTier);

  const advanceCountRef = useRef(0);
  const lastMediaIdRef = useRef(media.id);

  if (media.id !== lastMediaIdRef.current) {
    lastMediaIdRef.current = media.id;
    advanceCountRef.current += 1;
  }

  const boardState = useWallBoard(
    isBoardLayout && definition.id !== 'puzzle' ? allItems : [],
    {
      slotCount: MULTI_ITEM_SLOT_COUNT,
      advanceTrigger: advanceCountRef.current,
      boardInstanceKey: createBoardInstanceKey({
        eventId,
        layout: resolvedLayout,
        preset: settings.theme_config?.preset ?? null,
        themeVersion: definition.version,
        performanceTier,
        reducedMotion,
      }),
      adjacencyMap: createLinearAdjacencyMap(MULTI_ITEM_SLOT_COUNT),
      avoidSameSender: true,
    },
  );
  const puzzleBoardState = usePuzzleBoard(
    definition.id === 'puzzle' ? allItems : [],
    {
      settings,
      boardInstanceKey: createBoardInstanceKey({
        eventId,
        layout: resolvedLayout,
        preset: settings.theme_config?.preset ?? null,
        themeVersion: definition.version,
        performanceTier,
        reducedMotion,
      }),
      advanceTrigger: advanceCountRef.current,
      maxBoardPieces: runtimeBudget.maxBoardPieces,
      reducedMotion,
    },
  );
  const resolvedBoardState = definition.id === 'puzzle' ? puzzleBoardState : boardState;

  if (isBoardLayout) {
    return (
      <div className="absolute inset-0">
        <LayoutComponent
          media={media}
          settings={settings}
          reducedMotion={reducedMotion}
          videoControl={videoControl}
          slots={resolvedBoardState.slots}
          activeSlot={resolvedBoardState.activeSlot}
          activeSlotIndexes={resolvedBoardState.activeSlotIndexes}
          maxStrongAnimations={runtimeBudget.maxStrongAnimations}
        />
      </div>
    );
  }

  const resolvedTransition = resolveLayoutTransition(
    activeTransitionEffect ?? settings.transition_effect,
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

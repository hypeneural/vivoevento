import { AnimatePresence, LayoutGroup } from 'framer-motion';

import type { WallRuntimeItem } from '../../types';
import { resolveStrongAnimationSlotIndexes } from '../board/board-utils';
import type { WallLayoutRendererProps } from '../registry';
import PuzzlePiece from './PuzzlePiece';
import {
  PUZZLE_SHAPE_PATHS,
  resolvePuzzleClipPathId,
  resolvePuzzleShapeVariant,
} from './puzzle-shapes';
import './puzzle.css';

interface PuzzleCell {
  pieceIndex: number;
  pieceVariant: keyof typeof PUZZLE_SHAPE_PATHS;
  media: WallRuntimeItem | null;
  isAnchor: boolean;
  sourceSlotIndex: number | null;
}

function buildPuzzleCells(
  slots: (WallRuntimeItem | null)[],
  pieceCount: number,
  anchorEnabled: boolean,
  anchorIndex: number,
): PuzzleCell[] {
  const cells: PuzzleCell[] = [];
  let mediaCursor = 0;

  for (let pieceIndex = 0; pieceIndex < pieceCount; pieceIndex += 1) {
    const isAnchor = anchorEnabled && pieceIndex === anchorIndex;
    const media = isAnchor ? null : (slots[mediaCursor] ?? null);
    const sourceSlotIndex = isAnchor ? null : mediaCursor;

    cells.push({
      pieceIndex,
      pieceVariant: resolvePuzzleShapeVariant(pieceIndex),
      media,
      isAnchor,
      sourceSlotIndex,
    });

    if (!isAnchor) {
      mediaCursor += 1;
    }
  }

  return cells;
}

export function PuzzleLayout({
  settings,
  slots = [],
  reducedMotion = false,
  activeSlotIndexes = [],
  maxStrongAnimations = activeSlotIndexes.length,
}: WallLayoutRendererProps) {
  const preset = settings.theme_config?.preset ?? (slots.length <= 6 ? 'compact' : 'standard');
  const anchorMode = settings.theme_config?.anchor_mode ?? 'none';
  const anchorEnabled = anchorMode !== 'none';
  const pieceCount = slots.length + (anchorEnabled ? 1 : 0);
  const anchorIndex = pieceCount === 6 ? 2 : 4;
  const cells = buildPuzzleCells(slots, pieceCount, anchorEnabled, anchorIndex);
  const strongSlotIndexes = new Set(
    resolveStrongAnimationSlotIndexes(activeSlotIndexes, maxStrongAnimations),
  );
  const usedVariants = Array.from(new Set(cells.map((cell) => cell.pieceVariant)));
  const anchorLabel = anchorMode === 'qr_prompt' ? 'Envie sua foto' : 'Evento Vivo';
  const heroEnabled = settings.theme_config?.hero_enabled ?? true;
  const heroMediaId = heroEnabled
    ? cells.find((cell) => cell.media?.is_featured)?.media?.id ?? null
    : null;

  return (
    <LayoutGroup id={`puzzle-board-${preset}`}>
      <div
        data-testid="puzzle-board"
        className="puzzle-board"
        data-preset={preset}
        data-piece-count={pieceCount}
      >
        <svg width="0" height="0" aria-hidden="true">
          <defs>
            {usedVariants.map((variant) => (
              <clipPath
                key={variant}
                id={resolvePuzzleClipPathId(variant)}
                clipPathUnits="objectBoundingBox"
              >
                <path d={PUZZLE_SHAPE_PATHS[variant]} />
              </clipPath>
            ))}
          </defs>
        </svg>

        <AnimatePresence initial={false} mode="popLayout">
          {cells.map((cell) => (
            <PuzzlePiece
              key={`puzzle-piece-${cell.pieceIndex}-${cell.media?.id ?? 'anchor'}`}
              pieceIndex={cell.pieceIndex}
              pieceVariant={cell.pieceVariant}
              media={cell.media}
              isAnchor={cell.isAnchor}
              anchorLabel={anchorLabel}
              isHero={Boolean(heroMediaId && cell.media?.id === heroMediaId)}
              isStrongAnimation={cell.sourceSlotIndex != null && strongSlotIndexes.has(cell.sourceSlotIndex)}
              reducedMotion={reducedMotion}
            />
          ))}
        </AnimatePresence>
      </div>
    </LayoutGroup>
  );
}

export default PuzzleLayout;

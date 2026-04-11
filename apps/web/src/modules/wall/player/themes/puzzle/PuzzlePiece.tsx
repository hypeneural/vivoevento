import { forwardRef } from 'react';
import { motion, useAnimationFrame, useMotionValue, useSpring } from 'framer-motion';

import ThemeMediaSurface from '../shared/ThemeMediaSurface';
import type { WallRuntimeItem } from '../../types';
import {
  resolvePuzzleClipPathId,
  type PuzzleShapeVariant,
} from './puzzle-shapes';
import {
  resolvePuzzleBurstMotion,
  resolvePuzzleDriftProfile,
} from './puzzle-motion';

interface PuzzlePieceProps {
  pieceIndex: number;
  pieceVariant: PuzzleShapeVariant;
  media?: WallRuntimeItem | null;
  isAnchor?: boolean;
  anchorLabel?: string;
  isStrongAnimation?: boolean;
  reducedMotion?: boolean;
  isHero?: boolean;
}

export const PuzzlePiece = forwardRef<HTMLElement, PuzzlePieceProps>(function PuzzlePiece({
  pieceIndex,
  pieceVariant,
  media = null,
  isAnchor = false,
  anchorLabel = 'Evento Vivo',
  isStrongAnimation = false,
  reducedMotion = false,
  isHero = false,
}, ref) {
  const burstMotion = resolvePuzzleBurstMotion(isStrongAnimation, reducedMotion);
  const driftProfile = resolvePuzzleDriftProfile(media?.orientation ?? null, pieceIndex, reducedMotion);

  const baseX = useMotionValue(0);
  const baseY = useMotionValue(0);
  const driftX = useSpring(baseX, { stiffness: 28, damping: 20, mass: 1 });
  const driftY = useSpring(baseY, { stiffness: 28, damping: 20, mass: 1 });

  useAnimationFrame((time) => {
    if (!media || isAnchor || reducedMotion || driftProfile.amplitude === 0) {
      return;
    }

    const nextValue = Math.sin((time / driftProfile.periodMs) + driftProfile.phase) * driftProfile.amplitude;

    if (driftProfile.axis === 'x') {
      baseX.set(nextValue);
      baseY.set(0);
      return;
    }

    baseY.set(nextValue);
    baseX.set(0);
  });

  return (
    <motion.article
      ref={ref}
      data-testid={`puzzle-piece-${pieceIndex}`}
      data-strong-animation={isStrongAnimation ? 'true' : 'false'}
      data-piece-variant={pieceVariant}
      data-featured-hero={isHero ? 'true' : 'false'}
      className="puzzle-piece-shell"
      layout={reducedMotion ? false : 'position'}
      layoutId={isHero && media ? 'puzzle-featured-hero' : undefined}
      initial={burstMotion.initial}
      animate={burstMotion.animate}
      exit={burstMotion.exit}
      transition={burstMotion.transition}
    >
      {isAnchor ? (
        <div className="puzzle-anchor">
          <span className="puzzle-anchor-kicker">Ao vivo</span>
          <strong className="puzzle-anchor-title">{anchorLabel}</strong>
        </div>
      ) : (
        <motion.div
          className="absolute inset-0"
          style={{
            x: driftX,
            y: driftY,
            scale: driftProfile.scale,
          }}
        >
          <ThemeMediaSurface
            media={media}
            clipPathId={resolvePuzzleClipPathId(pieceVariant)}
            className="h-full w-full"
          />
        </motion.div>
      )}

      <div className="puzzle-piece-outline" />
    </motion.article>
  );
});

export default PuzzlePiece;

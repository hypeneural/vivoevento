import type { WallRuntimeItem } from '../../types';

export function resolvePuzzleDriftProfile(
  orientation: WallRuntimeItem['orientation'],
  pieceIndex: number,
  reducedMotion: boolean,
): {
  axis: 'x' | 'y';
  amplitude: number;
  periodMs: number;
  phase: number;
  scale: number;
} {
  if (reducedMotion) {
    return {
      axis: 'y',
      amplitude: 0,
      periodMs: 12000,
      phase: 0,
      scale: 1,
    };
  }

  if (orientation === 'vertical') {
    return {
      axis: 'y',
      amplitude: 14,
      periodMs: 16000,
      phase: pieceIndex * 0.45,
      scale: 1.08,
    };
  }

  if (orientation === 'horizontal') {
    return {
      axis: 'x',
      amplitude: 12,
      periodMs: 18000,
      phase: pieceIndex * 0.35,
      scale: 1.06,
    };
  }

  return {
    axis: 'y',
    amplitude: 8,
    periodMs: 20000,
    phase: pieceIndex * 0.28,
    scale: 1.04,
  };
}

export function resolvePuzzleBurstMotion(
  isStrongAnimation: boolean,
  reducedMotion: boolean,
) {
  if (reducedMotion) {
    return {
      initial: { opacity: 1 },
      animate: { opacity: 1 },
      exit: { opacity: 1 },
      transition: { duration: 0 },
    };
  }

  if (isStrongAnimation) {
    return {
      initial: { opacity: 0, scale: 0.86, rotate: -4 },
      animate: { opacity: 1, scale: 1, rotate: 0 },
      exit: { opacity: 0, scale: 0.98 },
      transition: { duration: 0.42, ease: 'easeOut' as const },
    };
  }

  return {
    initial: { opacity: 0 },
    animate: { opacity: 1 },
    exit: { opacity: 0 },
    transition: { duration: 0.2, ease: 'easeOut' as const },
  };
}

export function resolveStrongAnimationSlotIndexes(
  activeSlotIndexes: number[] | undefined,
  maxStrongAnimations: number | undefined,
): number[] {
  if (!activeSlotIndexes?.length || !maxStrongAnimations || maxStrongAnimations <= 0) {
    return [];
  }

  return activeSlotIndexes.slice(0, maxStrongAnimations);
}

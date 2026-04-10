import { describe, expect, it } from 'vitest';

import { resolveStrongAnimationSlotIndexes } from './board-utils';

describe('resolveStrongAnimationSlotIndexes', () => {
  it('caps strong animation slots to the runtime budget order', () => {
    expect(resolveStrongAnimationSlotIndexes([2, 0, 1], 1)).toEqual([2]);
    expect(resolveStrongAnimationSlotIndexes([2, 0, 1], 2)).toEqual([2, 0]);
  });

  it('returns an empty list when there is no strong animation budget', () => {
    expect(resolveStrongAnimationSlotIndexes([0, 1], 0)).toEqual([]);
  });
});

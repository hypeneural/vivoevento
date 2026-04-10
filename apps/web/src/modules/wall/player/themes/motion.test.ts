import { describe, expect, it } from 'vitest';

import {
  getWallLayoutMotionTokens,
  resolveWallMotionConfig,
} from './motion';

describe('wall theme motion tokens', () => {
  it('returns board-aware tokens for puzzle', () => {
    expect(getWallLayoutMotionTokens('puzzle')).toMatchObject({
      visualDuration: 0.46,
      reducedMotion: 'user',
      burst: {
        duration: 0.28,
      },
    });
  });

  it('returns instant motion config when reduced effects are active', () => {
    const config = resolveWallMotionConfig(getWallLayoutMotionTokens('puzzle'), true);

    expect(config.reducedMotion).toBe('always');
    expect(config.transition).toMatchObject({
      duration: 0,
    });
  });

  it('keeps the theme visual duration when reduced effects are not active', () => {
    const tokens = getWallLayoutMotionTokens('cinematic');
    const config = resolveWallMotionConfig(tokens, false);

    expect(config.reducedMotion).toBe('user');
    expect(config.transition).toMatchObject({
      duration: tokens.visualDuration,
      ease: tokens.enter.ease,
    });
  });
});

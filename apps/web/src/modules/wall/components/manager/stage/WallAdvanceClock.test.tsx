import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { WallAdvanceClock } from './WallAdvanceClock';

describe('WallAdvanceClock', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-08T22:10:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('mostra a contagem regressiva da proxima troca quando a exibicao esta ao vivo', () => {
    render(
      <WallAdvanceClock
        advancedAt="2026-04-08T22:09:55Z"
        intervalMs={10000}
        isLive
        isPaused={false}
      />,
    );

    expect(screen.getByText(/Troca prevista em 5s/i)).toBeInTheDocument();
  });

  it('mostra que a troca esta congelada quando o wall esta pausado', () => {
    render(
      <WallAdvanceClock
        advancedAt="2026-04-08T22:09:55Z"
        intervalMs={10000}
        isLive={false}
        isPaused
      />,
    );

    expect(screen.getByText(/Troca congelada na pausa/i)).toBeInTheDocument();
  });
});

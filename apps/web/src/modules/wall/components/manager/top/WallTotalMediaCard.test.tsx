import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { WallTotalMediaCard } from './WallTotalMediaCard';

describe('WallTotalMediaCard', () => {
  it('mostra a quantidade autoritativa de exibidas quando o backend ja entregou o total', () => {
    render(
      <WallTotalMediaCard
        totals={{
          received: 24,
          approved: 20,
          queued: 14,
          displayed: 9,
        }}
        lastCaptureAt="2026-04-09T03:45:00Z"
      />,
    );

    expect(screen.getByText(/Total de midias/i)).toBeInTheDocument();
    expect(screen.getByText(/^Exibidas$/i)).toBeInTheDocument();
    expect(screen.getByText(/^9$/i)).toBeInTheDocument();
  });
});

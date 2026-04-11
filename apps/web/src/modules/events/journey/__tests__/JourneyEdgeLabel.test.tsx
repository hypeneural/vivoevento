import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { JourneyEdgeLabel } from '../JourneyEdgeLabel';

describe('JourneyEdgeLabel', () => {
  it('renders the label text with the expected semantic classes and transform', () => {
    render(
      <JourneyEdgeLabel
        label="Aprovado"
        x={180}
        y={320}
        className="border-emerald-200 bg-emerald-50 text-emerald-800"
      />,
    );

    const label = screen.getByText('Aprovado');

    expect(label).toBeInTheDocument();
    expect(label).toHaveClass('nodrag');
    expect(label).toHaveClass('nopan');
    expect(label).toHaveClass('border-emerald-200');
    expect(label).toHaveStyle({
      transform: 'translate(-50%, -50%) translate(180px, 320px)',
      pointerEvents: 'all',
    });
  });
});

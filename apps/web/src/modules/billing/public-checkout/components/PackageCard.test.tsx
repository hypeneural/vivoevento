import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { PackageCard } from './PackageCard';

describe('PackageCard', () => {
  it('renders the native marketing badge label and selects the package', () => {
    const onSelect = vi.fn();

    render(
      <PackageCard
        pkg={{
          id: 1,
          code: 'interactive-event',
          name: 'Interativo',
          subtitle: 'O pacote mais equilibrado para eventos sociais com compra rapida.',
          idealFor: 'Casamentos e aniversarios com telao ao vivo.',
          benefits: ['Telao ao vivo para os convidados'],
          recommended: true,
          badgeLabel: 'Mais escolhido',
          deepLinkKey: 'casamento-interativo',
          priceLabel: 'R$ 199,00',
          raw: {} as never,
        }}
        onSelect={onSelect}
      />,
    );

    expect(screen.getByText('Mais escolhido')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /escolher este pacote/i }));

    expect(onSelect).toHaveBeenCalledTimes(1);
  });
});

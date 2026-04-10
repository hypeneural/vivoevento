import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { CheckoutHeroSimple } from './CheckoutHeroSimple';

describe('CheckoutHeroSimple', () => {
  it('keeps the public checkout hero compact on mobile without losing trust copy', () => {
    render(<CheckoutHeroSimple />);

    expect(screen.getByTestId('public-checkout-hero')).toHaveClass('space-y-2');
    expect(screen.getByRole('heading', { name: /contrate seu evento em poucos minutos/i })).toHaveClass('text-2xl');
    expect(screen.getByTestId('public-checkout-trust-row')).toHaveClass('hidden', 'sm:flex');
    expect(screen.getByText(/pagamento seguro/i)).toBeInTheDocument();
    expect(screen.getByText(/confirmacao automatica/i)).toBeInTheDocument();
  });
});

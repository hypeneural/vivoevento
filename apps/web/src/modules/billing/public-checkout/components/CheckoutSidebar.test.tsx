import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { CheckoutSidebar } from './CheckoutSidebar';

describe('CheckoutSidebar', () => {
  it('renders the selected package summary and the next-step guidance', () => {
    render(
      <CheckoutSidebar
        currentStep="payment"
        selectedPackage={{
          name: 'Casamento Essencial',
          priceLabel: 'R$ 199,00',
          subtitle: 'Pacote enxuto para evento unico.',
        }}
      />,
    );

    expect(screen.getByText(/^Seu pacote$/i)).toBeInTheDocument();
    expect(screen.getByText('Casamento Essencial')).toBeInTheDocument();
    expect(screen.getByText('R$ 199,00')).toBeInTheDocument();
    expect(screen.getByText(/escolha pix ou cartao para concluir a compra com seguranca/i)).toBeInTheDocument();
    expect(screen.getByText(/seus dados sensiveis de cartao continuam fora do nosso servidor/i)).toBeInTheDocument();
  });
});

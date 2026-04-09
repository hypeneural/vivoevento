import { useState } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { PaymentMethodTabs } from './PaymentMethodTabs';

describe('PaymentMethodTabs', () => {
  it('keeps Pix selected by default and lets the buyer switch to card', () => {
    function Wrapper() {
      const [value, setValue] = useState<'pix' | 'credit_card'>('pix');

      return (
        <div>
          <PaymentMethodTabs value={value} onValueChange={setValue} />
          <p>Metodo atual: {value}</p>
        </div>
      );
    }

    render(<Wrapper />);

    expect(screen.getByText(/metodo atual: pix/i)).toBeInTheDocument();

    const cardTab = screen.getByRole('tab', { name: /cartao/i });

    fireEvent.mouseDown(cardTab);
    fireEvent.click(cardTab);

    expect(screen.getByText(/metodo atual: credit_card/i)).toBeInTheDocument();
  });
});

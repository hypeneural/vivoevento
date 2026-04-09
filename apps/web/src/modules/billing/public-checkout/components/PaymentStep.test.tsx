import { fireEvent, render, screen } from '@testing-library/react';
import { useForm } from 'react-hook-form';
import { describe, expect, it, vi } from 'vitest';

import { Form } from '@/components/ui/form';

import { initialCheckoutV2Values, type CheckoutV2FormValues } from '../support/checkoutFormSchema';
import { PaymentStep } from './PaymentStep';

function renderPaymentStep(overrides: Partial<CheckoutV2FormValues> = {}) {
  const onBack = vi.fn();
  const onSubmit = vi.fn();

  function Wrapper() {
    const form = useForm<CheckoutV2FormValues>({
      defaultValues: {
        ...initialCheckoutV2Values,
        ...overrides,
      },
    });

    return (
      <Form {...form}>
        <PaymentStep
          selectedPackage={{
            id: 1,
            name: 'Casamento Essencial',
            subtitle: 'Ideal para quem quer resolver rapido.',
            priceLabel: 'R$ 199,00',
            benefits: [],
            recommended: false,
            idealFor: 'Eventos intimistas',
          }}
          onBack={onBack}
          onSubmit={onSubmit}
        />
      </Form>
    );
  }

  render(<Wrapper />);

  return {
    onBack,
    onSubmit,
  };
}

describe('PaymentStep', () => {
  it('keeps Pix as the default path and shows the shorter payment copy', () => {
    const { onSubmit } = renderPaymentStep();

    expect(screen.getByText(/pague com pix/i)).toBeInTheDocument();
    expect(screen.getByText(/valor desta compra: r\$ 199,00/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/numero do cartao/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /gerar meu pix/i }));

    expect(onSubmit).toHaveBeenCalledTimes(1);
  });

  it('reveals the credit card fields only when the buyer chooses card', () => {
    const { onBack } = renderPaymentStep({
      payment_method: 'credit_card',
    });

    expect(screen.getByRole('heading', { name: /pagamento seguro por cartao/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/cpf do pagador/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/numero do cartao/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /finalizar com cartao/i })).toBeInTheDocument();
    expect(screen.queryByText(/pague com pix/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /voltar para seus dados/i }));

    expect(onBack).toHaveBeenCalledTimes(1);
  });
});

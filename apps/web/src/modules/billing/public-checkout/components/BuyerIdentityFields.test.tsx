import { fireEvent, render, screen } from '@testing-library/react';
import { useForm } from 'react-hook-form';
import { describe, expect, it } from 'vitest';

import { Form } from '@/components/ui/form';

import { BuyerIdentityFields } from './BuyerIdentityFields';

type BuyerIdentityFieldsValues = {
  responsible_name: string;
  whatsapp: string;
  email: string;
};

function renderFields(defaultValues?: Partial<BuyerIdentityFieldsValues>) {
  function TestForm() {
    const form = useForm<BuyerIdentityFieldsValues>({
      defaultValues: {
        responsible_name: '',
        whatsapp: '',
        email: '',
        ...defaultValues,
      },
    });

    return (
      <Form {...form}>
        <form>
          <BuyerIdentityFields />
        </form>
      </Form>
    );
  }

  return render(<TestForm />);
}

describe('BuyerIdentityFields', () => {
  it('uses clearer buyer labels for a low-friction checkout', () => {
    renderFields();

    expect(screen.getByLabelText(/seu nome completo/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/whatsapp com ddd/i)).toBeInTheDocument();
  });

  it('formats the WhatsApp field and removes non-digit noise while the buyer types', () => {
    renderFields();

    const whatsappInput = screen.getByLabelText(/whatsapp com ddd/i);

    fireEvent.change(whatsappInput, {
      target: { value: 'abc48999771111' },
    });

    expect(whatsappInput).toHaveValue('(48) 99977-1111');
  });

  it('uses telephone input hints on the WhatsApp field', () => {
    renderFields();

    const whatsappInput = screen.getByLabelText(/whatsapp com ddd/i);

    expect(whatsappInput).toHaveAttribute('inputmode', 'tel');
    expect(whatsappInput).toHaveAttribute('autocomplete', 'tel');
  });
});

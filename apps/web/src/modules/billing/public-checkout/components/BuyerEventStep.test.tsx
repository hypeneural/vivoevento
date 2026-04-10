import { fireEvent, render, screen } from '@testing-library/react';
import { useForm } from 'react-hook-form';
import { describe, expect, it, vi } from 'vitest';

import { Form } from '@/components/ui/form';

import { BuyerEventStep } from './BuyerEventStep';

type BuyerEventStepValues = {
  responsible_name: string;
  whatsapp: string;
  email: string;
  event_title: string;
  event_type: string;
  organization_name: string;
  event_date: string;
  event_city: string;
  event_description: string;
};

function renderStep() {
  const onContinue = vi.fn();
  const onUseExistingAccount = vi.fn();

  function TestForm() {
    const form = useForm<BuyerEventStepValues>({
      defaultValues: {
        responsible_name: '',
        whatsapp: '',
        email: '',
        event_title: '',
        event_type: 'wedding',
        organization_name: '',
        event_date: '',
        event_city: '',
        event_description: '',
      },
    });

    return (
      <Form {...form}>
        <form>
          <BuyerEventStep
            onContinue={onContinue}
            onUseExistingAccount={onUseExistingAccount}
          />
        </form>
      </Form>
    );
  }

  const view = render(<TestForm />);

  return {
    ...view,
    onContinue,
    onUseExistingAccount,
  };
}

describe('BuyerEventStep', () => {
  it('collects the optional event schedule as date and time', () => {
    renderStep();

    fireEvent.click(screen.getByRole('button', { name: /adicionar mais detalhes/i }));

    const eventDateInput = screen.getByLabelText(/quando seu evento acontece/i);

    expect(eventDateInput).toHaveAttribute('type', 'datetime-local');
  });
});

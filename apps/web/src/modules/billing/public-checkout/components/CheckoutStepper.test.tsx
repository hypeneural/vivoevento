import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { CheckoutStepper } from './CheckoutStepper';

describe('CheckoutStepper', () => {
  it('renders the status state outside the payment accordion content', () => {
    render(
      <CheckoutStepper
        currentStep="status"
        progressValue={100}
        completedSteps={['package', 'details', 'payment']}
        steps={[
          { key: 'package', label: 'Pacote', summary: 'Pacote escolhido.' },
          { key: 'details', label: 'Seus dados', summary: 'Dados confirmados.' },
          { key: 'payment', label: 'Pagamento', summary: 'Pagamento iniciado.' },
        ]}
        childrenByStep={{
          package: <div>Conteudo do pacote</div>,
          details: <div>Conteudo dos dados</div>,
          payment: <div>Status final do pagamento</div>,
        }}
      />,
    );

    expect(screen.getByText(/status final do pagamento/i)).toBeInTheDocument();
    expect(screen.getByText(/pacote escolhido/i)).toBeInTheDocument();
    expect(screen.getByText(/dados confirmados/i)).toBeInTheDocument();
    expect(screen.getByText(/pagamento iniciado/i)).toBeInTheDocument();
    expect(screen.queryByText(/conteudo do pacote/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/conteudo dos dados/i)).not.toBeInTheDocument();
  });
});

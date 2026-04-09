import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation, useSearchParams } from 'react-router-dom';
import { describe, expect, it } from 'vitest';

import { usePublicCheckoutWizard } from './usePublicCheckoutWizard';

function WizardHarness() {
  const [searchParams, setSearchParams] = useSearchParams();
  const location = useLocation();
  const wizard = usePublicCheckoutWizard({
    searchParams,
    setSearchParams,
    summaries: {
      package: 'Pacote definido.',
      details: 'Dados em andamento.',
      payment: 'Pagamento pronto.',
    },
  });

  return (
    <div>
      <p data-testid="current-step">{wizard.currentStep}</p>
      <p data-testid="progress">{wizard.progressValue}</p>
      <p data-testid="search">{location.search}</p>
      <button type="button" onClick={() => wizard.goToStep('details')}>
        ir-detalhes
      </button>
      <button type="button" onClick={() => wizard.goToStep('payment')}>
        ir-pagamento
      </button>
      <button type="button" onClick={() => wizard.goBack()}>
        voltar
      </button>
    </div>
  );
}

function renderHarness(initialEntry = '/checkout/evento?v2=1') {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/checkout/evento" element={<WizardHarness />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('usePublicCheckoutWizard', () => {
  it('starts at package step when the URL has no explicit step', () => {
    renderHarness('/checkout/evento?v2=1');

    expect(screen.getByTestId('current-step')).toHaveTextContent('package');
    expect(screen.getByTestId('progress')).toHaveTextContent('0');
  });

  it('syncs step changes back to the URL while preserving the V2 flag', () => {
    renderHarness('/checkout/evento?v2=1');

    fireEvent.click(screen.getByRole('button', { name: 'ir-detalhes' }));

    expect(screen.getByTestId('current-step')).toHaveTextContent('details');
    expect(screen.getByTestId('search')).toHaveTextContent('v2=1');
    expect(screen.getByTestId('search')).toHaveTextContent('step=details');
  });

  it('supports going back to the previous step', () => {
    renderHarness('/checkout/evento?v2=1&step=payment');

    fireEvent.click(screen.getByRole('button', { name: 'voltar' }));

    expect(screen.getByTestId('current-step')).toHaveTextContent('details');
    expect(screen.getByTestId('search')).toHaveTextContent('step=details');
  });
});

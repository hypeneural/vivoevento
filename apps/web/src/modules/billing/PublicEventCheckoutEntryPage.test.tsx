import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';

import PublicEventCheckoutEntryPage from './PublicEventCheckoutEntryPage';

vi.mock('./public-checkout/PublicCheckoutPageV2', () => ({
  PublicCheckoutPageV2: () => <div>checkout-v2-page</div>,
}));

function renderPage(initialEntry: string) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/checkout/evento" element={<PublicEventCheckoutEntryPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('PublicEventCheckoutEntryPage', () => {
  it('uses the V2 checkout as the default public experience', () => {
    renderPage('/checkout/evento');

    expect(screen.getByText('checkout-v2-page')).toBeInTheDocument();
  });

  it('still supports the explicit V2 flag for older links', () => {
    renderPage('/checkout/evento?v2=1');

    expect(screen.getByText('checkout-v2-page')).toBeInTheDocument();
  });

  it('ignores legacy=1 and keeps the V2 checkout active', () => {
    renderPage('/checkout/evento?legacy=1');

    expect(screen.getByText('checkout-v2-page')).toBeInTheDocument();
  });
});

import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';

import PublicEventCheckoutEntryPage from './PublicEventCheckoutEntryPage';

vi.mock('./PublicEventCheckoutPage', () => ({
  default: () => <div>legacy-checkout-page</div>,
}));

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
  it('keeps the legacy checkout as default', () => {
    renderPage('/checkout/evento');

    expect(screen.getByText('legacy-checkout-page')).toBeInTheDocument();
    expect(screen.queryByText('checkout-v2-page')).not.toBeInTheDocument();
  });

  it('activates the V2 checkout only when v2=1 is present', () => {
    renderPage('/checkout/evento?v2=1');

    expect(screen.getByText('checkout-v2-page')).toBeInTheDocument();
    expect(screen.queryByText('legacy-checkout-page')).not.toBeInTheDocument();
  });
});

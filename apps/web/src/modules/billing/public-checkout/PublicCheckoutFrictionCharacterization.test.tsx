import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventPackage } from '@/lib/api-types';

import { PublicCheckoutPageV2 } from './PublicCheckoutPageV2';
import { PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY } from './support/checkoutFormUtils';

const useAuthMock = vi.fn();
const listPackagesMock = vi.fn();
const createCheckoutMock = vi.fn();
const getCheckoutMock = vi.fn();
const useCheckoutIdentityPrecheckMock = vi.fn();
const useIsMobileMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

vi.mock('../services/public-event-packages.service', () => ({
  publicEventPackagesService: {
    list: (...args: unknown[]) => listPackagesMock(...args),
  },
}));

vi.mock('../services/public-event-checkout.service', () => ({
  publicEventCheckoutService: {
    create: (...args: unknown[]) => createCheckoutMock(...args),
    get: (...args: unknown[]) => getCheckoutMock(...args),
  },
}));

vi.mock('./hooks/useCheckoutIdentityPrecheck', () => ({
  useCheckoutIdentityPrecheck: (...args: unknown[]) => useCheckoutIdentityPrecheckMock(...args),
}));

vi.mock('@/lib/pagarme-tokenization', () => ({
  createPagarmeCardToken: vi.fn(),
  PagarmeTokenizationError: class PagarmeTokenizationError extends Error {},
}));

function makePackage(): ApiEventPackage {
  return {
    id: 1,
    code: 'casamento-essencial',
    name: 'Casamento Essencial',
    description: 'Pacote enxuto para evento unico.',
    target_audience: 'direct_customer',
    is_active: true,
    sort_order: 1,
    default_price: {
      id: 10,
      billing_mode: 'one_time',
      currency: 'BRL',
      amount_cents: 19900,
      is_active: true,
      is_default: true,
    },
    prices: [],
    features: [],
    feature_map: {
      'hub.enabled': true,
      'wall.enabled': true,
      'play.enabled': false,
    },
    checkout_marketing: {
      slug: 'casamento-essencial',
      subtitle: 'O pacote mais equilibrado para eventos sociais com compra rapida.',
      ideal_for: 'Casamentos e aniversarios com telao ao vivo.',
      benefits: [
        'Telao ao vivo para os convidados',
        'Pagina do evento pronta para compartilhar',
        'Pix e cartao com confirmacao automatica',
      ],
      badge: 'Mais escolhido',
      recommended: true,
    },
    modules: {
      hub: true,
      wall: true,
      play: false,
    },
    limits: {
      retention_days: 90,
      max_photos: 400,
    },
  };
}

function renderPage(initialEntry = '/checkout/evento?v2=1') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/checkout/evento" element={<PublicCheckoutPageV2 />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function LoginProbe() {
  const location = useLocation();

  return <div>{`login-screen:${location.search}`}</div>;
}

function renderPageWithLoginRoute(initialEntry = '/checkout/evento?v2=1') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/checkout/evento" element={<PublicCheckoutPageV2 />} />
          <Route path="/login" element={<LoginProbe />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

async function openDetailsStep() {
  fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));
  expect(await screen.findByLabelText(/seu nome/i)).toBeInTheDocument();
}

function fillBasicDetails() {
  fireEvent.change(screen.getByLabelText(/seu nome/i), {
    target: { value: 'Camila Rocha' },
  });
  fireEvent.change(screen.getByLabelText(/whatsapp/i), {
    target: { value: '(48) 99977-1111' },
  });
  fireEvent.change(screen.getByLabelText(/nome do evento/i), {
    target: { value: 'Casamento Camila e Bruno' },
  });
}

describe('Public checkout friction characterization', () => {
  beforeEach(() => {
    useAuthMock.mockReturnValue({ isAuthenticated: false, refreshSession: vi.fn() });
    useIsMobileMock.mockReturnValue(false);
    listPackagesMock.mockResolvedValue([makePackage()]);
    createCheckoutMock.mockReset();
    getCheckoutMock.mockReset();
    useCheckoutIdentityPrecheckMock.mockReturnValue({
      identityAssist: null,
      isChecking: false,
      isReady: true,
    });
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it('now formats the buyer WhatsApp field while the user types', async () => {
    renderPage();

    await openDetailsStep();

    const whatsappInput = screen.getByLabelText(/whatsapp/i);

    fireEvent.change(whatsappInput, {
      target: { value: 'abc48999771111' },
    });

    expect(whatsappInput).toHaveValue('(48) 99977-1111');
  });

  it('now renders the optional event schedule field with date and time inside more details', async () => {
    renderPage();

    await openDetailsStep();

    fireEvent.click(screen.getByRole('button', { name: /adicionar mais detalhes/i }));

    const eventDateInput = screen.getByLabelText(/quando seu evento acontece/i);

    expect(eventDateInput).toHaveAttribute('type', 'datetime-local');
  });

  it('persists a safe manual resume draft when "Ja tenho conta" is used before payment', async () => {
    renderPageWithLoginRoute('/checkout/evento?package=casamento-essencial');

    await screen.findByLabelText(/seu nome/i);
    fillBasicDetails();

    fireEvent.click(screen.getByRole('button', { name: /ja tenho conta/i }));

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      package_id: '1',
      payment_method: 'pix',
      source: 'manual_login',
      version: 1,
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
    expect(await screen.findByText(/^login-screen:/i)).toHaveTextContent(
      'returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth%26package%3Dcasamento-essencial',
    );
  });

  it('now uses the mobile footer as a sticky step CTA while keeping the summary as a secondary action', async () => {
    useIsMobileMock.mockReturnValue(true);
    renderPage();

    await openDetailsStep();
    fillBasicDetails();

    expect(await screen.findByTestId('public-checkout-mobile-footer')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /ver resumo/i })).toBeInTheDocument();
    expect(screen.getByTestId('public-checkout-mobile-primary-cta')).toHaveTextContent(/continuar para pagamento/i);
  });
});

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventPackage, PublicEventCheckoutResponse } from '@/lib/api-types';
import { PublicCheckoutPageV2 } from './PublicCheckoutPageV2';

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

function makeResponse(): PublicEventCheckoutResponse {
  return {
    message: 'Checkout iniciado com sucesso.',
    token: 'token_public_checkout',
    user: null,
    organization: null,
    event: null,
    commercial_status: null,
    checkout: {
      id: 11,
      uuid: 'checkout-uuid',
      mode: 'event_package',
      status: 'pending_payment',
      currency: 'BRL',
      total_cents: 19900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
      summary: {
        state: 'pending',
        tone: 'info',
        payment_status_title: 'Pix gerado com sucesso',
        order_status_label: 'Pedido criado',
        payment_status_label: 'Aguardando pagamento',
        payment_status_description: 'Use o QR Code para pagar.',
        next_action: 'wait_payment_confirmation',
        expires_in_seconds: 1800,
        is_waiting_payment: true,
        can_retry: false,
      },
      payment: {
        provider: 'pagarme',
        method: 'pix',
        gateway_order_id: 'or_test_123',
        gateway_charge_id: 'ch_test_123',
        gateway_transaction_id: null,
        gateway_status: 'pending',
        status: 'pending_payment',
        checkout_url: null,
        confirm_url: null,
        expires_at: '2026-04-05T10:30:00Z',
        pix: {
          qr_code: '000201010212',
          qr_code_url: 'https://example.test/qr.png',
          expires_at: '2026-04-05T10:30:00Z',
        },
        credit_card: null,
        whatsapp: {
          pix_generated: null,
          payment_paid: null,
          payment_failed: null,
          payment_refunded: null,
        },
      },
      package: {
        id: 1,
        code: 'casamento-essencial',
        name: 'Casamento Essencial',
        description: 'Pacote enxuto para evento unico.',
        target_audience: 'direct_customer',
      },
      items: [],
    },
    purchase: null,
    onboarding: {
      title: 'Evento pronto',
      description: 'Finalize o pagamento para ativar o pacote.',
      next_path: '/events/11',
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

function fillDetailsStep() {
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

describe('PublicCheckoutPageV2 mobile layout', () => {
  beforeEach(() => {
    useAuthMock.mockReturnValue({ isAuthenticated: false, refreshSession: vi.fn() });
    useIsMobileMock.mockReturnValue(true);
    listPackagesMock.mockResolvedValue([makePackage()]);
    createCheckoutMock.mockResolvedValue(makeResponse());
    getCheckoutMock.mockResolvedValue(makeResponse());
    useCheckoutIdentityPrecheckMock.mockReturnValue({
      identityAssist: null,
      isChecking: false,
      isReady: true,
    });
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it('renders a sticky mobile footer and opens the secondary summary drawer', async () => {
    renderPage();

    expect(await screen.findByTestId('public-checkout-mobile-footer')).toBeInTheDocument();
    expect(screen.queryByTestId('public-checkout-mobile-primary-cta')).not.toBeInTheDocument();
    expect(screen.queryByText(/^Seu pacote$/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /ver resumo/i }));

    await waitFor(() => {
      expect(screen.getByTestId('public-checkout-mobile-drawer')).toBeInTheDocument();
    });

    expect(screen.getByRole('heading', { name: /resumo da compra/i })).toBeInTheDocument();
    expect(screen.getByText(/^Seu pacote$/i)).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: /pagamento seguro/i })).toBeInTheDocument();
  }, 10000);

  it('uses the sticky footer as the primary mobile CTA for details and payment', async () => {
    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));
    fillDetailsStep();

    const detailsCta = screen.getByTestId('public-checkout-mobile-primary-cta');

    expect(detailsCta).toHaveTextContent(/continuar para pagamento/i);

    fireEvent.click(detailsCta);

    const paymentCta = await screen.findByTestId('public-checkout-mobile-primary-cta');

    expect(paymentCta).toHaveTextContent(/gerar meu pix/i);

    fireEvent.click(paymentCta);

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });
  });

  it('keeps the buyer data when the user goes back from payment on mobile', async () => {
    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));
    expect(screen.getByTestId('public-checkout-mobile-footer')).toHaveTextContent('Casamento Essencial');

    fillDetailsStep();
    fireEvent.click(screen.getByTestId('public-checkout-mobile-primary-cta'));

    expect(await screen.findByRole('button', { name: /gerar meu pix/i })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /voltar para seus dados/i }));

    await waitFor(() => {
      expect(screen.getByLabelText(/seu nome/i)).toHaveValue('Camila Rocha');
    });

    expect(screen.getByLabelText(/whatsapp/i)).toHaveValue('(48) 99977-1111');
    expect(screen.getByLabelText(/nome do evento/i)).toHaveValue('Casamento Camila e Bruno');
  });
});

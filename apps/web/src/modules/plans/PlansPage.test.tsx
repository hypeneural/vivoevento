import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type {
  ApiBillingCheckoutResponse,
  ApiBillingInvoice,
  ApiBillingSubscription,
  ApiPlan,
} from '@/lib/api-types';

import PlansPage from './PlansPage';

const useAuthMock = vi.fn();
const listCatalogMock = vi.fn();
const getCurrentSubscriptionMock = vi.fn();
const listInvoicesMock = vi.fn();
const checkoutMock = vi.fn();
const cancelSubscriptionMock = vi.fn();
const toastMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

vi.mock('./api', () => ({
  plansService: {
    listCatalog: (...args: unknown[]) => listCatalogMock(...args),
    getCurrentSubscription: (...args: unknown[]) => getCurrentSubscriptionMock(...args),
    listInvoices: (...args: unknown[]) => listInvoicesMock(...args),
    checkout: (...args: unknown[]) => checkoutMock(...args),
    cancelSubscription: (...args: unknown[]) => cancelSubscriptionMock(...args),
  },
}));

function makePlan(): ApiPlan {
  return {
    id: 1,
    code: 'starter',
    name: 'Starter',
    audience: 'b2b',
    status: 'active',
    description: 'Plano para operacao recorrente da conta.',
    prices: [
      {
        id: 10,
        plan_id: 1,
        billing_cycle: 'monthly',
        currency: 'BRL',
        amount_cents: 9900,
        gateway_provider: 'manual',
        gateway_price_id: null,
        is_default: true,
      },
      {
        id: 11,
        plan_id: 1,
        billing_cycle: 'yearly',
        currency: 'BRL',
        amount_cents: 99000,
        gateway_provider: 'manual',
        gateway_price_id: null,
        is_default: false,
      },
    ],
    features: [
      {
        id: 100,
        plan_id: 1,
        feature_key: 'wall.enabled',
        feature_value: 'true',
      },
      {
        id: 101,
        plan_id: 1,
        feature_key: 'events.max_active',
        feature_value: '5',
      },
    ],
    created_at: null,
    updated_at: null,
  };
}

function makeSubscription(): ApiBillingSubscription {
  return {
    id: 12,
    plan_key: 'starter',
    plan_name: 'Starter',
    billing_cycle: 'monthly',
    status: 'active',
    starts_at: '2026-04-01T10:00:00Z',
    trial_ends_at: null,
    renews_at: '2026-05-01T10:00:00Z',
    ends_at: null,
    canceled_at: null,
    cancel_at_period_end: false,
    cancellation_effective_at: null,
    features: {
      'wall.enabled': 'true',
      'events.max_active': '5',
    },
  };
}

function makeInvoice(): ApiBillingInvoice {
  return {
    id: 20,
    invoice_number: 'EVV-2026-0001',
    status: 'paid',
    amount_cents: 9900,
    currency: 'BRL',
    issued_at: '2026-04-01T10:00:00Z',
    due_at: '2026-04-01T10:00:00Z',
    paid_at: '2026-04-01T10:05:00Z',
    order: {
      id: 33,
      uuid: 'billing-order-uuid',
      mode: 'subscription',
      status: 'paid',
    },
    event: null,
    package: null,
    plan: {
      id: 1,
      code: 'starter',
      name: 'Starter',
    },
    payment: {
      id: 44,
      status: 'paid',
      amount_cents: 9900,
      currency: 'BRL',
      gateway_provider: 'manual',
      gateway_payment_id: 'pay_123',
      paid_at: '2026-04-01T10:05:00Z',
    },
    snapshot: {},
  };
}

function makeCheckoutResponse(): ApiBillingCheckoutResponse {
  return {
    subscription_id: null,
    plan_name: 'Starter',
    status: 'pending_payment',
    starts_at: null,
    renews_at: null,
    billing_order_id: 91,
    payment_id: null,
    invoice_id: null,
    checkout: {
      provider: 'pagarme',
      gateway_order_id: 'or_checkout_123',
      status: 'pending_payment',
      checkout_url: 'https://checkout.example.com/pay/starter',
      confirm_url: null,
      expires_at: '2026-04-05T22:00:00Z',
    },
  };
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <PlansPage />
    </QueryClientProvider>,
  );
}

describe('PlansPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      meOrganization: {
        id: 1,
        name: 'Evento Vivo Studio',
      },
      can: (permission: string) => [
        'billing.view',
        'billing.manage',
        'billing.purchase',
        'billing.manage_subscription',
      ].includes(permission),
    });

    listCatalogMock.mockResolvedValue([makePlan()]);
    getCurrentSubscriptionMock.mockResolvedValue(null);
    listInvoicesMock.mockResolvedValue({
      success: true,
      data: [],
      meta: {
        page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    });
    checkoutMock.mockResolvedValue(makeCheckoutResponse());
    cancelSubscriptionMock.mockResolvedValue({});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows a pending checkout panel with a clear action to open the provider checkout', async () => {
    renderPage();

    expect(await screen.findByText('Starter')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /ativar plano/i }));

    expect((await screen.findAllByText(/pagamento pendente/i)).length).toBeGreaterThan(0);
    const checkoutLinks = screen.getAllByRole('link', { name: /abrir checkout/i });

    expect(
      checkoutLinks.some((link) => link.getAttribute('href') === 'https://checkout.example.com/pay/starter'),
    ).toBe(true);
    expect(screen.getByText(/gateway_order_id/i)).toBeInTheDocument();
  });

  it('summarizes the current account state and latest invoice for the logged organization', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription());
    listInvoicesMock.mockResolvedValue({
      success: true,
      data: [makeInvoice()],
      meta: {
        page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    });

    renderPage();

    expect(await screen.findByText(/conta protegida pelo plano/i)).toBeInTheDocument();
    expect(screen.getByText(/renovacao prevista/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /ver cobrancas/i })).toBeInTheDocument();
    expect(screen.getByText('EVV-2026-0001')).toBeInTheDocument();
  });

  it('shows the catalog error state when the plans payload is malformed', async () => {
    listCatalogMock.mockResolvedValue({
      data: [],
    });

    renderPage();

    expect(await screen.findByText(/falha ao carregar o catalogo/i)).toBeInTheDocument();
  });
});

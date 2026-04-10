import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type {
  ApiBillingCheckoutResponse,
  ApiBillingCard,
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
const listWalletCardsMock = vi.fn();
const updateSubscriptionCardMock = vi.fn();
const reconcileSubscriptionMock = vi.fn();
const createPagarmeCardTokenMock = vi.fn();
const toastMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

vi.mock('@/lib/pagarme-tokenization', () => ({
  createPagarmeCardToken: (...args: unknown[]) => createPagarmeCardTokenMock(...args),
  PagarmeTokenizationError: class PagarmeTokenizationError extends Error {
    status: number;
    body: unknown;

    constructor(message: string, status = 422, body: unknown = null) {
      super(message);
      this.name = 'PagarmeTokenizationError';
      this.status = status;
      this.body = body;
    }
  },
}));

vi.mock('./api', () => ({
  plansService: {
    listCatalog: (...args: unknown[]) => listCatalogMock(...args),
    getCurrentSubscription: (...args: unknown[]) => getCurrentSubscriptionMock(...args),
    listInvoices: (...args: unknown[]) => listInvoicesMock(...args),
    checkout: (...args: unknown[]) => checkoutMock(...args),
    cancelSubscription: (...args: unknown[]) => cancelSubscriptionMock(...args),
    listWalletCards: (...args: unknown[]) => listWalletCardsMock(...args),
    updateSubscriptionCard: (...args: unknown[]) => updateSubscriptionCardMock(...args),
    reconcileSubscription: (...args: unknown[]) => reconcileSubscriptionMock(...args),
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

function makeSubscription(overrides: Partial<ApiBillingSubscription> = {}): ApiBillingSubscription {
  return {
    id: 12,
    plan_key: 'starter',
    plan_name: 'Starter',
    billing_cycle: 'monthly',
    status: 'active',
    contract_status: 'active',
    billing_status: 'paid',
    access_status: 'enabled',
    payment_method: 'credit_card',
    starts_at: '2026-04-01T10:00:00Z',
    trial_ends_at: null,
    current_period_started_at: '2026-04-01T10:00:00Z',
    current_period_ends_at: '2026-05-01T10:00:00Z',
    renews_at: '2026-05-01T10:00:00Z',
    next_billing_at: '2026-05-01T10:00:00Z',
    ends_at: null,
    canceled_at: null,
    cancel_at_period_end: false,
    cancellation_effective_at: null,
    gateway_provider: 'pagarme',
    gateway_subscription_id: 'sub_test_123',
    gateway_customer_id: 'cus_test_123',
    gateway_card_id: 'card_current_123',
    features: {
      'wall.enabled': 'true',
      'events.max_active': '5',
    },
    ...overrides,
  };
}

function makeWalletCards(): ApiBillingCard[] {
  return [
    {
      id: 'card_current_123',
      brand: 'visa',
      holder_name: 'JOAO SILVA',
      last_four: '1111',
      exp_month: 12,
      exp_year: 2030,
      status: 'active',
      is_default: true,
      label: 'VISA - final 1111 - 12/30',
    },
    {
      id: 'card_new_123',
      brand: 'mastercard',
      holder_name: 'JOAO SILVA',
      last_four: '2222',
      exp_month: 11,
      exp_year: 2031,
      status: 'active',
      is_default: false,
      label: 'MASTERCARD - final 2222 - 11/31',
    },
  ];
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

async function openAndFillRecurringCheckoutDialog() {
  fireEvent.click(await screen.findByRole('button', { name: /contratar plano/i }));

  const dialog = await screen.findByRole('dialog');
  const scoped = within(dialog);

  fireEvent.change(scoped.getByLabelText(/nome do pagador/i), {
    target: { value: 'Parceiro Premium LTDA' },
  });
  fireEvent.change(scoped.getByLabelText(/e-mail de cobranca/i), {
    target: { value: 'financeiro@parceiro.test' },
  });
  fireEvent.change(scoped.getByLabelText(/cpf ou cnpj/i), {
    target: { value: '12345678000199' },
  });
  fireEvent.change(scoped.getByLabelText(/telefone/i), {
    target: { value: '11999999999' },
  });
  fireEvent.change(scoped.getByLabelText(/^rua$/i), {
    target: { value: 'Rua A' },
  });
  fireEvent.change(scoped.getByLabelText(/^numero$/i), {
    target: { value: '100' },
  });
  fireEvent.change(scoped.getByLabelText(/bairro/i), {
    target: { value: 'Centro' },
  });
  fireEvent.change(scoped.getByLabelText(/cep/i), {
    target: { value: '01001000' },
  });
  fireEvent.change(scoped.getByLabelText(/cidade/i), {
    target: { value: 'Sao Paulo' },
  });
  fireEvent.change(scoped.getByLabelText(/uf/i), {
    target: { value: 'SP' },
  });
  fireEvent.change(scoped.getByLabelText(/numero do cartao/i), {
    target: { value: '4111111111111111' },
  });
  fireEvent.change(scoped.getByLabelText(/nome impresso no cartao/i), {
    target: { value: 'JOAO SILVA' },
  });
  fireEvent.change(scoped.getByLabelText(/^mes$/i), {
    target: { value: '12' },
  });
  fireEvent.change(scoped.getByLabelText(/^ano$/i), {
    target: { value: '30' },
  });
  fireEvent.change(scoped.getByLabelText(/cvv/i), {
    target: { value: '123' },
  });

  return scoped;
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
      meUser: {
        id: 9,
        name: 'Joao Silva',
        email: 'joao@eventovivo.test',
      },
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
    listWalletCardsMock.mockResolvedValue([]);
    updateSubscriptionCardMock.mockResolvedValue({
      message: 'Cartao da assinatura atualizado.',
      subscription: makeSubscription({
        gateway_card_id: 'card_new_123',
      }),
    });
    reconcileSubscriptionMock.mockResolvedValue({
      provider_key: 'pagarme',
      subscription_id: 12,
      gateway_subscription_id: 'sub_test_123',
      cycles_reconciled: 1,
      invoices_reconciled: 1,
      charges_reconciled: 1,
      charge_details_loaded: 1,
      page: 1,
      size: 20,
      subscription: makeSubscription(),
    });
    createPagarmeCardTokenMock.mockResolvedValue({
      id: 'tok_checkout_123',
      type: 'card',
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('tokenizes the card only on the final submit and then shows the pending checkout panel', async () => {
    renderPage();

    expect(await screen.findByText('Starter')).toBeInTheDocument();

    const dialog = await openAndFillRecurringCheckoutDialog();

    expect(createPagarmeCardTokenMock).not.toHaveBeenCalled();
    expect(checkoutMock).not.toHaveBeenCalled();

    fireEvent.click(dialog.getByRole('button', { name: /confirmar contratacao/i }));

    await waitFor(() => {
      expect(createPagarmeCardTokenMock).toHaveBeenCalledTimes(1);
    });
    await waitFor(() => {
      expect(checkoutMock).toHaveBeenCalled();
    });

    expect(checkoutMock.mock.calls[0]?.[0]).toEqual(expect.objectContaining({
      plan_id: 1,
      billing_cycle: 'monthly',
      payment_method: 'credit_card',
      credit_card: {
        card_token: 'tok_checkout_123',
      },
    }));

    expect((await screen.findAllByText(/pagamento pendente/i)).length).toBeGreaterThan(0);
    const checkoutLinks = screen.getAllByRole('link', { name: /continuar pagamento/i });

    expect(
      checkoutLinks.some((link) => link.getAttribute('href') === 'https://checkout.example.com/pay/starter'),
    ).toBe(true);
    expect(screen.queryByText(/gateway_order_id/i)).not.toBeInTheDocument();
  });

  it('blocks the backend checkout when card tokenization fails', async () => {
    createPagarmeCardTokenMock.mockRejectedValueOnce(new Error('Falha ao tokenizar cartao.'));

    renderPage();

    const dialog = await openAndFillRecurringCheckoutDialog();

    fireEvent.click(dialog.getByRole('button', { name: /confirmar contratacao/i }));

    expect(await screen.findByText(/falha ao tokenizar cartao/i)).toBeInTheDocument();
    expect(checkoutMock).not.toHaveBeenCalled();
  });

  it('summarizes the current account state and latest invoice for the logged organization', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription());
    listWalletCardsMock.mockResolvedValue(makeWalletCards());
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

  it('updates the recurring card with a saved wallet card without tokenizing again', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription());
    listWalletCardsMock.mockResolvedValue(makeWalletCards());

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /ver assinatura/i }));
    expect(await screen.findByText(/mastercard - final 2222/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /^usar$/i }));

    await waitFor(() => {
      expect(updateSubscriptionCardMock).toHaveBeenCalled();
    });
    expect(updateSubscriptionCardMock.mock.calls[0]?.[0]).toEqual({ card_id: 'card_new_123' });
    expect(createPagarmeCardTokenMock).not.toHaveBeenCalled();
  });

  it('tokenizes a new recurring card only when the final submit is confirmed', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription());
    listWalletCardsMock.mockResolvedValue(makeWalletCards());
    createPagarmeCardTokenMock.mockResolvedValueOnce({
      id: 'tok_card_update_123',
      type: 'card',
    });

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /ver assinatura/i }));
    fireEvent.click(await screen.findByRole('button', { name: /trocar cartao/i }));

    const dialog = await screen.findByRole('dialog');
    const scoped = within(dialog);

    fireEvent.change(scoped.getByLabelText(/numero do cartao/i), {
      target: { value: '4111111111111111' },
    });
    fireEvent.change(scoped.getByLabelText(/nome impresso no cartao/i), {
      target: { value: 'JOAO SILVA' },
    });
    fireEvent.change(scoped.getByLabelText(/^mes$/i), {
      target: { value: '12' },
    });
    fireEvent.change(scoped.getByLabelText(/^ano$/i), {
      target: { value: '30' },
    });
    fireEvent.change(scoped.getByLabelText(/cvv/i), {
      target: { value: '123' },
    });

    expect(createPagarmeCardTokenMock).not.toHaveBeenCalled();

    fireEvent.click(scoped.getByRole('button', { name: /salvar novo cartao/i }));

    await waitFor(() => {
      expect(createPagarmeCardTokenMock).toHaveBeenCalledTimes(1);
    });
    await waitFor(() => {
      expect(updateSubscriptionCardMock).toHaveBeenCalled();
    });
    expect(updateSubscriptionCardMock.mock.calls[0]?.[0]).toEqual({ card_token: 'tok_card_update_123' });
  });

  it('runs assisted reconcile from the subscription panel', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription());
    listWalletCardsMock.mockResolvedValue(makeWalletCards());

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /ver assinatura/i }));
    fireEvent.click(await screen.findByRole('button', { name: /sincronizar dados/i }));

    await waitFor(() => {
      expect(reconcileSubscriptionMock).toHaveBeenCalled();
    });
    expect(reconcileSubscriptionMock.mock.calls[0]?.[0]).toEqual({
      page: 1,
      size: 20,
      with_charge_details: true,
    });
  });

  it('allows changing the billing cycle of the current plan', async () => {
    getCurrentSubscriptionMock.mockResolvedValue(makeSubscription({
      billing_cycle: 'monthly',
    }));

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /anual/i }));

    expect(await screen.findByRole('button', { name: /alterar ciclo/i })).toBeInTheDocument();
  });

  it('shows the catalog error state when the plans payload is malformed', async () => {
    listCatalogMock.mockResolvedValue({
      data: [],
    });

    renderPage();

    expect(await screen.findByText(/falha ao carregar o catalogo/i)).toBeInTheDocument();
  });
});

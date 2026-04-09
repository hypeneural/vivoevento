import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { PublicEventCheckoutResponse } from '@/lib/api-types';

import { useCheckoutStatusPolling } from './useCheckoutStatusPolling';

const getCheckoutMock = vi.fn();

vi.mock('../../services/public-event-checkout.service', () => ({
  publicEventCheckoutService: {
    get: (...args: unknown[]) => getCheckoutMock(...args),
  },
}));

function createResponse(isWaitingPayment: boolean): PublicEventCheckoutResponse {
  return {
    message: 'Checkout iniciado com sucesso.',
    token: null,
    user: null,
    organization: null,
    event: null,
    commercial_status: null,
    checkout: {
      id: 11,
      uuid: 'checkout-uuid',
      mode: 'event_package',
      status: isWaitingPayment ? 'pending_payment' : 'paid',
      currency: 'BRL',
      total_cents: 19900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
      summary: {
        state: isWaitingPayment ? 'pending' : 'paid',
        tone: isWaitingPayment ? 'info' : 'success',
        payment_status_title: isWaitingPayment ? 'Pix gerado com sucesso' : 'Pagamento confirmado',
        order_status_label: isWaitingPayment ? 'Pedido criado' : 'Pedido pago',
        payment_status_label: isWaitingPayment ? 'Aguardando pagamento' : 'Confirmado',
        payment_status_description: 'Descricao resumida',
        next_action: isWaitingPayment ? 'wait_payment_confirmation' : 'open_event',
        expires_in_seconds: isWaitingPayment ? 1200 : null,
        is_waiting_payment: isWaitingPayment,
        can_retry: false,
      },
      payment: {
        provider: 'pagarme',
        method: 'pix',
        gateway_order_id: 'or_test_123',
        gateway_charge_id: 'ch_test_123',
        gateway_transaction_id: null,
        gateway_status: isWaitingPayment ? 'pending' : 'paid',
        status: isWaitingPayment ? 'pending_payment' : 'paid',
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
        description: 'Pacote enxuto',
        target_audience: 'direct_customer',
      },
      items: [],
    },
    purchase: null,
    onboarding: null,
  };
}

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
}

describe('useCheckoutStatusPolling', () => {
  beforeEach(() => {
    getCheckoutMock.mockReset();
  });

  it('does not run when the checkout uuid is missing', async () => {
    renderHook(() => useCheckoutStatusPolling({ checkoutUuid: null }), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      await Promise.resolve();
    });

    expect(getCheckoutMock).not.toHaveBeenCalled();
  });

  it('keeps polling while the semantic summary says payment is still waiting', async () => {
    getCheckoutMock.mockResolvedValue(createResponse(true));

    renderHook(() => useCheckoutStatusPolling({
      checkoutUuid: 'checkout-uuid',
      pollingIntervalMs: 50,
    }), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(getCheckoutMock).toHaveBeenCalledTimes(1);
    });

    await waitFor(() => {
      expect(getCheckoutMock).toHaveBeenCalledTimes(2);
    }, { timeout: 2000 });
  });

  it('stops polling when the semantic summary is terminal', async () => {
    getCheckoutMock.mockResolvedValue(createResponse(false));

    renderHook(() => useCheckoutStatusPolling({
      checkoutUuid: 'checkout-uuid',
      pollingIntervalMs: 50,
    }), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(getCheckoutMock).toHaveBeenCalledTimes(1);
    });

    await act(async () => {
      await new Promise((resolve) => window.setTimeout(resolve, 180));
    });

    expect(getCheckoutMock).toHaveBeenCalledTimes(1);
  });
});

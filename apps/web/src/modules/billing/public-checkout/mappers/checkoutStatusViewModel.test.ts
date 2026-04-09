import { describe, expect, it } from 'vitest';

import type { PublicEventCheckoutResponse } from '@/lib/api-types';

import { buildCheckoutStatusViewModel, shouldPollPublicCheckout } from './checkoutStatusViewModel';

function makeResponse(overrides: Partial<PublicEventCheckoutResponse> = {}): PublicEventCheckoutResponse {
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
      status: 'pending_payment',
      currency: 'BRL',
      total_cents: 19900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
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
        expires_at: '2099-04-05T10:30:00Z',
        pix: {
          qr_code: '000201010212',
          qr_code_url: 'https://pagar.me/qr/ch_test_123.png',
          expires_at: '2099-04-05T10:30:00Z',
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
    onboarding: null,
    ...overrides,
  };
}

describe('checkoutStatusViewModel', () => {
  it('builds a friendly Pix pending status without exposing transport terms', () => {
    const viewModel = buildCheckoutStatusViewModel(makeResponse(), new Date('2099-04-05T10:10:00Z').getTime());

    expect(viewModel.title).toBe('Pix gerado com sucesso');
    expect(viewModel.statusLabel).toBe('Aguardando pagamento');
    expect(viewModel.qrCode).toBe('000201010212');
    expect(viewModel.pixExpiresLabel).toBeTruthy();
    expect(shouldPollPublicCheckout(makeResponse())).toBe(true);
  });

  it('stops polling once the payment is confirmed', () => {
    const response = makeResponse({
      checkout: {
        ...makeResponse().checkout,
        status: 'paid',
        payment: {
          ...makeResponse().checkout.payment,
          status: 'paid',
          gateway_status: 'paid',
        },
      },
    });

    const viewModel = buildCheckoutStatusViewModel(response);

    expect(viewModel.title).toBe('Pagamento confirmado');
    expect(viewModel.isTerminal).toBe(true);
    expect(shouldPollPublicCheckout(response)).toBe(false);
  });

  it('maps credit card failures into a semantic error state', () => {
    const response = makeResponse({
      checkout: {
        ...makeResponse().checkout,
        status: 'failed',
        payment: {
          ...makeResponse().checkout.payment,
          method: 'credit_card',
          status: 'failed',
          gateway_status: 'failed',
          pix: null,
          credit_card: {
            installments: 1,
            acquirer_message: 'Transacao recusada',
            acquirer_return_code: '51',
            last_status: 'failed',
          },
        },
      },
    });

    const viewModel = buildCheckoutStatusViewModel(response);

    expect(viewModel.paymentMethod).toBe('credit_card');
    expect(viewModel.title).toBe('Pagamento nao confirmado');
    expect(viewModel.description).toContain('Transacao recusada');
  });

  it('prefers the semantic summary from the backend when it is available', () => {
    const response = makeResponse({
      checkout: {
        ...makeResponse().checkout,
        status: 'pending_payment',
        summary: {
          state: 'paid',
          tone: 'success',
          payment_status_title: 'Pagamento confirmado',
          order_status_label: 'Pedido confirmado',
          payment_status_label: 'Confirmado',
          payment_status_description: 'Seu pacote ja foi confirmado e o evento pode seguir para a ativacao.',
          next_action: 'open_event',
          expires_in_seconds: null,
          is_waiting_payment: false,
          can_retry: false,
        },
        payment: {
          ...makeResponse().checkout.payment,
          status: 'pending_payment',
          gateway_status: 'pending',
        },
      },
    });

    const viewModel = buildCheckoutStatusViewModel(response);

    expect(viewModel.state).toBe('paid');
    expect(viewModel.tone).toBe('success');
    expect(viewModel.title).toBe('Pagamento confirmado');
    expect(viewModel.statusLabel).toBe('Confirmado');
    expect(shouldPollPublicCheckout(response)).toBe(false);
  });
});

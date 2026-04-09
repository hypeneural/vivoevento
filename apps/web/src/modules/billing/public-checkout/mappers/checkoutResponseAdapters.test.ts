import { describe, expect, it } from 'vitest';

import type { PublicEventCheckoutResponse } from '@/lib/api-types';

import {
  buildCheckoutWizardSummaries,
  buildMobileCheckoutFooterSummary,
  resolveCheckoutSelectedPackage,
} from './checkoutResponseAdapters';
import type { CommercialPackageCopy } from './packageCommercialCopy';

function createPackage(overrides: Partial<CommercialPackageCopy> = {}): CommercialPackageCopy {
  return {
    id: 1,
    code: 'casamento-essencial',
    name: 'Casamento Essencial',
    subtitle: 'Pacote enxuto para evento unico.',
    idealFor: 'Eventos que querem uma contratacao simples e segura.',
    benefits: ['Telao ao vivo para os convidados'],
    recommended: true,
    priceLabel: 'R$ 199,00',
    raw: {
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
      feature_map: {},
      modules: {
        hub: true,
        wall: true,
        play: false,
      },
      limits: {
        retention_days: 90,
        max_photos: 400,
      },
    },
    ...overrides,
  };
}

function createCheckoutResponse(overrides: Partial<PublicEventCheckoutResponse> = {}): PublicEventCheckoutResponse {
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
      total_cents: 24900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
      summary: null,
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
        code: 'casamento-premium',
        name: 'Casamento Premium',
        description: 'Pacote mais completo para o evento.',
        target_audience: 'direct_customer',
      },
      items: [],
    },
    purchase: null,
    onboarding: null,
    ...overrides,
  };
}

describe('checkoutResponseAdapters', () => {
  it('prefers the commercial package selected in the form', () => {
    const selectedPackage = resolveCheckoutSelectedPackage({
      formSelectedPackage: createPackage(),
      checkoutResponse: createCheckoutResponse(),
    });

    expect(selectedPackage).toEqual({
      name: 'Casamento Essencial',
      priceLabel: 'R$ 199,00',
      subtitle: 'Pacote enxuto para evento unico.',
    });
  });

  it('falls back to the checkout response package when the form package is missing', () => {
    const selectedPackage = resolveCheckoutSelectedPackage({
      checkoutResponse: createCheckoutResponse(),
    });

    expect(selectedPackage).toEqual({
      name: 'Casamento Premium',
      priceLabel: 'R$\u00a0249,00',
      subtitle: 'Pacote mais completo para o evento.',
    });
  });

  it('builds semantic wizard summaries for the V2 steps', () => {
    const summaries = buildCheckoutWizardSummaries({
      selectedPackage: {
        name: 'Casamento Essencial',
        priceLabel: 'R$ 199,00',
        subtitle: 'Pacote enxuto para evento unico.',
      },
      responsibleName: 'Camila Rocha',
      eventTitle: 'Casamento Camila e Bruno',
      checkoutResponse: createCheckoutResponse(),
      statusViewModel: {
        statusLabel: 'Aguardando pagamento',
      },
    });

    expect(summaries).toEqual({
      package: 'Casamento Essencial selecionado.',
      details: 'Camila Rocha - Casamento Camila e Bruno',
      payment: 'Aguardando pagamento',
    });
  });

  it('builds a compact mobile footer summary for the current step', () => {
    const summary = buildMobileCheckoutFooterSummary({
      currentStep: 'payment',
      selectedPackage: {
        name: 'Casamento Essencial',
        priceLabel: 'R$ 199,00',
        subtitle: 'Pacote enxuto para evento unico.',
      },
      statusViewModel: {
        title: 'Pix gerado com sucesso',
        statusLabel: 'Aguardando pagamento',
      },
    });

    expect(summary).toEqual({
      title: 'Casamento Essencial • R$ 199,00',
      description: 'Pix rapido ou cartao com confirmacao automatica.',
    });
  });

  it('switches the mobile footer to the semantic status summary after submit', () => {
    const summary = buildMobileCheckoutFooterSummary({
      currentStep: 'status',
      selectedPackage: null,
      statusViewModel: {
        title: 'Pagamento confirmado',
        statusLabel: 'Confirmado',
      },
    });

    expect(summary).toEqual({
      title: 'Pagamento confirmado',
      description: 'Confirmado',
    });
  });
});

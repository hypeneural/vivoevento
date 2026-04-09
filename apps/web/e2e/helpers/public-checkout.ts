import { expect, type Page } from '@playwright/test';

export const PUBLIC_CHECKOUT_V2_PATH = '/checkout/evento';

export function createEventPackage() {
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

export function createPixPendingCheckoutResponse() {
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

export function createPixPaidCheckoutResponse() {
  const response = createPixPendingCheckoutResponse();

  return {
    ...response,
    checkout: {
      ...response.checkout,
      status: 'paid',
      confirmed_at: '2026-04-05T10:05:00Z',
      summary: {
        state: 'paid',
        tone: 'success',
        payment_status_title: 'Pagamento confirmado',
        order_status_label: 'Pedido pago',
        payment_status_label: 'Confirmado',
        payment_status_description: 'Seu pagamento foi confirmado e o pacote ja pode ser ativado.',
        next_action: 'open_event',
        expires_in_seconds: null,
        is_waiting_payment: false,
        can_retry: false,
      },
      payment: {
        ...response.checkout.payment,
        gateway_status: 'paid',
        status: 'paid',
      },
    },
  };
}

export function createCardProcessingCheckoutResponse() {
  return {
    message: 'Checkout iniciado com sucesso.',
    token: 'token_public_checkout',
    user: null,
    organization: null,
    event: null,
    commercial_status: null,
    checkout: {
      id: 12,
      uuid: 'card-checkout-uuid',
      mode: 'event_package',
      status: 'pending_payment',
      currency: 'BRL',
      total_cents: 19900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
      summary: {
        state: 'processing',
        tone: 'warning',
        payment_status_title: 'Pagamento em analise',
        order_status_label: 'Pedido criado',
        payment_status_label: 'Em analise',
        payment_status_description: 'Seu cartao foi enviado com seguranca e agora estamos aguardando a confirmacao.',
        next_action: 'wait_payment_confirmation',
        expires_in_seconds: null,
        is_waiting_payment: true,
        can_retry: false,
      },
      payment: {
        provider: 'pagarme',
        method: 'credit_card',
        gateway_order_id: 'or_card_123',
        gateway_charge_id: 'ch_card_123',
        gateway_transaction_id: 'tr_card_123',
        gateway_status: 'processing',
        status: 'pending_payment',
        checkout_url: null,
        confirm_url: null,
        expires_at: null,
        pix: null,
        credit_card: {
          installments: 1,
          acquirer_message: 'Pagamento em analise.',
          acquirer_return_code: '00',
          last_status: 'processing',
        },
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
      description: 'Aguardando a confirmacao do pagamento.',
      next_path: '/events/11',
    },
  };
}

export function createMeResponse() {
  return {
    user: {
      id: 99,
      name: 'Camila Rocha',
      email: 'camila@example.com',
      phone: '5548999771111',
      avatar_url: null,
      status: 'active',
      role: {
        key: 'partner-owner',
        name: 'Partner Owner',
      },
      permissions: ['billing.view'],
      preferences: {
        theme: 'light',
        timezone: 'America/Sao_Paulo',
        locale: 'pt-BR',
        email_notifications: true,
        push_notifications: false,
        compact_mode: false,
      },
      last_login_at: '2026-04-05T10:00:00Z',
    },
    organization: {
      id: 44,
      uuid: 'org-direct-customer',
      type: 'direct_customer',
      name: 'Camila Rocha',
      slug: 'camila-rocha',
      status: 'active',
      logo_url: null,
      branding: {
        primary_color: null,
        secondary_color: null,
        subdomain: null,
        custom_domain: null,
      },
    },
    access: {
      accessible_modules: ['billing'],
      modules: [
        {
          key: 'billing',
          enabled: true,
          visible: true,
        },
      ],
      feature_flags: {
        billing: true,
      },
      entitlements: {
        version: 1,
        organization_type: 'direct_customer',
        modules: {
          live_gallery: true,
          wall: true,
          play: false,
          hub: true,
          whatsapp_ingestion: false,
          analytics_advanced: false,
        },
        limits: {
          max_active_events: 1,
          retention_days: 90,
        },
        branding: {
          white_label: false,
          custom_domain: false,
        },
        source_summary: [],
      },
    },
    subscription: null,
  };
}

export async function mockCommonPublicCheckoutRoutes(page: Page) {
  await page.route('**/api/v1/public/event-packages*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([createEventPackage()]),
    });
  });

  await page.route('**/api/v1/public/checkout-identity/check', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        identity_status: 'new_account',
        title: 'Tudo certo para continuar',
        description: 'Voce pode seguir normalmente para o pagamento.',
        action_label: null,
        login_url: null,
        cooldown_seconds: null,
      }),
    });
  });
}

export async function mockPagarmeCardToken(page: Page) {
  await page.route('https://api.pagar.me/core/v5/tokens?*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        id: 'tok_test_123',
        type: 'card',
      }),
    });
  });
}

export async function mockAuthLogin(page: Page) {
  await page.route('**/api/v1/auth/login', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        token: 'token-auth-login',
      }),
    });
  });

  await page.route('**/api/v1/auth/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(createMeResponse()),
    });
  });
}

export async function fillBuyerDetails(page: Page) {
  await page.getByLabel(/seu nome/i).fill('Camila Rocha');
  await page.getByLabel(/^WhatsApp$/i).fill('(48) 99977-1111');
  await page.getByLabel(/nome do evento/i).fill('Casamento Camila e Bruno');
}

export async function goToPaymentStep(page: Page) {
  await page.goto(PUBLIC_CHECKOUT_V2_PATH, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: /escolher este pacote/i }).click();
  await fillBuyerDetails(page);
  await page.getByRole('button', { name: /continuar para pagamento/i }).click();
  await expect(page.getByRole('button', { name: /gerar meu pix/i })).toBeVisible();
}

import { afterEach, describe, expect, it, vi } from 'vitest';
import type { PublicEventCheckoutPayload } from '@/lib/api-types';
import { publicEventCheckoutService } from './public-event-checkout.service';

describe('publicEventCheckoutService', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('creates the public checkout through the local backend endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          checkout: {
            id: 1,
            uuid: 'checkout-uuid',
            mode: 'event_package',
            status: 'pending_payment',
          },
        },
      }), {
        status: 201,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    const payload: PublicEventCheckoutPayload = {
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      package_id: 1,
      event: {
        title: 'Casamento Camila & Bruno',
        event_type: 'wedding',
      },
      payer: {
        name: 'Camila Rocha',
        email: 'camila@example.com',
        document: '12345678909',
        document_type: 'CPF',
        phone: '(48) 99977-1111',
        address: {
          street: 'Rua Exemplo',
          number: '123',
          district: 'Centro',
          zip_code: '88000000',
          city: 'Florianopolis',
          state: 'SC',
          country: 'BR',
        },
      },
      payment: {
        method: 'credit_card',
        credit_card: {
          installments: 1,
          statement_descriptor: 'EVENTOVIVO',
          card_token: 'token_test_123',
          billing_address: {
            street: 'Rua Exemplo',
            number: '123',
            district: 'Centro',
            zip_code: '88000000',
            city: 'Florianopolis',
            state: 'SC',
            country: 'BR',
          },
        },
      },
    };

    await publicEventCheckoutService.create(payload);

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/public/event-checkouts'),
      expect.objectContaining({
        method: 'POST',
      }),
    );

    const [, request] = fetchSpy.mock.calls[0] ?? [];
    expect(JSON.parse(String(request?.body))).toEqual(payload);
  });

  it('reads the checkout status only from the local backend endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          checkout: {
            id: 1,
            uuid: 'checkout-uuid',
            mode: 'event_package',
            status: 'paid',
          },
        },
      }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    await publicEventCheckoutService.get('checkout-uuid');

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/public/event-checkouts/checkout-uuid'),
      expect.objectContaining({
        method: 'GET',
      }),
    );
  });
});

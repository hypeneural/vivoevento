import { describe, expect, it } from 'vitest';

import type { CheckoutV2FormValues } from './checkoutFormSchema';
import { buildCheckoutPayload } from './checkoutFormUtils';

function makeBaseValues(): CheckoutV2FormValues {
  return {
    package_id: '7',
    responsible_name: 'Camila Rocha',
    whatsapp: '(48) 99977-1111',
    email: 'camila@example.com',
    event_title: 'Casamento Camila & Bruno',
    event_type: 'wedding',
    organization_name: 'Camila e Bruno',
    event_date: '2026-12-20',
    event_city: 'Florianopolis',
    event_description: 'Evento teste',
    payment_method: 'credit_card',
    payer_document: '123.456.789-09',
    payer_phone: '(48) 99977-1111',
    address_street: 'Rua Exemplo',
    address_number: '123',
    address_district: 'Centro',
    address_complement: 'Apto 12',
    address_zip_code: '88000-000',
    address_city: 'Florianopolis',
    address_state: 'sc',
    card_number: '4111 1111 1111 1111',
    card_holder_name: 'Camila Rocha',
    card_exp_month: '12',
    card_exp_year: '30',
    card_cvv: '123',
  };
}

describe('checkoutFormUtils', () => {
  it('builds credit card checkout payload with installments fixed at 1 and card token only at submit time', () => {
    const payloadWithoutToken = buildCheckoutPayload(makeBaseValues());
    const payloadWithToken = buildCheckoutPayload(makeBaseValues(), 'tok_card_123');

    expect(payloadWithoutToken.payment.method).toBe('credit_card');
    expect(payloadWithoutToken.payment.credit_card?.installments).toBe(1);
    expect(payloadWithoutToken.payment.credit_card?.card_token).toBeNull();
    expect(payloadWithoutToken.payer?.document).toBe('12345678909');
    expect(payloadWithoutToken.payer?.phone).toBe('48999771111');
    expect(payloadWithoutToken.payer?.address.zip_code).toBe('88000000');
    expect(payloadWithoutToken.payer?.address.state).toBe('SC');

    expect(payloadWithToken.payment.credit_card?.card_token).toBe('tok_card_123');
  });

  it('builds pix checkout payload without billing address or credit card payload', () => {
    const payload = buildCheckoutPayload({
      ...makeBaseValues(),
      payment_method: 'pix',
    });

    expect(payload.payment).toEqual({
      method: 'pix',
    });
    expect(payload.payer).toBeUndefined();
  });
});

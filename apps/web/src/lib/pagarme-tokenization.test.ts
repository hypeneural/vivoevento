import { afterEach, describe, expect, it, vi } from 'vitest';
import { createPagarmeCardToken, PagarmeTokenizationError } from './pagarme-tokenization';

describe('pagarme-tokenization', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('posts card data directly to the official tokens endpoint with appId and no authorization header', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        id: 'token_test_123',
        type: 'card',
        expires_at: '2026-04-04T20:00:00Z',
      }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    const token = await createPagarmeCardToken({
      number: '4000000000000010',
      holderName: 'CAMILA ROCHA',
      expMonth: '12',
      expYear: '30',
      cvv: '123',
    }, {
      publicKey: 'pk_test_jGWvy7PhpBukl396',
    });

    expect(token.id).toBe('token_test_123');
    expect(fetchSpy).toHaveBeenCalledTimes(1);
    expect(fetchSpy).toHaveBeenCalledWith(
      'https://api.pagar.me/core/v5/tokens?appId=pk_test_jGWvy7PhpBukl396',
      expect.objectContaining({
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }),
    );

    const [, request] = fetchSpy.mock.calls[0] ?? [];
    const body = JSON.parse(String(request?.body));

    expect((request?.headers as Record<string, string>).Authorization).toBeUndefined();
    expect(body).toEqual({
      type: 'card',
      card: {
        number: '4000000000000010',
        holder_name: 'CAMILA ROCHA',
        exp_month: 12,
        exp_year: 30,
        cvv: '123',
      },
    });
  });

  it('fails fast when the public key is missing', async () => {
    await expect(createPagarmeCardToken({
      number: '4000000000000010',
      holderName: 'CAMILA ROCHA',
      expMonth: 12,
      expYear: 30,
      cvv: '123',
    }, {
      publicKey: '',
    })).rejects.toThrow('VITE_PAGARME_PUBLIC_KEY is required');
  });

  it('surfaces provider errors from the tokenization endpoint', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        message: 'Invalid card data.',
      }), {
        status: 400,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    await expect(createPagarmeCardToken({
      number: '4000000000000028',
      holderName: 'CAMILA ROCHA',
      expMonth: 12,
      expYear: 30,
      cvv: '612',
    }, {
      publicKey: 'pk_test_jGWvy7PhpBukl396',
    })).rejects.toMatchObject<PagarmeTokenizationError>({
      name: 'PagarmeTokenizationError',
      message: 'Invalid card data.',
      status: 400,
    });
  });
});

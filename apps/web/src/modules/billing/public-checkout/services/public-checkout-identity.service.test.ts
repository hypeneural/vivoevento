import { afterEach, describe, expect, it, vi } from 'vitest';

import { publicCheckoutIdentityService } from './public-checkout-identity.service';

describe('publicCheckoutIdentityService', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('posts the identity pre-check payload to the public billing endpoint and forwards AbortSignal', async () => {
    const controller = new AbortController();
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: {
          identity_status: 'login_suggested',
          title: 'Ja encontramos seu cadastro',
          description: 'Entrar agora costuma ser mais rapido para continuar sua compra.',
          action_label: 'Entrar para continuar',
          login_url: '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth',
          cooldown_seconds: null,
        },
      }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    await publicCheckoutIdentityService.check({
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
    }, controller.signal);

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/public/checkout-identity/check'),
      expect.objectContaining({
        method: 'POST',
        signal: controller.signal,
        body: JSON.stringify({
          whatsapp: '(48) 99977-1111',
          email: 'camila@example.com',
        }),
      }),
    );
  });
});

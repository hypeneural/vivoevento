import { afterEach, describe, expect, it, vi } from 'vitest';
import { publicEventPackagesService } from './public-event-packages.service';

describe('publicEventPackagesService', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads the public event package catalog from the local backend endpoint', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({
        data: [
          {
            id: 1,
            code: 'casamento-essencial',
            name: 'Casamento Essencial',
            description: 'Pacote base para evento unico.',
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
        ],
      }), {
        status: 200,
        headers: {
          'content-type': 'application/json',
        },
      }),
    );

    const packages = await publicEventPackagesService.list();

    expect(fetchSpy).toHaveBeenCalledWith(
      expect.stringContaining('/public/event-packages'),
      expect.objectContaining({
        method: 'GET',
      }),
    );
    expect(packages).toHaveLength(1);
    expect(packages[0]?.code).toBe('casamento-essencial');
    expect(packages[0]?.default_price?.amount_cents).toBe(19900);
  });
});

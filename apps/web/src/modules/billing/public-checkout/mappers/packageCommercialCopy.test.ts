import { describe, expect, it } from 'vitest';

import type { ApiEventPackage } from '@/lib/api-types';

import { findCommercialPackageBySelectionKey, mapPackageToCommercialCard } from './packageCommercialCopy';

function makePackage(overrides: Partial<ApiEventPackage> = {}): ApiEventPackage {
  return {
    id: 1,
    code: 'interactive-event',
    name: 'Interativo',
    description: 'Descricao tecnica.',
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
    checkout_marketing: null,
    modules: {
      hub: true,
      wall: true,
      play: false,
    },
    limits: {
      retention_days: 90,
      max_photos: 400,
    },
    ...overrides,
  };
}

describe('packageCommercialCopy', () => {
  it('prefers native checkout marketing metadata from the catalog', () => {
    const copy = mapPackageToCommercialCard(makePackage({
      checkout_marketing: {
        slug: 'casamento-interativo',
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
    }));

    expect(copy.subtitle).toBe('O pacote mais equilibrado para eventos sociais com compra rapida.');
    expect(copy.idealFor).toBe('Casamentos e aniversarios com telao ao vivo.');
    expect(copy.benefits).toEqual([
      'Telao ao vivo para os convidados',
      'Pagina do evento pronta para compartilhar',
      'Pix e cartao com confirmacao automatica',
    ]);
    expect(copy.badgeLabel).toBe('Mais escolhido');
    expect(copy.recommended).toBe(true);
    expect(copy.deepLinkKey).toBe('casamento-interativo');
  });

  it('falls back to derived copy when the catalog has no marketing overrides', () => {
    const copy = mapPackageToCommercialCard(makePackage({
      modules: {
        hub: true,
        wall: false,
        play: true,
      },
      limits: {
        retention_days: 180,
        max_photos: 800,
      },
    }), 2);

    expect(copy.badgeLabel).toBeNull();
    expect(copy.recommended).toBe(false);
    expect(copy.deepLinkKey).toBe('interactive-event');
    expect(copy.idealFor).toBe('Eventos que querem experiencia mais interativa para os convidados.');
    expect(copy.benefits).toContain('Experiencias interativas para engajar o publico');
  });

  it('finds packages by deep link slug, code or id', () => {
    const packages = [
      mapPackageToCommercialCard(makePackage({
        checkout_marketing: {
          slug: 'casamento-interativo',
          subtitle: '...',
          ideal_for: '...',
          benefits: ['Beneficio 1'],
          badge: 'Mais escolhido',
          recommended: true,
        },
      })),
    ];

    expect(findCommercialPackageBySelectionKey(packages, 'casamento-interativo')?.id).toBe(1);
    expect(findCommercialPackageBySelectionKey(packages, 'interactive-event')?.id).toBe(1);
    expect(findCommercialPackageBySelectionKey(packages, '1')?.id).toBe(1);
    expect(findCommercialPackageBySelectionKey(packages, 'desconhecido')).toBeNull();
  });
});

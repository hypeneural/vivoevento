import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { normalizeEventPublicLinkQrConfig } from '@/modules/qr-code/support/qrSchemaNormalizer';

import { PublicLinkCard } from './PublicLinkCard';

const createQrCodeStylingDriverMock = vi.fn();

vi.mock('@/modules/qr-code/support/qrCodeStylingDriver', () => ({
  createQrCodeStylingDriver: (...args: unknown[]) => createQrCodeStylingDriverMock(...args),
}));

function makeLink(overrides: Partial<ApiEventPublicLink> = {}): ApiEventPublicLink {
  return {
    key: 'gallery',
    label: 'Galeria',
    enabled: true,
    identifier_type: 'slug',
    identifier: 'casamento-ana-pedro',
    url: 'https://example.com/gallery/casamento-ana-pedro',
    api_url: null,
    qr_value: 'https://example.com/gallery/casamento-ana-pedro',
    ...overrides,
  };
}

function makeBranding(overrides: Partial<ApiEventEffectiveBranding> = {}): ApiEventEffectiveBranding {
  return {
    logo_path: null,
    logo_url: null,
    cover_image_path: null,
    cover_image_url: null,
    primary_color: '#112233',
    secondary_color: '#445566',
    source: 'organization',
    inherits_from_organization: true,
    ...overrides,
  };
}

describe('PublicLinkCard', () => {
  it('renders a styled mini preview when qr state exists', () => {
    createQrCodeStylingDriverMock.mockReturnValue({
      append: vi.fn(),
      update: vi.fn(),
    });

    render(
      <PublicLinkCard
        eventId="42"
        effectiveBranding={makeBranding()}
        link={makeLink()}
        qrState={{
          eventId: '42',
          linkKey: 'gallery',
          link: makeLink(),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'gallery' }),
          configSource: 'saved',
          hasSavedConfig: true,
          updatedAt: '2026-04-11T03:00:00.000Z',
          assets: {
            svgPath: null,
            pngPath: null,
          },
        }}
        onCopy={vi.fn()}
      />,
    );

    expect(screen.getByTestId('qr-code-mini-preview')).toBeInTheDocument();
    expect(screen.getAllByText('Estilo salvo').length).toBeGreaterThan(0);
  });
});

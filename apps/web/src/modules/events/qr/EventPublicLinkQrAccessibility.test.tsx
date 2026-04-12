import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { normalizeEventPublicLinkQrConfig } from '@/modules/qr-code/support/qrSchemaNormalizer';
import { EventPublicLinkQrEditorShell } from './EventPublicLinkQrEditorShell';

const useIsMobileMock = vi.fn();
const createQrCodeStylingDriverMock = vi.fn();

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

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
    logo_url: 'https://cdn.example.com/logo.png',
    cover_image_path: null,
    cover_image_url: null,
    primary_color: '#112233',
    secondary_color: '#445566',
    source: 'organization',
    inherits_from_organization: true,
    ...overrides,
  };
}

describe('EventPublicLinkQrAccessibility', () => {
  beforeEach(() => {
    useIsMobileMock.mockReturnValue(false);
    createQrCodeStylingDriverMock.mockReturnValue({
      append: vi.fn(),
      update: vi.fn(),
    });
  });

  it('exposes the dialog accessibly and closes on Escape', () => {
    const onOpenChange = vi.fn();

    render(
      <div>
        <button type="button">Trigger anterior</button>
        <button type="button">Trigger seguinte</button>
        <div id="root">
          <EventPublicLinkQrEditorShell
            open
            onOpenChange={onOpenChange}
            state={{
              eventId: '42',
              linkKey: 'gallery',
              link: makeLink(),
              effectiveBranding: makeBranding(),
              config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'gallery' }),
              configSource: 'default',
              hasSavedConfig: false,
              updatedAt: null,
              assets: {
                svgPath: null,
                pngPath: null,
              },
            }}
            onSave={vi.fn()}
            onResetToDefault={vi.fn()}
          />
        </div>
      </div>,
    );

    const dialog = screen.getByRole('dialog', { name: /editar qr code/i });

    expect(dialog).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /fechar/i })).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: /baixar qr/i }).length).toBeGreaterThan(0);

    fireEvent.keyDown(dialog, { key: 'Escape' });

    expect(onOpenChange).toHaveBeenCalledWith(false);
  }, 10000);
});

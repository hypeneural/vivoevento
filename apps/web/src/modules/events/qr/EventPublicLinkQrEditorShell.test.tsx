import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { normalizeEventPublicLinkQrConfig } from '@/modules/qr-code/support/qrSchemaNormalizer';

import { EventPublicLinkQrEditorShell } from './EventPublicLinkQrEditorShell';

const useIsMobileMock = vi.fn();

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

function makeLink(overrides: Partial<ApiEventPublicLink> = {}): ApiEventPublicLink {
  return {
    key: 'upload',
    label: 'Upload',
    enabled: true,
    identifier_type: 'upload_slug',
    identifier: 'envio-casamento-ana-pedro',
    url: 'https://example.com/upload/envio-casamento-ana-pedro',
    api_url: null,
    qr_value: 'https://example.com/upload/envio-casamento-ana-pedro',
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

describe('EventPublicLinkQrEditorShell', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uses Dialog on desktop', async () => {
    useIsMobileMock.mockReturnValue(false);

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          link: makeLink(),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'upload' }),
        }}
      />,
    );

    expect(await screen.findByTestId('event-public-link-qr-editor-dialog')).toBeInTheDocument();
    expect(screen.getByText(/Editar QR Code/i)).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /Conteudo/i })).toBeInTheDocument();
    expect(screen.getByText(/Salvar em breve/i)).toBeInTheDocument();
  });

  it('uses Drawer on mobile', async () => {
    useIsMobileMock.mockReturnValue(true);

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          link: makeLink({ key: 'gallery', label: 'Galeria', identifier_type: 'slug' }),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'gallery' }),
        }}
      />,
    );

    expect(await screen.findByTestId('event-public-link-qr-editor-drawer')).toBeInTheDocument();
    expect(screen.getByText(/Editar QR Code/i)).toBeInTheDocument();
  });
});

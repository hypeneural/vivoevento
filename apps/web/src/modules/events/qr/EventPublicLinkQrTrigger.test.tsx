import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';

const warmEventPublicLinkQrEditorMock = vi.fn();
let resolveLazyModule: ((value: { default: React.ComponentType<any> }) => void) | null = null;

vi.mock('./preload', () => ({
  warmEventPublicLinkQrEditor: (...args: unknown[]) => warmEventPublicLinkQrEditorMock(...args),
}));

vi.mock('./loader', () => ({
  loadEventPublicLinkQrEditorModule: () => new Promise((resolve) => {
    resolveLazyModule = resolve as (value: { default: React.ComponentType<any> }) => void;
  }),
}));

vi.mock('@/modules/events/qr/QrCodeMiniPreview', () => ({
  QrCodeMiniPreview: () => (
    <div data-testid="qr-code-mini-preview">
      <svg data-testid="qr-code-mini-preview-svg" viewBox="0 0 10 10">
        <rect width="10" height="10" />
      </svg>
    </div>
  ),
}));

import { EventPublicLinkQrTrigger } from './EventPublicLinkQrTrigger';

function MockLazyEditor({
  open,
  onOpenChange,
  link,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  link: ApiEventPublicLink;
}) {
  if (!open) {
    return null;
  }

  return (
    <div data-testid="mock-event-public-link-qr-editor">
      <p>{`Editor de ${link.label}`}</p>
      <button type="button" onClick={() => onOpenChange(false)}>
        Fechar editor
      </button>
    </div>
  );
}

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

describe('EventPublicLinkQrTrigger', () => {
  beforeEach(() => {
    warmEventPublicLinkQrEditorMock.mockReset();
    warmEventPublicLinkQrEditorMock.mockResolvedValue(undefined);
    resolveLazyModule = null;
    document.body.innerHTML = '';
  });

  it('renders the QR as an accessible button and warms the editor on hover/focus', () => {
    render(
      <EventPublicLinkQrTrigger
        eventId="42"
        link={makeLink()}
        effectiveBranding={makeBranding()}
      />,
    );

    const button = screen.getByRole('button', { name: /editar qr code de galeria/i });

    expect(button).toBeInTheDocument();

    fireEvent.mouseEnter(button);
    fireEvent.focus(button);

    expect(warmEventPublicLinkQrEditorMock).toHaveBeenCalledTimes(2);
    expect(warmEventPublicLinkQrEditorMock).toHaveBeenCalledWith(expect.objectContaining({
      eventId: '42',
      link: expect.objectContaining({ key: 'gallery' }),
    }));
  });

  it('does not rely on the shared Button svg sizing rules when rendering a live mini preview', () => {
    render(
      <EventPublicLinkQrTrigger
        eventId="42"
        link={makeLink()}
        effectiveBranding={makeBranding()}
        previewOptions={{
          width: 100,
          height: 100,
          type: 'svg',
          data: 'https://example.com/gallery/casamento-ana-pedro',
        }}
      />,
    );

    const button = screen.getByRole('button', { name: /editar qr code de galeria/i });

    expect(screen.getByTestId('qr-code-mini-preview-svg')).toBeInTheDocument();
    expect(button.className).not.toContain('[_svg]:size-4');
  });

  it('shows suspense fallback while the lazy editor chunk loads and restores focus after close', async () => {
    render(
      <EventPublicLinkQrTrigger
        eventId="42"
        link={makeLink()}
        effectiveBranding={makeBranding()}
      />,
    );

    const button = screen.getByRole('button', { name: /editar qr code de galeria/i });
    button.focus();

    fireEvent.click(button);

    expect(await screen.findByTestId('event-public-link-qr-editor-fallback')).toBeInTheDocument();

    resolveLazyModule?.({ default: MockLazyEditor });

    expect(await screen.findByTestId('mock-event-public-link-qr-editor')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /fechar editor/i }));

    await waitFor(() => {
      expect(button).toHaveFocus();
    });
  });
});

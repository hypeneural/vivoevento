import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

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

vi.mock('@/modules/events/qr/QrCodeMiniPreview', () => ({
  QrCodeMiniPreview: () => <div data-testid="qr-code-mini-preview-mock" />,
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
    createQrCodeStylingDriverMock.mockReturnValue({
      append: vi.fn(),
      update: vi.fn(),
    });
  });

  it('uses Dialog on desktop and mounts the live preview once', async () => {
    useIsMobileMock.mockReturnValue(false);

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          linkKey: 'upload',
          link: makeLink(),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'upload' }),
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
      />,
    );

    expect(await screen.findByTestId('event-public-link-qr-editor-dialog')).toBeInTheDocument();
    expect(screen.getByText(/Editar QR Code/i)).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /Conteudo/i })).toBeInTheDocument();
    expect(screen.getByTestId('qr-code-preview-pane')).toBeInTheDocument();
    expect(createQrCodeStylingDriverMock).toHaveBeenCalledTimes(1);
  });

  it('uses Drawer on mobile', async () => {
    useIsMobileMock.mockReturnValue(true);

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          linkKey: 'gallery',
          link: makeLink({ key: 'gallery', label: 'Galeria', identifier_type: 'slug' }),
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
      />,
    );

    expect(await screen.findByTestId('event-public-link-qr-editor-drawer')).toBeInTheDocument();
    expect(screen.getByText(/Editar QR Code/i)).toBeInTheDocument();
  });

  it('updates the live preview from watched visual fields and ignores export-only draft changes', async () => {
    useIsMobileMock.mockReturnValue(false);

    const update = vi.fn();
    createQrCodeStylingDriverMock.mockReturnValue({
      append: vi.fn(),
      update,
    });

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          linkKey: 'upload',
          link: makeLink(),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'upload' }),
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
      />,
    );

    expect(await screen.findByTestId('event-public-link-qr-editor-dialog')).toBeInTheDocument();
    const initialUpdateCalls = update.mock.calls.length;

    fireEvent.change(screen.getByLabelText(/Cor principal/i), {
      target: { value: '#224466' },
    });

    expect(update).toHaveBeenCalledTimes(initialUpdateCalls + 1);

    fireEvent.change(screen.getByLabelText(/Tamanho de exportacao/i), {
      target: { value: '2048' },
    });

    expect(update).toHaveBeenCalledTimes(initialUpdateCalls + 1);
  });

  it('submits the current semantic config and resets through the backend callback when an override exists', async () => {
    useIsMobileMock.mockReturnValue(false);

    const onSave = vi.fn().mockResolvedValue(undefined);
    const onResetToDefault = vi.fn().mockResolvedValue(undefined);

    render(
      <EventPublicLinkQrEditorShell
        open
        onOpenChange={vi.fn()}
        state={{
          eventId: '42',
          linkKey: 'upload',
          link: makeLink(),
          effectiveBranding: makeBranding(),
          config: normalizeEventPublicLinkQrConfig({}, { linkKey: 'upload' }),
          configSource: 'saved',
          hasSavedConfig: true,
          updatedAt: '2026-04-11T03:00:00.000Z',
          assets: {
            svgPath: null,
            pngPath: null,
          },
        }}
        onSave={onSave}
        onResetToDefault={onResetToDefault}
      />,
    );

    fireEvent.change(screen.getByLabelText(/Cor principal/i), {
      target: { value: '#224466' },
    });

    const saveButton = screen.getByRole('button', { name: /^Salvar$/i });

    await waitFor(() => {
      expect(saveButton).not.toBeDisabled();
    });

    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledTimes(1);
    });

    expect(onSave).toHaveBeenCalledWith(expect.objectContaining({
      style: expect.objectContaining({
        dots: expect.objectContaining({
          color: '#224466',
        }),
      }),
    }));

    fireEvent.click(screen.getByRole('button', { name: /Restaurar padrao/i }));

    expect(onResetToDefault).toHaveBeenCalledTimes(1);
  });
});

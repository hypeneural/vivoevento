import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { QRCodeStylingOptions } from '../support/qrCodeStylingDriver';
import { QrCodePreviewPane } from './QrCodePreviewPane';

const createQrCodeStylingDriverMock = vi.fn();

vi.mock('../support/qrCodeStylingDriver', () => ({
  createQrCodeStylingDriver: (...args: unknown[]) => createQrCodeStylingDriverMock(...args),
}));

describe('QrCodePreviewPane', () => {
  it('mounts the live preview and never triggers export on simple render/update flow', () => {
    const append = vi.fn();
    const update = vi.fn();
    const download = vi.fn();

    createQrCodeStylingDriverMock.mockReturnValue({
      append,
      update,
      download,
    });

    const options: QRCodeStylingOptions = {
      type: 'svg',
      width: 320,
      height: 320,
      data: 'https://eventovivo.com/wall',
      dotsOptions: {
        color: '#0f172a',
      },
    };

    const { rerender } = render(<QrCodePreviewPane options={options} />);

    expect(screen.getByTestId('qr-code-preview-pane')).toBeInTheDocument();
    expect(append).toHaveBeenCalledTimes(1);
    expect(download).not.toHaveBeenCalled();

    rerender(
      <QrCodePreviewPane
        options={{
          ...options,
          dotsOptions: {
            color: '#112233',
          },
        }}
      />,
    );

    expect(update).toHaveBeenCalledTimes(1);
    expect(download).not.toHaveBeenCalled();
  });

  it('shows the unavailable state when preview cannot be mounted', () => {
    render(
      <QrCodePreviewPane
        unavailable
        unavailableLabel="QR ainda indisponivel"
        options={{
          type: 'svg',
          width: 320,
          height: 320,
          data: '',
        }}
      />,
    );

    expect(screen.getByTestId('qr-code-preview-pane-unavailable')).toHaveTextContent(
      /qr ainda indisponivel/i,
    );
  });
});

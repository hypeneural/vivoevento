import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

import type { QRCodeStylingOptions } from '../support/qrCodeStylingDriver';
import { useQrCodePreview } from './useQrCodePreview';

const createQrCodeStylingDriverMock = vi.fn();

vi.mock('../support/qrCodeStylingDriver', () => ({
  createQrCodeStylingDriver: (...args: unknown[]) => createQrCodeStylingDriverMock(...args),
}));

function HookHarness({
  options,
  children,
}: {
  options: QRCodeStylingOptions;
  children?: ReactNode;
}) {
  const { containerRef } = useQrCodePreview({ options });

  return (
    <div>
      <div ref={containerRef} data-testid="preview-container" />
      {children}
    </div>
  );
}

describe('useQrCodePreview', () => {
  it('creates the qr-code-styling instance once, appends once and only updates on option changes', () => {
    const append = vi.fn();
    const update = vi.fn();

    createQrCodeStylingDriverMock.mockReturnValue({
      append,
      update,
    });

    const initialOptions: QRCodeStylingOptions = {
      type: 'svg',
      width: 320,
      height: 320,
      data: 'https://eventovivo.com/upload',
    };

    const { rerender } = render(
      <HookHarness options={initialOptions} />,
    );

    expect(createQrCodeStylingDriverMock).toHaveBeenCalledTimes(1);
    expect(createQrCodeStylingDriverMock).toHaveBeenCalledWith(initialOptions);
    expect(append).toHaveBeenCalledTimes(1);
    expect(update).not.toHaveBeenCalled();

    rerender(<HookHarness options={initialOptions} />);

    expect(createQrCodeStylingDriverMock).toHaveBeenCalledTimes(1);
    expect(append).toHaveBeenCalledTimes(1);
    expect(update).not.toHaveBeenCalled();

    const nextOptions: QRCodeStylingOptions = {
      ...initialOptions,
      dotsOptions: {
        color: '#112233',
      },
    };

    rerender(<HookHarness options={nextOptions} />);

    expect(createQrCodeStylingDriverMock).toHaveBeenCalledTimes(1);
    expect(append).toHaveBeenCalledTimes(1);
    expect(update).toHaveBeenCalledTimes(1);
    expect(update).toHaveBeenCalledWith(nextOptions);
  });
});

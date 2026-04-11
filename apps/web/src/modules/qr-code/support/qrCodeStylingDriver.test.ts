import { afterEach, describe, expect, it } from 'vitest';
import { waitFor } from '@testing-library/react';

import { createQrCodeStylingDriver, QR_CODE_STYLING_VERSION } from './qrCodeStylingDriver';

describe('qrCodeStylingDriver', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('pins the dependency version used by the local wrapper', () => {
    expect(QR_CODE_STYLING_VERSION).toBe('1.9.2');
  });

  it('can append, update and export svg data with the real qr-code-styling runtime', async () => {
    const container = document.createElement('div');
    document.body.appendChild(container);

    const driver = createQrCodeStylingDriver({
      width: 128,
      height: 128,
      type: 'svg',
      data: 'https://eventovivo.com/qr/editor',
      dotsOptions: {
        color: '#0f172a',
        type: 'rounded',
      },
      backgroundOptions: {
        color: '#ffffff',
      },
    });

    driver.append(container);

    await waitFor(() => {
      expect(container.querySelector('svg')).not.toBeNull();
    });

    driver.update({
      data: 'https://eventovivo.com/qr/editor-atualizado',
    });

    const raw = await driver.getRawData('svg');

    expect(raw).toBeInstanceOf(Blob);
    expect(container.innerHTML.length).toBeGreaterThan(1000);
  });
});

import { describe, expect, it, vi, beforeEach } from 'vitest';

const createQrCodeStylingDriverMock = vi.fn();

vi.mock('./qrCodeStylingDriver', () => ({
  createQrCodeStylingDriver: (...args: unknown[]) => createQrCodeStylingDriverMock(...args),
}));

import { normalizeEventPublicLinkQrConfig } from './qrSchemaNormalizer';
import { downloadEventPublicLinkQrCode } from './qrDownload';

describe('qrDownload', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    createQrCodeStylingDriverMock.mockReset();
  });

  it('generates a file download using the configured extension and pattern', async () => {
    createQrCodeStylingDriverMock.mockReturnValue({
      getRawData: vi.fn().mockResolvedValue(new Blob(['svg'], { type: 'image/svg+xml' })),
    });

    const appendSpy = vi.spyOn(document.body, 'append');
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn(),
    });
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn(() => 'blob:preview'),
    });
    const revokeSpy = vi.mocked(URL.revokeObjectURL);
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:preview');
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

    await downloadEventPublicLinkQrCode({
      config: normalizeEventPublicLinkQrConfig({
        export_defaults: {
          extension: 'svg',
          size: 1024,
          download_name_pattern: 'evento-{event_id}-{link_key}',
        },
      }),
      data: 'https://app.eventovivo.com/e/evento/gallery',
      eventId: '30',
      linkKey: 'gallery',
    });

    expect(createQrCodeStylingDriverMock).toHaveBeenCalledTimes(1);
    expect(appendSpy).toHaveBeenCalledTimes(1);
    expect(revokeSpy).toHaveBeenCalledWith('blob:preview');
  });
});

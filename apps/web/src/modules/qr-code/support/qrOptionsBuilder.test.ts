import { describe, expect, it } from 'vitest';

import { normalizeEventPublicLinkQrConfig } from './qrSchemaNormalizer';
import { buildQrCodeStylingOptions } from './qrOptionsBuilder';

describe('qrOptionsBuilder', () => {
  it('maps the semantic product schema into qr-code-styling options and takes data from the read model', () => {
    const config = normalizeEventPublicLinkQrConfig({
      skin_preset: 'premium',
      logo: {
        mode: 'custom',
        asset_url: 'https://cdn.example.com/branding/event-logo.png',
        image_size: 0.24,
        save_as_blob: true,
      },
      advanced: {
        error_correction_level: 'Q',
        round_size: false,
      },
    });

    const options = buildQrCodeStylingOptions({
      config,
      data: 'https://app.eventovivo.com/e/evento/upload',
    });

    expect(options).toMatchObject({
      type: 'svg',
      width: 320,
      height: 320,
      margin: 16,
      data: 'https://app.eventovivo.com/e/evento/upload',
      image: 'https://cdn.example.com/branding/event-logo.png',
      shape: 'square',
      dotsOptions: {
        roundSize: false,
      },
      imageOptions: {
        saveAsBlob: true,
        crossOrigin: 'anonymous',
      },
      qrOptions: {
        errorCorrectionLevel: 'H',
      },
    });
  });

  it('never derives qr data from the saved schema object', () => {
    const options = buildQrCodeStylingOptions({
      config: normalizeEventPublicLinkQrConfig({}),
      data: 'https://app.eventovivo.com/e/evento/gallery',
    });

    expect(options.data).toBe('https://app.eventovivo.com/e/evento/gallery');
  });
});

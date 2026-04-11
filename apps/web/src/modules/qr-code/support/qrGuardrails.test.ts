import { describe, expect, it } from 'vitest';

import { applyQrGuardrails, clampImageSize, clampMarginModules } from './qrGuardrails';
import { buildQrConfigDefaults } from './qrDefaults';

describe('qrGuardrails', () => {
  it('clamps margin modules to the minimum quiet zone and image size to the library hard limit', () => {
    expect(clampMarginModules(1)).toBe(4);
    expect(clampMarginModules(8)).toBe(8);
    expect(clampImageSize(0.9)).toBe(0.5);
    expect(clampImageSize(0.34)).toBe(0.34);
  });

  it('upgrades ECC to H when a logo is present and avoids jpeg defaults on transparent QR backgrounds', () => {
    const guarded = applyQrGuardrails({
      ...buildQrConfigDefaults(),
      logo: {
        ...buildQrConfigDefaults().logo,
        mode: 'custom',
        asset_url: 'https://cdn.example.com/logo.png',
        image_size: 0.42,
      },
      style: {
        ...buildQrConfigDefaults().style,
        background: {
          ...buildQrConfigDefaults().style.background,
          transparent: true,
        },
      },
      export_defaults: {
        ...buildQrConfigDefaults().export_defaults,
        extension: 'jpeg',
      },
      advanced: {
        ...buildQrConfigDefaults().advanced,
        error_correction_level: 'Q',
      },
    });

    expect(guarded.advanced.error_correction_level).toBe('H');
    expect(guarded.export_defaults.extension).toBe('png');
  });
});

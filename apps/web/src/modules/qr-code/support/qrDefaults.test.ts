import { describe, expect, it } from 'vitest';

import { buildQrConfigDefaults } from './qrDefaults';
import { getDefaultUsagePresetForLinkKey } from './qrPresets';
import { QR_CONFIG_VERSION } from './qrTypes';

describe('qrDefaults', () => {
  it('maps public link keys to scenario presets with product-safe defaults', () => {
    expect(getDefaultUsagePresetForLinkKey('wall')).toBe('telao');
    expect(getDefaultUsagePresetForLinkKey('upload')).toBe('upload_rapido');
    expect(getDefaultUsagePresetForLinkKey('gallery')).toBe('galeria_premium');
  });

  it('builds normalized defaults from the link key', () => {
    const config = buildQrConfigDefaults({
      linkKey: 'upload',
    });

    expect(config).toMatchObject({
      config_version: QR_CONFIG_VERSION,
      usage_preset: 'upload_rapido',
      skin_preset: 'classico',
      render: {
        preview_type: 'svg',
        preview_size: 320,
        margin_modules: 4,
      },
      advanced: {
        error_correction_level: 'Q',
        shape: 'square',
      },
      export_defaults: {
        extension: 'png',
        size: 1024,
      },
    });
  });
});

import { describe, expect, it } from 'vitest';

import { buildQrConfigDefaults } from './qrDefaults';
import { resetQrSection } from './qrSectionReset';

describe('qrSectionReset', () => {
  it('resets the style section without touching logo and export', () => {
    const defaults = buildQrConfigDefaults({ linkKey: 'upload' });
    const config = {
      ...defaults,
      style: {
        ...defaults.style,
        dots: {
          ...defaults.style.dots,
          color: '#ff0000',
        },
      },
      logo: {
        ...defaults.logo,
        mode: 'event_logo',
      },
      export_defaults: {
        ...defaults.export_defaults,
        size: 2048,
      },
    };

    const result = resetQrSection(config, defaults, 'style');

    expect(result.style).toEqual(defaults.style);
    expect(result.logo).toEqual(config.logo);
    expect(result.export_defaults).toEqual(config.export_defaults);
  });
});

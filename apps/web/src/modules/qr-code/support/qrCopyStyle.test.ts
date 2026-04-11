import { describe, expect, it } from 'vitest';

import { buildQrConfigDefaults } from './qrDefaults';
import { applyQrCopyStyle } from './qrCopyStyle';

describe('qrCopyStyle', () => {
  it('copies visual fields without touching the target config version', () => {
    const source = buildQrConfigDefaults({ linkKey: 'wall', usagePreset: 'telao', skinPreset: 'escuro' });
    const target = {
      ...buildQrConfigDefaults({ linkKey: 'upload', usagePreset: 'upload_rapido', skinPreset: 'classico' }),
      config_version: 'event-public-link-qr.v1',
    };

    const result = applyQrCopyStyle(source, target);

    expect(result.config_version).toBe(target.config_version);
    expect(result.usage_preset).toBe('telao');
    expect(result.skin_preset).toBe('escuro');
    expect(result.style).toEqual(source.style);
    expect(result.export_defaults).toEqual(source.export_defaults);
  });
});

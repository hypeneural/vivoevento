import { describe, expect, it } from 'vitest';

import { buildQrCascadeDefaults } from './qrPresetCascade';
import { resolveQrCascadeExplanation } from './qrCascadeExplanation';

describe('qrCascadeExplanation', () => {
  it('marks event branding values as coming from the event', () => {
    const config = buildQrCascadeDefaults({
      linkKey: 'upload',
      usagePreset: 'upload_rapido',
      branding: {
        logo_url: 'https://cdn.example.com/logo.png',
        primary_color: '#112233',
        secondary_color: '#445566',
      },
    });

    const explanation = resolveQrCascadeExplanation({
      config,
      linkKey: 'upload',
      branding: {
        logo_url: 'https://cdn.example.com/logo.png',
        primary_color: '#112233',
        secondary_color: '#445566',
      },
    });

    expect(explanation.usagePreset).toBe('preset');
    expect(explanation.skinPreset).toBe('event');
    expect(explanation.primaryColor).toBe('event');
    expect(explanation.backgroundColor).toBe('preset');
    expect(explanation.logo).toBe('event');
    expect(explanation.exportDefaults).toBe('preset');
  });

  it('marks custom overrides when the user diverges from defaults', () => {
    const config = buildQrCascadeDefaults({
      linkKey: 'upload',
      usagePreset: 'upload_rapido',
      skinPreset: 'classico',
    });

    const explanation = resolveQrCascadeExplanation({
      config: {
        ...config,
        usage_preset: 'galeria_premium',
        style: {
          ...config.style,
          dots: {
            ...config.style.dots,
            color: '#123456',
          },
        },
      },
      linkKey: 'upload',
    });

    expect(explanation.usagePreset).toBe('custom');
    expect(explanation.primaryColor).toBe('custom');
  });
});

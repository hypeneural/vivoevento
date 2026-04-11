import { describe, expect, it } from 'vitest';

import { buildQrBrandingSeed, buildQrCascadeDefaults } from './qrPresetCascade';

describe('qrPresetCascade', () => {
  it('builds a branding seed that promotes premium visuals when branding exists', () => {
    const seed = buildQrBrandingSeed({
      logo_url: 'https://cdn.example.com/logo.png',
      primary_color: '#112233',
      secondary_color: '#445566',
    });

    expect(seed).toMatchObject({
      skin_preset: 'premium',
      style: {
        dots: { color: '#112233' },
        corners_square: { color: '#112233' },
        corners_dot: { color: '#445566' },
      },
      logo: {
        mode: 'event_logo',
        asset_url: 'https://cdn.example.com/logo.png',
      },
    });
  });

  it('defaults to premium skin when branding exists and no explicit skin is provided', () => {
    const config = buildQrCascadeDefaults({
      linkKey: 'upload',
      usagePreset: 'upload_rapido',
      branding: {
        logo_url: 'https://cdn.example.com/logo.png',
        primary_color: '#112233',
        secondary_color: '#445566',
      },
    });

    expect(config.skin_preset).toBe('premium');
  });

  it('applies branding colors and guardrails without overriding an explicit skin preset', () => {
    const config = buildQrCascadeDefaults({
      linkKey: 'upload',
      usagePreset: 'upload_rapido',
      skinPreset: 'minimalista',
      branding: {
        logo_url: 'https://cdn.example.com/logo.png',
        primary_color: '#112233',
        secondary_color: '#445566',
      },
    });

    expect(config.skin_preset).toBe('minimalista');
    expect(config.style.dots.color).toBe('#112233');
    expect(config.logo.mode).toBe('event_logo');
    expect(config.logo.asset_url).toBe('https://cdn.example.com/logo.png');
    expect(config.advanced.error_correction_level).toBe('H');
  });
});

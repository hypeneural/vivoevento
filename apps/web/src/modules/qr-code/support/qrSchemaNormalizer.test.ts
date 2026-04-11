import { describe, expect, it } from 'vitest';

import { normalizeEventPublicLinkQrConfig } from './qrSchemaNormalizer';
import { QR_CONFIG_VERSION } from './qrTypes';

describe('qrSchemaNormalizer', () => {
  it('fills missing defaults from the link key and upgrades the payload to the current version', () => {
    const normalized = normalizeEventPublicLinkQrConfig(
      {
        skin_preset: 'premium',
      },
      {
        linkKey: 'wall',
      },
    );

    expect(normalized).toMatchObject({
      config_version: QR_CONFIG_VERSION,
      usage_preset: 'telao',
      skin_preset: 'premium',
      render: {
        margin_modules: 4,
      },
    });
  });

  it('applies hard guardrails during normalization', () => {
    const normalized = normalizeEventPublicLinkQrConfig({
      render: {
        margin_modules: 1,
      },
      logo: {
        mode: 'event_logo',
        image_size: 0.9,
      },
      advanced: {
        error_correction_level: 'L',
      },
    });

    expect(normalized.render.margin_modules).toBe(4);
    expect(normalized.logo.image_size).toBe(0.5);
    expect(normalized.advanced.error_correction_level).toBe('H');
  });
});

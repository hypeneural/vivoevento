import { describe, expect, it } from 'vitest';

import { migrateEventPublicLinkQrConfig } from './qrSchemaMigrator';
import { QR_CONFIG_VERSION } from './qrTypes';

describe('qrSchemaMigrator', () => {
  it('migrates legacy version aliases and renamed fields into the current schema shape', () => {
    const migrated = migrateEventPublicLinkQrConfig({
      version: QR_CONFIG_VERSION,
      render: {
        margin: 2,
      },
      advanced: {
        error_correction: 'M',
      },
    });

    expect(migrated).toMatchObject({
      config_version: QR_CONFIG_VERSION,
      render: {
        margin_modules: 2,
      },
      advanced: {
        error_correction_level: 'M',
      },
    });
    expect((migrated.render as { margin?: number }).margin).toBeUndefined();
  });

  it('keeps payloads already in the latest version idempotent', () => {
    const migrated = migrateEventPublicLinkQrConfig({
      config_version: QR_CONFIG_VERSION,
      usage_preset: 'telao',
      render: {
        margin_modules: 6,
      },
    });

    expect(migrated.config_version).toBe(QR_CONFIG_VERSION);
    expect(migrated.render?.margin_modules).toBe(6);
  });
});

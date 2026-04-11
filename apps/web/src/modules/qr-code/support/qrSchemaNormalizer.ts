import { buildQrConfigDefaults, mergeQrConfig } from './qrDefaults';
import { applyQrGuardrails } from './qrGuardrails';
import { migrateEventPublicLinkQrConfig } from './qrSchemaMigrator';
import type { EventPublicLinkQrConfig, EventPublicLinkQrConfigInput, QrLinkKey } from './qrTypes';
import { QR_CONFIG_VERSION } from './qrTypes';

export function normalizeEventPublicLinkQrConfig(
  input?: EventPublicLinkQrConfigInput,
  options?: { linkKey?: QrLinkKey },
): EventPublicLinkQrConfig {
  const migrated = migrateEventPublicLinkQrConfig(input);
  const defaults = buildQrConfigDefaults({
    linkKey: options?.linkKey,
    usagePreset: migrated.usage_preset,
    skinPreset: migrated.skin_preset,
  });

  return applyQrGuardrails(
    mergeQrConfig(defaults, migrated, {
      config_version: QR_CONFIG_VERSION,
    }),
  );
}

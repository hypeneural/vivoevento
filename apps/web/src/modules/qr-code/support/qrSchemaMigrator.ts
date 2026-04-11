import type { DeepPartial, EventPublicLinkQrConfig, EventPublicLinkQrConfigInput } from './qrTypes';
import { QR_CONFIG_VERSION } from './qrTypes';

function cloneInput<T>(value: T): T {
  return value ? (JSON.parse(JSON.stringify(value)) as T) : ({} as T);
}

export function migrateEventPublicLinkQrConfig(
  input: EventPublicLinkQrConfigInput,
): DeepPartial<EventPublicLinkQrConfig> {
  const migrated = cloneInput((input ?? {}) as Record<string, unknown>) as DeepPartial<EventPublicLinkQrConfig> & {
    version?: string | null;
    render?: DeepPartial<EventPublicLinkQrConfig['render']> & { margin?: number | null };
    advanced?: DeepPartial<EventPublicLinkQrConfig['advanced']> & { error_correction?: EventPublicLinkQrConfig['advanced']['error_correction_level'] | null };
  };

  if (migrated.version && !migrated.config_version) {
    migrated.config_version = QR_CONFIG_VERSION;
  }

  delete migrated.version;

  if (migrated.render?.margin != null && migrated.render.margin_modules == null) {
    migrated.render.margin_modules = migrated.render.margin;
  }

  if (migrated.render && 'margin' in migrated.render) {
    delete migrated.render.margin;
  }

  if (migrated.advanced?.error_correction && migrated.advanced.error_correction_level == null) {
    migrated.advanced.error_correction_level = migrated.advanced.error_correction;
  }

  if (migrated.advanced && 'error_correction' in migrated.advanced) {
    delete migrated.advanced.error_correction;
  }

  if (!migrated.config_version) {
    migrated.config_version = QR_CONFIG_VERSION;
  }

  return migrated;
}

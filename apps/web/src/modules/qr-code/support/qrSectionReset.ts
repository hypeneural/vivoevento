import type { EventPublicLinkQrConfig } from './qrTypes';

export type QrSectionKey = 'content' | 'style' | 'logo' | 'export' | 'advanced';

export function resetQrSection(
  config: EventPublicLinkQrConfig,
  defaults: EventPublicLinkQrConfig,
  section: QrSectionKey,
): EventPublicLinkQrConfig {
  switch (section) {
    case 'content':
      return {
        ...config,
        usage_preset: defaults.usage_preset,
      };
    case 'style':
      return {
        ...config,
        style: defaults.style,
      };
    case 'logo':
      return {
        ...config,
        logo: defaults.logo,
      };
    case 'export':
      return {
        ...config,
        export_defaults: defaults.export_defaults,
      };
    case 'advanced':
      return {
        ...config,
        advanced: defaults.advanced,
      };
    default:
      return config;
  }
}

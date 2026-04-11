import type { EventPublicLinkQrConfig } from './qrTypes';

export function applyQrCopyStyle(
  source: EventPublicLinkQrConfig,
  target: EventPublicLinkQrConfig,
): EventPublicLinkQrConfig {
  return {
    ...target,
    usage_preset: source.usage_preset,
    skin_preset: source.skin_preset,
    render: source.render,
    style: source.style,
    logo: source.logo,
    advanced: source.advanced,
    export_defaults: source.export_defaults,
  };
}

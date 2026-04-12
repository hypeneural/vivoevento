import { createQrCodeStylingDriver, type QRCodeStylingOptions } from './qrCodeStylingDriver';
import { buildQrCodeStylingOptions } from './qrOptionsBuilder';
import type { EventPublicLinkQrConfig, QrExportExtension, QrLinkKey } from './qrTypes';

function buildDownloadName(pattern: string, params: { eventId: string; linkKey: QrLinkKey }) {
  const seeded = (pattern || 'evento-{event_id}-{link_key}')
    .replaceAll('{event_id}', params.eventId)
    .replaceAll('{link_key}', params.linkKey);

  return seeded.trim() || `evento-${params.eventId}-${params.linkKey}`;
}

function resolveDrawType(extension: QrExportExtension): QRCodeStylingOptions['type'] {
  return extension === 'svg' ? 'svg' : 'canvas';
}

function buildExportOptions(params: {
  config: EventPublicLinkQrConfig;
  data: string;
  extension: QrExportExtension;
}) {
  const exportConfig: EventPublicLinkQrConfig = {
    ...params.config,
    render: {
      ...params.config.render,
      preview_size: params.config.export_defaults.size,
      preview_type: resolveDrawType(params.extension),
    },
  };

  return buildQrCodeStylingOptions({
    config: exportConfig,
    data: params.data,
  });
}

export async function downloadEventPublicLinkQrCode(params: {
  config: EventPublicLinkQrConfig;
  data: string;
  eventId: string;
  linkKey: QrLinkKey;
}) {
  const extension = params.config.export_defaults.extension;
  const options = buildExportOptions({
    config: params.config,
    data: params.data,
    extension,
  });
  const driver = createQrCodeStylingDriver(options);
  const raw = await driver.getRawData(extension);

  if (!raw) {
    throw new Error('Nao foi possivel gerar o arquivo do QR para download.');
  }

  const blob = raw instanceof Blob
    ? raw
    : new Blob([raw], { type: extension === 'svg' ? 'image/svg+xml' : `image/${extension}` });
  const href = URL.createObjectURL(blob);
  const anchor = document.createElement('a');

  anchor.href = href;
  anchor.download = `${buildDownloadName(params.config.export_defaults.download_name_pattern, {
    eventId: params.eventId,
    linkKey: params.linkKey,
  })}.${extension}`;
  anchor.rel = 'noopener';
  document.body.append(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(href);
}

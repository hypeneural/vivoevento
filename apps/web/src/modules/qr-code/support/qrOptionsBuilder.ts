import type { Gradient, Options as QRCodeStylingOptions } from 'qr-code-styling';

import { applyQrGuardrails } from './qrGuardrails';
import type { EventPublicLinkQrConfig, QrGradientConfig } from './qrTypes';

const PIXELS_PER_MARGIN_MODULE = 4;

function toGradient(gradient: QrGradientConfig | null): Gradient | undefined {
  return gradient ?? undefined;
}

function resolveLogoUrl(config: EventPublicLinkQrConfig): string | undefined {
  return config.logo.asset_url ?? undefined;
}

function resolveCrossOrigin(url: string | undefined): string | undefined {
  if (!url) {
    return undefined;
  }

  if (url.startsWith('http://') || url.startsWith('https://')) {
    return 'anonymous';
  }

  return undefined;
}

export function buildQrCodeStylingOptions(params: {
  config: EventPublicLinkQrConfig;
  data: string;
}): QRCodeStylingOptions {
  const config = applyQrGuardrails(params.config);
  const image = resolveLogoUrl(config);

  return {
    type: config.render.preview_type,
    shape: config.advanced.shape,
    width: config.render.preview_size,
    height: config.render.preview_size,
    margin: config.render.margin_modules * PIXELS_PER_MARGIN_MODULE,
    data: params.data,
    image,
    qrOptions: {
      typeNumber: config.advanced.type_number,
      mode: config.advanced.mode,
      errorCorrectionLevel: config.advanced.error_correction_level,
    },
    imageOptions: {
      saveAsBlob: config.logo.save_as_blob,
      hideBackgroundDots: config.logo.hide_background_dots,
      imageSize: config.logo.image_size,
      margin: config.logo.margin_px,
      crossOrigin: resolveCrossOrigin(image),
    },
    dotsOptions: {
      type: config.style.dots.type,
      color: config.style.dots.color,
      gradient: toGradient(config.style.dots.gradient),
      roundSize: config.advanced.round_size,
    },
    cornersSquareOptions: {
      type: config.style.corners_square.type,
      color: config.style.corners_square.color,
      gradient: toGradient(config.style.corners_square.gradient),
    },
    cornersDotOptions: {
      type: config.style.corners_dot.type,
      color: config.style.corners_dot.color,
      gradient: toGradient(config.style.corners_dot.gradient),
    },
    backgroundOptions: {
      color: config.style.background.transparent ? 'transparent' : config.style.background.color,
      gradient: toGradient(config.style.background.gradient),
    },
  };
}

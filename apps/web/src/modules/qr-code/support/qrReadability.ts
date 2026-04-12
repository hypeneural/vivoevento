import { applyQrGuardrails } from './qrGuardrails';
import type { EventPublicLinkQrConfig, QrReadabilityStatus } from './qrTypes';

export interface QrReadabilityReport {
  status: QrReadabilityStatus;
  score: number;
  label: string;
  tone: 'success' | 'warning' | 'danger';
  reasons: string[];
  blocksExport: boolean;
  blocksSave: boolean;
}

function clampScore(value: number) {
  return Math.max(0, Math.min(100, Math.round(value)));
}

function expandHex(color: string) {
  if (color.length === 4) {
    return `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}`;
  }

  return color;
}

function hexToRgb(color: string) {
  if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color)) {
    return null;
  }

  const normalized = expandHex(color);
  const value = normalized.slice(1);

  return {
    r: Number.parseInt(value.slice(0, 2), 16),
    g: Number.parseInt(value.slice(2, 4), 16),
    b: Number.parseInt(value.slice(4, 6), 16),
  };
}

function channelToLinear(channel: number) {
  const normalized = channel / 255;

  if (normalized <= 0.03928) {
    return normalized / 12.92;
  }

  return ((normalized + 0.055) / 1.055) ** 2.4;
}

function luminance(color: string) {
  const rgb = hexToRgb(color);

  if (!rgb) {
    return null;
  }

  return (
    0.2126 * channelToLinear(rgb.r)
    + 0.7152 * channelToLinear(rgb.g)
    + 0.0722 * channelToLinear(rgb.b)
  );
}

function contrastRatio(foreground: string, background: string) {
  const foregroundLuminance = luminance(foreground);
  const backgroundLuminance = luminance(background);

  if (foregroundLuminance === null || backgroundLuminance === null) {
    return null;
  }

  const lighter = Math.max(foregroundLuminance, backgroundLuminance);
  const darker = Math.min(foregroundLuminance, backgroundLuminance);

  return (lighter + 0.05) / (darker + 0.05);
}

function hasVisualGradient(config: EventPublicLinkQrConfig) {
  return Boolean(
    config.style.dots.gradient
    || config.style.corners_square.gradient
    || config.style.corners_dot.gradient
    || config.style.background.gradient,
  );
}

export function getQrReadabilityReport(config: EventPublicLinkQrConfig): QrReadabilityReport {
  const safeConfig = applyQrGuardrails(config);
  const reasons: string[] = [];
  let score = 100;

  const backgroundColor = safeConfig.style.background.transparent
    ? '#ffffff'
    : safeConfig.style.background.color;
  const contrast = contrastRatio(safeConfig.style.dots.color, backgroundColor);

  if (contrast === null) {
    score -= 45;
    reasons.push('Alguma cor do QR nao esta valida e prejudica a leitura.');
  } else if (contrast < 3) {
    score -= 45;
    reasons.push('O contraste entre modulos e fundo esta muito baixo.');
  } else if (contrast < 4.5) {
    score -= 22;
    reasons.push('O contraste esta aceitavel, mas ainda arriscado para alguns celulares.');
  } else if (contrast < 7) {
    score -= 8;
    reasons.push('O contraste esta bom, mas ainda nao esta no nivel mais folgado.');
  }

  if (safeConfig.style.background.transparent) {
    score -= 18;
    reasons.push('Fundo transparente depende mais do fundo externo e reduz previsibilidade.');
  }

  if (safeConfig.logo.mode !== 'none') {
    score -= 8;
    reasons.push('Logo central exige mais robustez de leitura.');

    if (safeConfig.logo.image_size >= 0.34) {
      score -= 12;
      reasons.push('A logo esta grande para leitura confortavel.');
    }
  }

  if (safeConfig.advanced.error_correction_level === 'Q') {
    score -= 4;
  } else if (safeConfig.advanced.error_correction_level === 'M') {
    score -= 10;
    reasons.push('Correcao de erro media reduz folga quando o visual fica mais decorado.');
  } else if (safeConfig.advanced.error_correction_level === 'L') {
    score -= 18;
    reasons.push('Correcao de erro baixa deixa o QR mais fragil.');
  }

  if (hasVisualGradient(safeConfig)) {
    score -= 10;
    reasons.push('Gradientes deixam a leitura menos previsivel do que cores solidas.');
  }

  if (safeConfig.advanced.shape === 'circle') {
    score -= 8;
    reasons.push('Formato circular tende a ser mais sensivel para leitura.');
  }

  const finalScore = clampScore(score);
  const blocks = contrast !== null && contrast < 2.5;
  const status: QrReadabilityStatus = finalScore >= 80 ? 'great' : finalScore >= 60 ? 'good' : 'risky';

  if (reasons.length === 0) {
    reasons.push('Contraste, margem e logo estao dentro de uma faixa confortavel.');
  }

  return {
    status,
    score: finalScore,
    label: status === 'great' ? 'Otima leitura' : status === 'good' ? 'Boa leitura' : 'Leitura arriscada',
    tone: status === 'great' ? 'success' : status === 'good' ? 'warning' : 'danger',
    reasons,
    blocksExport: blocks,
    blocksSave: blocks,
  };
}

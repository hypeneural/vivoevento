import { describe, expect, it } from 'vitest';

import { normalizeEventPublicLinkQrConfig } from './qrSchemaNormalizer';
import { getQrReadabilityReport } from './qrReadability';

describe('qrReadability', () => {
  it('classifies a high-contrast QR as great', () => {
    const report = getQrReadabilityReport(normalizeEventPublicLinkQrConfig({
      style: {
        dots: { color: '#111827' },
        background: { color: '#ffffff', transparent: false },
      },
    }));

    expect(report.status).toBe('great');
    expect(report.blocksExport).toBe(false);
  });

  it('drops to risky when contrast is too low and blocks save/export in the extreme case', () => {
    const report = getQrReadabilityReport(normalizeEventPublicLinkQrConfig({
      style: {
        dots: { color: '#bbbbbb' },
        background: { color: '#ffffff', transparent: false },
      },
      logo: {
        mode: 'custom',
        asset_path: 'events/branding/1/logo/arquivo.webp',
        image_size: 0.5,
      },
      advanced: {
        error_correction_level: 'L',
        shape: 'circle',
      },
    }));

    expect(report.status).toBe('risky');
    expect(report.blocksSave).toBe(true);
    expect(report.reasons[0]).toMatch(/contraste/i);
  });
});

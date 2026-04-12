import { describe, expect, it } from 'vitest';

import { galleryContractCatalog } from './gallery-builder';
import type { GalleryResponsiveSources } from '@eventovivo/shared-types';

describe('public gallery responsive media contract', () => {
  it('freezes sizes for responsive gallery items', () => {
    expect(galleryContractCatalog.publicResponsiveSizes).toBe(
      '(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw',
    );
  });

  it('requires srcset, sizes and width-aware variants', () => {
    const sources: GalleryResponsiveSources = {
      sizes: galleryContractCatalog.publicResponsiveSizes,
      srcset: [
        'https://cdn.eventovivo.test/media-10-320.webp 320w',
        'https://cdn.eventovivo.test/media-10-768.webp 768w',
        'https://cdn.eventovivo.test/media-10-1440.webp 1440w',
      ].join(', '),
      variants: [
        {
          variant_key: 'grid-sm',
          src: 'https://cdn.eventovivo.test/media-10-320.webp',
          width: 320,
          height: 213,
          mime_type: 'image/webp',
        },
        {
          variant_key: 'grid-md',
          src: 'https://cdn.eventovivo.test/media-10-768.webp',
          width: 768,
          height: 512,
          mime_type: 'image/webp',
        },
      ],
    };

    expect(sources.srcset).toContain('320w');
    expect(sources.srcset).toContain('768w');
    expect(sources.sizes).toContain('50vw');
    expect(sources.variants[0]).toHaveProperty('width', 320);
    expect(sources.variants[0]).toHaveProperty('height', 213);
  });
});

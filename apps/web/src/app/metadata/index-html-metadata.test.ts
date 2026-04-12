import { readFileSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

const indexHtml = readFileSync(path.resolve(__dirname, '../../../index.html'), 'utf8');

describe('index.html metadata', () => {
  it('exposes favicon and social preview metadata for the admin panel', () => {
    expect(indexHtml).toContain('rel="icon" type="image/svg+xml" href="/favicon.svg"');
    expect(indexHtml).toContain('property="og:image" content="%VITE_APP_URL%/social-share-panel.png"');
    expect(indexHtml).toContain('property="og:url" content="%VITE_APP_URL%"');
    expect(indexHtml).toContain('name="twitter:card" content="summary_large_image"');
    expect(indexHtml).toContain('name="robots" content="noindex, nofollow"');
  });
});

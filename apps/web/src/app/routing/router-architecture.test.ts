import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('router architecture characterization', () => {
  it('uses a data router with ScrollRestoration in the app shell', () => {
    const appSource = fs.readFileSync(path.resolve(__dirname, '../../App.tsx'), 'utf8');

    expect(appSource).not.toContain('BrowserRouter');
    expect(appSource).toContain('createBrowserRouter');
    expect(appSource).toContain('RouterProvider');
    expect(appSource).toContain('ScrollRestoration');
  });
});

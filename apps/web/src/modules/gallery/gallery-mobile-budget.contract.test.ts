import { describe, expect, it } from 'vitest';

import { galleryContractCatalog } from './gallery-builder';

describe('gallery mobile-first performance budget', () => {
  it('freezes Web Vitals acceptance budgets for V1', () => {
    expect(galleryContractCatalog.mobileBudget).toEqual({
      lcp_ms: 2500,
      inp_ms: 200,
      cls: 0.1,
      percentile: 75,
    });
  });
});

import { describe, expect, it } from 'vitest';

import { buildSenderScopedPath, readSenderScopedPrefill } from './sender-filters';

describe('sender filter helpers', () => {
  it('builds sender-scoped routes for operational surfaces', () => {
    expect(buildSenderScopedPath('/gallery', 42, '11111111111111@lid')).toBe(
      '/gallery?event_id=42&search=11111111111111%40lid',
    );
  });

  it('reads sender-scoped prefill from url params', () => {
    const params = new URLSearchParams('event_id=84&search=554899999999');

    expect(readSenderScopedPrefill(params)).toEqual({
      eventId: '84',
      search: '554899999999',
    });
  });
});

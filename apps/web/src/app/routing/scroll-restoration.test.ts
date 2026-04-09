import { describe, expect, it } from 'vitest';

import { buildScrollRestorationKey } from './scroll-restoration';

describe('scroll restoration keying', () => {
  it('uses pathname plus critical moderation filters for the moderation queue', () => {
    const key = buildScrollRestorationKey({
      pathname: '/moderation',
      search: '?foo=bar&search=ana&event_id=12&status=all&pinned=1',
      hash: '',
      key: 'ignored-history-entry',
      state: null,
    });

    expect(key).toBe('/moderation?event_id=12&search=ana&pinned=1');
  });

  it('falls back to the location entry key outside the moderation queue', () => {
    const key = buildScrollRestorationKey({
      pathname: '/events',
      search: '?page=2',
      hash: '',
      key: 'history-entry-42',
      state: null,
    });

    expect(key).toBe('history-entry-42');
  });
});

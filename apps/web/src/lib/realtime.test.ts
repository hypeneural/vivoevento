import { describe, expect, it } from 'vitest';

import { isRealtimeTlsAligned, normalizeRealtimeScheme } from './realtime';

describe('realtime tls alignment', () => {
  it('normalizes page protocols and schemes', () => {
    expect(normalizeRealtimeScheme('https:')).toBe('https');
    expect(normalizeRealtimeScheme('https')).toBe('https');
    expect(normalizeRealtimeScheme('http:')).toBe('http');
    expect(normalizeRealtimeScheme('http')).toBe('http');
  });

  it('treats matching protocols as aligned', () => {
    expect(isRealtimeTlsAligned('http:', 'http')).toBe(true);
    expect(isRealtimeTlsAligned('https:', 'https')).toBe(true);
  });

  it('treats mismatched protocols as misaligned', () => {
    expect(isRealtimeTlsAligned('https:', 'http')).toBe(false);
    expect(isRealtimeTlsAligned('http:', 'https')).toBe(false);
  });
});

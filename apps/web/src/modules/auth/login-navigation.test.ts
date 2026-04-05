import { describe, expect, it } from 'vitest';

import { resolveLoginReturnPath } from './login-navigation';

describe('resolveLoginReturnPath', () => {
  it('returns the internal returnTo path when it is safe', () => {
    expect(resolveLoginReturnPath('?returnTo=%2Fcheckout%2Fevento%3Fresume%3Dauth'))
      .toBe('/checkout/evento?resume=auth');
  });

  it('falls back when returnTo is external or malformed', () => {
    expect(resolveLoginReturnPath('?returnTo=https%3A%2F%2Fevil.example')).toBe('/');
    expect(resolveLoginReturnPath('?returnTo=%2F%2Fevil.example')).toBe('/');
    expect(resolveLoginReturnPath('')).toBe('/');
  });
});

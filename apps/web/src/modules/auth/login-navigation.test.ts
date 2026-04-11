import { describe, expect, it } from 'vitest';

import { buildLoginPath, resolveInvitationReturnContext, resolveLoginInitialStep, resolveLoginReturnPath } from './login-navigation';

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

describe('resolveLoginInitialStep', () => {
  it('opens the forgot-password step when the flow query requests it', () => {
    expect(resolveLoginInitialStep('?returnTo=%2Fconvites%2Feventos%2Fabc&flow=forgot')).toBe('forgot');
  });

  it('falls back to the default method step for unknown or missing flows', () => {
    expect(resolveLoginInitialStep('?returnTo=%2Fconvites%2Feventos%2Fabc')).toBe('method');
    expect(resolveLoginInitialStep('?flow=unknown')).toBe('method');
  });
});

describe('resolveInvitationReturnContext', () => {
  it('recognizes organization invitations', () => {
    expect(resolveInvitationReturnContext('/convites/equipe/token-123')).toEqual({
      kind: 'organization',
      badge: 'Convite da equipe',
      title: 'Você está entrando para continuar um convite da equipe.',
      description: 'Depois de entrar ou redefinir a senha, você voltará para este convite. Essa mesma conta pode ser usada em vários eventos e convites.',
    });
  });

  it('recognizes event invitations', () => {
    expect(resolveInvitationReturnContext('/convites/eventos/token-123')).toEqual({
      kind: 'event',
      badge: 'Convite de evento',
      title: 'Você está entrando para continuar um convite de evento.',
      description: 'Depois de entrar ou redefinir a senha, você voltará para este convite. Essa mesma conta pode ser usada em vários eventos e convites.',
    });
  });

  it('returns null for regular dashboard routes', () => {
    expect(resolveInvitationReturnContext('/plans')).toBeNull();
  });
});

describe('buildLoginPath', () => {
  it('builds a login path preserving the returnTo param', () => {
    expect(buildLoginPath('/convites/eventos/token-123')).toBe('/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123');
  });

  it('can request the forgot-password flow while preserving the invitation return path', () => {
    expect(buildLoginPath('/convites/equipe/token-123', { flow: 'forgot' }))
      .toBe('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');
  });
});

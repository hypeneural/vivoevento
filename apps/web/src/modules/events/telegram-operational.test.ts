import { describe, expect, it } from 'vitest';

import { describeTelegramOperationalSignal, maskTelegramExternalId } from './telegram-operational';

describe('telegram operational helpers', () => {
  it('describes bot block and unblock signals with stable copy', () => {
    expect(describeTelegramOperationalSignal('bot_blocked_by_user')).toBe('Bot bloqueado pelo usuario');
    expect(describeTelegramOperationalSignal('bot_unblocked_by_user')).toBe('Bot desbloqueado pelo usuario');
    expect(describeTelegramOperationalSignal('other')).toBe('Mudanca operacional do bot');
  });

  it('masks large telegram ids for operational cards', () => {
    expect(maskTelegramExternalId('9007199254740991')).toBe('9007...0991');
    expect(maskTelegramExternalId('1234')).toBe('1234');
    expect(maskTelegramExternalId(null)).toBe('ID indisponivel');
  });
});

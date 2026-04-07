export function describeTelegramOperationalSignal(signal: string | null | undefined): string {
  switch (signal) {
    case 'bot_blocked_by_user':
      return 'Bot bloqueado pelo usuario';
    case 'bot_unblocked_by_user':
      return 'Bot desbloqueado pelo usuario';
    default:
      return 'Mudanca operacional do bot';
  }
}

export function maskTelegramExternalId(value: string | null | undefined): string {
  if (!value) {
    return 'ID indisponivel';
  }

  if (value.length <= 8) {
    return value;
  }

  return `${value.slice(0, 4)}...${value.slice(-4)}`;
}

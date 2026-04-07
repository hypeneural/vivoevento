import type {
  EventBlacklistIdentityType,
  EventIntakeBlacklistSenderSummary,
} from './intake';

export function initialsFromName(value?: string | null) {
  if (!value) {
    return 'EV';
  }

  const parts = value.trim().split(/\s+/).filter(Boolean).slice(0, 2);

  if (parts.length === 0) {
    return 'EV';
  }

  return parts.map((part) => part.charAt(0).toUpperCase()).join('');
}

export function senderPrimaryLabel(sender: EventIntakeBlacklistSenderSummary) {
  return sender.sender_name
    ?? sender.sender_phone
    ?? sender.sender_lid
    ?? sender.sender_external_id
    ?? 'Remetente sem identificacao';
}

export function senderSecondaryLabel(sender: EventIntakeBlacklistSenderSummary) {
  return sender.sender_phone
    ?? sender.sender_lid
    ?? sender.sender_external_id
    ?? 'Identificador indisponivel';
}

export function identityTypeLabel(type: EventBlacklistIdentityType) {
  switch (type) {
    case 'phone':
      return 'Telefone';
    case 'lid':
      return '@LID';
    default:
      return 'External ID';
  }
}

export function formatSenderDateTime(value?: string | null) {
  if (!value) {
    return 'Agora';
  }

  return new Date(value).toLocaleString('pt-BR');
}

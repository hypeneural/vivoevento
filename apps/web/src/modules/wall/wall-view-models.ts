import { formatDistanceToNowStrict, isValid } from 'date-fns';
import { ptBR } from 'date-fns/locale';

export function formatWallRelativeTime(
  timestamp?: string | null,
  fallback = 'Sem atividade recente',
): string {
  if (!timestamp) {
    return fallback;
  }

  const date = new Date(timestamp);

  if (!isValid(date)) {
    return fallback;
  }

  return formatDistanceToNowStrict(date, {
    addSuffix: true,
    locale: ptBR,
  });
}

export function formatWallLastCaptureLabel(timestamp?: string | null): string {
  return formatWallRelativeTime(timestamp, 'Sem novas midias ainda');
}

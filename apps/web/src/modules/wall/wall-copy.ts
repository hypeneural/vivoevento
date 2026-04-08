import type { ApiWallMediaSource, ApiWallRecentItemStatus } from '@/lib/api-types';

export const WALL_INSIGHTS_COPY = {
  topContributor: 'Quem mais enviou',
  totalMedia: 'Total de midias',
  recentMedia: 'Ultimas chegadas',
  selectedMedia: 'Midia selecionada do topo',
} as const;

export function formatWallSourceLabel(source: ApiWallMediaSource): string {
  switch (source) {
    case 'telegram':
      return 'Telegram';
    case 'upload':
      return 'Upload';
    case 'manual':
      return 'Manual';
    case 'gallery':
      return 'Galeria';
    default:
      return 'WhatsApp';
  }
}

export function formatWallRecentStatusLabel(status: ApiWallRecentItemStatus): string {
  switch (status) {
    case 'approved':
      return 'Aprovada';
    case 'queued':
      return 'Na fila';
    case 'displayed':
      return 'Exibida';
    case 'error':
      return 'Com problema';
    default:
      return 'Recebida';
  }
}

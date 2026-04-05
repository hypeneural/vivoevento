import type { ApiEventMediaItem } from '@/lib/api-types';

import type { ModerationQuickFilterKey } from './types';

export function formatDateTime(value?: string | null) {
  if (!value) return 'Sem registro';

  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

export function formatShortTime(value?: string | null) {
  if (!value) return '--:--';

  return new Intl.DateTimeFormat('pt-BR', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

export function getAspectRatio(media: ApiEventMediaItem) {
  if ((media.width ?? 0) > 0 && (media.height ?? 0) > 0) {
    return Math.max((media.width ?? 1) / Math.max(media.height ?? 1, 1), 0.65);
  }

  return media.orientation === 'landscape'
    ? 4 / 3
    : media.orientation === 'portrait'
      ? 3 / 4
      : 1;
}

export function isVideoAsset(
  media: Pick<ApiEventMediaItem, 'media_type'> & { mime_type?: string | null },
  url?: string | null,
) {
  if (media.mime_type?.startsWith('video/')) {
    return true;
  }

  if (media.media_type !== 'video' || !url) {
    return false;
  }

  try {
    const pathname = new URL(url, window.location.origin).pathname.toLowerCase();

    return ['.mp4', '.webm', '.ogg', '.mov', '.m4v'].some((extension) => pathname.endsWith(extension));
  } catch {
    const normalizedUrl = url.toLowerCase();

    return ['.mp4', '.webm', '.ogg', '.mov', '.m4v'].some((extension) => normalizedUrl.includes(extension));
  }
}

export function getOrientationLabel(orientation?: string | null) {
  switch (orientation) {
    case 'portrait':
      return 'Vertical';
    case 'landscape':
      return 'Horizontal';
    default:
      return 'Quadrada';
  }
}

export function resolveQuickFilter(
  statusFilter: string,
  featuredOnly: boolean,
  pinnedOnly: boolean,
): ModerationQuickFilterKey | null {
  if (!featuredOnly && !pinnedOnly && statusFilter === 'all') {
    return 'all';
  }

  if (!featuredOnly && !pinnedOnly && statusFilter === 'pending_moderation') {
    return 'pending_moderation';
  }

  if (!featuredOnly && !pinnedOnly && statusFilter === 'approved') {
    return 'approved';
  }

  if (!featuredOnly && !pinnedOnly && statusFilter === 'rejected') {
    return 'rejected';
  }

  if (featuredOnly && !pinnedOnly && statusFilter === 'all') {
    return 'featured';
  }

  if (pinnedOnly && !featuredOnly && statusFilter === 'all') {
    return 'pinned';
  }

  return null;
}

export function buildActionMessage(action: 'approve' | 'reject' | 'favorite' | 'pin', media: ApiEventMediaItem) {
  if (action === 'approve') {
    return {
      title: 'Midia aprovada',
      description: `${media.sender_name || 'Convidado'} foi liberado para a galeria.`,
    };
  }

  if (action === 'reject') {
    return {
      title: 'Midia reprovada',
      description: `${media.sender_name || 'Convidado'} saiu da fila publica.`,
    };
  }

  if (action === 'favorite') {
    return media.is_featured
      ? {
        title: 'Favorito removido',
        description: 'A midia saiu da selecao de destaque.',
      }
      : {
        title: 'Midia favorita',
        description: 'A midia entrou na selecao de destaque.',
      };
  }

  return media.is_pinned
    ? {
      title: 'Midia desafixada',
      description: 'A ordem padrao da galeria foi restaurada.',
    }
    : {
      title: 'Midia fixada',
      description: 'Ela passara a aparecer primeiro na galeria publica.',
    };
}

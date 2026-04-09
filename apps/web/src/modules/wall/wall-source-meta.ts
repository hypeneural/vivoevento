import type { LucideIcon } from 'lucide-react';
import { Images, MessageCircle, Pencil, Send, Upload } from 'lucide-react';

import type { ApiWallMediaSource } from '@/lib/api-types';

import { formatWallSourceLabel } from './wall-copy';

interface WallSourceMeta {
  label: string;
  Icon: LucideIcon;
  chipClassName: string;
}

interface WallMediaSemanticMeta {
  isVideo: boolean;
  badgeLabel: string;
  detailLabel: string;
  operationalLabel: string;
  chipClassName: string;
}

const WALL_SOURCE_META: Record<ApiWallMediaSource, WallSourceMeta> = {
  whatsapp: {
    label: formatWallSourceLabel('whatsapp'),
    Icon: MessageCircle,
    chipClassName: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700',
  },
  telegram: {
    label: formatWallSourceLabel('telegram'),
    Icon: Send,
    chipClassName: 'border-sky-500/30 bg-sky-500/10 text-sky-700',
  },
  upload: {
    label: formatWallSourceLabel('upload'),
    Icon: Upload,
    chipClassName: 'border-violet-500/30 bg-violet-500/10 text-violet-700',
  },
  manual: {
    label: formatWallSourceLabel('manual'),
    Icon: Pencil,
    chipClassName: 'border-amber-500/30 bg-amber-500/10 text-amber-700',
  },
  gallery: {
    label: formatWallSourceLabel('gallery'),
    Icon: Images,
    chipClassName: 'border-slate-500/30 bg-slate-500/10 text-slate-700',
  },
};

export function getWallSourceMeta(source: ApiWallMediaSource): WallSourceMeta {
  return WALL_SOURCE_META[source];
}

export function getWallMediaSemanticMeta({
  isVideo,
  durationSeconds,
  videoPolicyLabel,
}: {
  isVideo?: boolean | null;
  durationSeconds?: number | null;
  videoPolicyLabel?: string | null;
}): WallMediaSemanticMeta {
  if (!isVideo) {
    return {
      isVideo: false,
      badgeLabel: 'Foto',
      detailLabel: 'Foto',
      operationalLabel: 'Imagem pronta para a rotacao normal do telao.',
      chipClassName: 'border-slate-500/30 bg-slate-500/10 text-slate-700',
    };
  }

  const detailLabel = durationSeconds != null ? `Video ${durationSeconds}s` : 'Video';

  return {
    isVideo: true,
    badgeLabel: detailLabel,
    detailLabel,
    operationalLabel: videoPolicyLabel ?? resolveFallbackVideoPolicyLabel(durationSeconds),
    chipClassName: resolveVideoChipClassName(durationSeconds, videoPolicyLabel),
  };
}

function resolveFallbackVideoPolicyLabel(durationSeconds?: number | null) {
  if (durationSeconds == null) {
    return 'Video sem duracao confirmada para leitura operacional.';
  }

  if (durationSeconds <= 15) {
    return 'Video curto. Costuma caber melhor na rotacao atual do telao.';
  }

  if (durationSeconds > 30) {
    return 'Video longo. Requer politica especial para nao ser cortado pela rotacao atual.';
  }

  return 'Video com duracao diferenciada. Vale acompanhar o intervalo atual do telao.';
}

function resolveVideoChipClassName(durationSeconds?: number | null, videoPolicyLabel?: string | null) {
  if ((videoPolicyLabel ?? '').toLowerCase().includes('politica especial') || (durationSeconds != null && durationSeconds > 30)) {
    return 'border-amber-500/30 bg-amber-500/10 text-amber-700';
  }

  if (durationSeconds != null && durationSeconds <= 15) {
    return 'border-sky-500/30 bg-sky-500/10 text-sky-700';
  }

  return 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-700';
}

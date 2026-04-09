import type { LucideIcon } from 'lucide-react';
import { Images, MessageCircle, Pencil, Send, Upload } from 'lucide-react';

import type { ApiWallMediaSource, ApiWallVideoAdmission } from '@/lib/api-types';

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

interface WallVideoAdmissionMeta {
  stateLabel: string;
  summaryLabel: string;
  chipClassName: string;
  assetSourceLabel: string;
  preferredVariantLabel: string;
  previewVariantLabel: string;
  reasonLabels: string[];
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

export function getWallVideoAdmissionMeta(admission?: ApiWallVideoAdmission | null): WallVideoAdmissionMeta | null {
  if (!admission) {
    return null;
  }

  const stateLabel =
    admission.state === 'blocked'
      ? 'Bloqueado no backend'
      : admission.state === 'eligible_with_fallback'
        ? 'Elegivel com fallback'
        : 'Elegivel';

  const chipClassName =
    admission.state === 'blocked'
      ? 'border-rose-500/30 bg-rose-500/10 text-rose-700'
      : admission.state === 'eligible_with_fallback'
        ? 'border-amber-500/30 bg-amber-500/10 text-amber-700'
        : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700';

  const assetSourceLabel = admission.asset_source === 'wall_variant' ? 'Variante otimizada do wall' : 'Arquivo original';
  const preferredVariantLabel = formatWallVariantLabel(admission.preferred_variant_key);
  const previewVariantLabel = formatWallVariantLabel(admission.poster_variant_key);
  const reasonLabels = admission.reasons.map((reason) => formatWallVideoAdmissionReason(reason, admission.duration_limit_seconds));

  return {
    stateLabel,
    summaryLabel:
      admission.state === 'blocked'
        ? `Backend vai bloquear este video. ${reasonLabels.join(' ')}`
        : admission.state === 'eligible_with_fallback'
          ? `Backend ainda aceita este video, mas com fallback operacional. ${reasonLabels.join(' ')}`
          : `Backend considera o video apto para o wall com ${assetSourceLabel.toLowerCase()}.`,
    chipClassName,
    assetSourceLabel,
    preferredVariantLabel,
    previewVariantLabel,
    reasonLabels,
  };
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

function formatWallVideoAdmissionReason(reason: string, durationLimitSeconds: number) {
  switch (reason) {
    case 'video_disabled':
      return 'Video desativado na policy atual do telao.';
    case 'missing_metadata':
      return 'Metadata minima de duracao ou dimensao ainda nao ficou pronta.';
    case 'duration_over_limit':
      return `Duracao acima do limite operacional de ${durationLimitSeconds}s.`;
    case 'unsupported_format':
      return 'Formato fora do baseline suportado para o wall.';
    case 'variant_missing':
      return 'Variante otimizada de wall ainda indisponivel.';
    case 'poster_missing':
      return 'Poster de seguranca ainda indisponivel.';
    default:
      return reason;
  }
}

function formatWallVariantLabel(value?: string | null) {
  if (!value) {
    return 'Sem variante pronta';
  }

  return value;
}

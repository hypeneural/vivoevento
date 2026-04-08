import type { LucideIcon } from 'lucide-react';
import { Images, MessageCircle, Pencil, Send, Upload } from 'lucide-react';

import type { ApiWallMediaSource } from '@/lib/api-types';

import { formatWallSourceLabel } from './wall-copy';

interface WallSourceMeta {
  label: string;
  Icon: LucideIcon;
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

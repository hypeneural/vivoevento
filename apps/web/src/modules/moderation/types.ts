import type { ApiEventMediaItem, CursorPaginatedMeta, ModerationStatsMeta } from '@/lib/api-types';

export type ModerationStatusFilter =
  | 'received'
  | 'processing'
  | 'pending_moderation'
  | 'approved'
  | 'published'
  | 'rejected'
  | 'error';

export type ModerationOrientationFilter = 'portrait' | 'landscape' | 'square';

export type ModerationQuickFilterKey =
  | 'all'
  | 'pending_moderation'
  | 'approved'
  | 'rejected'
  | 'featured'
  | 'pinned';

export interface ModerationListFilters {
  per_page?: number;
  cursor?: string | null;
  event_id?: number;
  search?: string;
  status?: ModerationStatusFilter;
  featured?: boolean;
  pinned?: boolean;
  orientation?: ModerationOrientationFilter;
}

export interface ModerationFeedMeta extends CursorPaginatedMeta {
  stats: ModerationStatsMeta | null;
}

export interface ModerationFeedPage {
  data: ApiEventMediaItem[];
  meta: ModerationFeedMeta;
}

export interface ModerationBulkActionResponse {
  count: number;
  ids: number[];
  items: ApiEventMediaItem[];
}

export interface ModerationQuickFilterOption {
  key: ModerationQuickFilterKey;
  label: string;
  helper: string;
}

export const MODERATION_STATUS_OPTIONS: Array<{ value: ModerationStatusFilter; label: string }> = [
  { value: 'pending_moderation', label: 'Nao moderadas' },
  { value: 'approved', label: 'Aprovadas' },
  { value: 'rejected', label: 'Reprovadas' },
  { value: 'published', label: 'Publicadas' },
  { value: 'processing', label: 'Processando' },
  { value: 'received', label: 'Recebidas' },
  { value: 'error', label: 'Com erro' },
];

export const MODERATION_ORIENTATION_OPTIONS: Array<{ value: ModerationOrientationFilter; label: string }> = [
  { value: 'portrait', label: 'Vertical' },
  { value: 'landscape', label: 'Horizontal' },
  { value: 'square', label: 'Quadrada' },
];

export const MODERATION_QUICK_FILTERS: ModerationQuickFilterOption[] = [
  { key: 'all', label: 'Tudo', helper: 'Fila completa' },
  { key: 'pending_moderation', label: 'Nao moderadas', helper: 'Prontas para revisar' },
  { key: 'approved', label: 'Aprovadas', helper: 'Liberadas pela equipe' },
  { key: 'rejected', label: 'Reprovadas', helper: 'Bloqueadas' },
  { key: 'featured', label: 'Favoritas', helper: 'Com destaque visual' },
  { key: 'pinned', label: 'Fixadas', helper: 'No topo da galeria' },
];

export const MODERATION_PAGE_SIZE_OPTIONS = [18, 24, 36, 48] as const;

export const MODERATION_EVENT_NAMES = {
  created: 'moderation.media.created',
  updated: 'moderation.media.updated',
  deleted: 'moderation.media.deleted',
} as const;

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
export type ModerationMediaTypeFilter = 'image' | 'video';

export type ModerationQuickFilterKey =
  | 'all'
  | 'pending_moderation'
  | 'approved'
  | 'rejected'
  | 'error'
  | 'images'
  | 'videos'
  | 'ai_review'
  | 'duplicates'
  | 'featured'
  | 'pinned'
  | 'blocked_sender';

export interface ModerationListFilters {
  per_page?: number;
  cursor?: string | null;
  event_id?: number;
  search?: string;
  status?: ModerationStatusFilter;
  featured?: boolean;
  pinned?: boolean;
  sender_blocked?: boolean;
  orientation?: ModerationOrientationFilter;
  media_type?: ModerationMediaTypeFilter;
  duplicates?: boolean;
  ai_review?: boolean;
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

export interface ModerationRejectReasonPreset {
  value: string;
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

export const MODERATION_MEDIA_TYPE_OPTIONS: Array<{ value: ModerationMediaTypeFilter; label: string }> = [
  { value: 'image', label: 'Imagens' },
  { value: 'video', label: 'Videos' },
];

export const MODERATION_QUICK_FILTERS: ModerationQuickFilterOption[] = [
  { key: 'all', label: 'Tudo', helper: 'Fila completa' },
  { key: 'pending_moderation', label: 'Nao moderadas', helper: 'Prontas para revisar' },
  { key: 'approved', label: 'Aprovadas', helper: 'Liberadas pela equipe' },
  { key: 'rejected', label: 'Reprovadas', helper: 'Bloqueadas' },
  { key: 'error', label: 'Com erro', helper: 'Falharam no pipeline tecnico' },
  { key: 'images', label: 'Imagens', helper: 'Somente fotos e artes estaticas' },
  { key: 'videos', label: 'Videos', helper: 'Somente videos na fila' },
  { key: 'ai_review', label: 'IA em review', helper: 'Retidas pela esteira automatica' },
  { key: 'duplicates', label: 'Duplicatas', helper: 'Somente clusters repetidos' },
  { key: 'featured', label: 'Favoritas', helper: 'Com destaque visual' },
  { key: 'pinned', label: 'Fixadas', helper: 'No topo da galeria' },
  { key: 'blocked_sender', label: 'Remetente bloqueado', helper: 'Somente autores bloqueados' },
];

export const MODERATION_PAGE_SIZE_OPTIONS = [18, 24, 36, 48] as const;

export const MODERATION_REJECT_REASON_PRESETS: ModerationRejectReasonPreset[] = [
  { value: 'Conteudo inadequado', label: 'Conteudo inadequado', helper: 'Bloqueia material fora da politica do evento.' },
  { value: 'Baixa qualidade', label: 'Baixa qualidade', helper: 'Sinaliza arquivo ruim ou sem nitidez suficiente.' },
  { value: 'Duplicada', label: 'Duplicada', helper: 'Marca repeticao da mesma captura ou enquadramento.' },
  { value: 'Fora do contexto', label: 'Fora do contexto', helper: 'Indica que a midia nao pertence ao evento.' },
  { value: 'Spam', label: 'Spam', helper: 'Filtra envio abusivo ou promocional.' },
];

export const MODERATION_EVENT_NAMES = {
  created: 'moderation.media.created',
  updated: 'moderation.media.updated',
  deleted: 'moderation.media.deleted',
} as const;

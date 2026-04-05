import type {
  ApiEventMediaItem,
  MediaCatalogStatsMeta,
  PaginatedMeta,
} from '@/lib/api-types';

export type MediaCatalogStatusFilter =
  | 'received'
  | 'processing'
  | 'pending_moderation'
  | 'approved'
  | 'published'
  | 'rejected'
  | 'error';

export type MediaCatalogChannelFilter =
  | 'upload'
  | 'link'
  | 'whatsapp'
  | 'telegram'
  | 'qrcode';

export type MediaCatalogMediaTypeFilter = 'image' | 'video';
export type MediaCatalogOrientationFilter = 'portrait' | 'landscape' | 'square';
export type MediaCatalogFaceIndexStatusFilter = 'queued' | 'processing' | 'indexed' | 'skipped' | 'failed';
export type MediaCatalogSortBy = 'created_at' | 'published_at' | 'sort_order';
export type MediaCatalogSortDirection = 'asc' | 'desc';
export type MediaCatalogPublicationStatusFilter = 'draft' | 'published' | 'hidden' | 'deleted';

export interface MediaCatalogFilters {
  page?: number;
  per_page?: number;
  event_id?: number;
  search?: string;
  status?: MediaCatalogStatusFilter;
  channel?: MediaCatalogChannelFilter;
  media_type?: MediaCatalogMediaTypeFilter;
  featured?: boolean;
  duplicates?: boolean;
  face_search_enabled?: boolean;
  face_index_status?: MediaCatalogFaceIndexStatusFilter;
  publication_status?: MediaCatalogPublicationStatusFilter;
  orientation?: MediaCatalogOrientationFilter;
  created_from?: string;
  created_to?: string;
  sort_by?: MediaCatalogSortBy;
  sort_direction?: MediaCatalogSortDirection;
}

export interface MediaCatalogPageResponse {
  data: ApiEventMediaItem[];
  meta: PaginatedMeta & {
    stats: MediaCatalogStatsMeta;
  };
}

export const MEDIA_CATALOG_STATUS_OPTIONS: Array<{ value: MediaCatalogStatusFilter; label: string }> = [
  { value: 'pending_moderation', label: 'Nao moderadas' },
  { value: 'published', label: 'Publicadas' },
  { value: 'approved', label: 'Aprovadas' },
  { value: 'processing', label: 'Processando' },
  { value: 'received', label: 'Recebidas' },
  { value: 'rejected', label: 'Reprovadas' },
  { value: 'error', label: 'Com erro' },
];

export const MEDIA_CATALOG_CHANNEL_OPTIONS: Array<{ value: MediaCatalogChannelFilter; label: string }> = [
  { value: 'upload', label: 'Upload' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'link', label: 'Link' },
  { value: 'telegram', label: 'Telegram' },
  { value: 'qrcode', label: 'QR Code' },
];

export const MEDIA_CATALOG_TYPE_OPTIONS: Array<{ value: MediaCatalogMediaTypeFilter; label: string }> = [
  { value: 'image', label: 'Imagem' },
  { value: 'video', label: 'Video' },
];

export const MEDIA_CATALOG_FACE_INDEX_OPTIONS: Array<{ value: MediaCatalogFaceIndexStatusFilter; label: string }> = [
  { value: 'indexed', label: 'Rosto indexado' },
  { value: 'queued', label: 'Na fila facial' },
  { value: 'processing', label: 'Processando rosto' },
  { value: 'skipped', label: 'Sem index facial' },
  { value: 'failed', label: 'Falha facial' },
];

export const MEDIA_CATALOG_SORT_OPTIONS: Array<{ value: MediaCatalogSortBy; label: string }> = [
  { value: 'created_at', label: 'Mais recentes' },
  { value: 'published_at', label: 'Publicacao recente' },
  { value: 'sort_order', label: 'Fixadas primeiro' },
];

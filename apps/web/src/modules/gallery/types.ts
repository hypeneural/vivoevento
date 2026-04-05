import type { MediaCatalogPageResponse } from '@/modules/media/types';

export type GalleryPublicationStatusFilter = 'draft' | 'published' | 'hidden' | 'deleted';
export type GalleryMediaTypeFilter = 'image' | 'video';
export type GalleryChannelFilter = 'upload' | 'link' | 'whatsapp' | 'telegram' | 'qrcode';
export type GalleryOrientationFilter = 'portrait' | 'landscape' | 'square';
export type GallerySortBy = 'sort_order' | 'published_at' | 'created_at';
export type GallerySortDirection = 'asc' | 'desc';

export interface GalleryCatalogFilters {
  page?: number;
  per_page?: number;
  event_id?: number;
  search?: string;
  channel?: GalleryChannelFilter;
  media_type?: GalleryMediaTypeFilter;
  featured?: boolean;
  pinned?: boolean;
  publication_status?: GalleryPublicationStatusFilter;
  orientation?: GalleryOrientationFilter;
  created_from?: string;
  created_to?: string;
  sort_by?: GallerySortBy;
  sort_direction?: GallerySortDirection;
}

export type GalleryCatalogPageResponse = MediaCatalogPageResponse;

export const GALLERY_PUBLICATION_OPTIONS: Array<{ value: GalleryPublicationStatusFilter; label: string }> = [
  { value: 'published', label: 'Publicadas' },
  { value: 'hidden', label: 'Ocultas' },
  { value: 'draft', label: 'Prontas para publicar' },
  { value: 'deleted', label: 'Removidas' },
];

export const GALLERY_ORIENTATION_OPTIONS: Array<{ value: GalleryOrientationFilter; label: string }> = [
  { value: 'portrait', label: 'Vertical' },
  { value: 'landscape', label: 'Horizontal' },
  { value: 'square', label: 'Quadrada' },
];

export const GALLERY_SORT_OPTIONS: Array<{ value: GallerySortBy; label: string }> = [
  { value: 'sort_order', label: 'Ordem da galeria' },
  { value: 'published_at', label: 'Publicacao recente' },
  { value: 'created_at', label: 'Upload recente' },
];

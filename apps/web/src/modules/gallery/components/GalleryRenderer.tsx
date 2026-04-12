import { useMemo, useRef, useState } from 'react';
import {
  MasonryPhotoAlbum,
  RowsPhotoAlbum,
  ColumnsPhotoAlbum,
  type Photo,
} from 'react-photo-album';
import { Play } from 'lucide-react';

import type { ApiEventMediaItem } from '@/lib/api-types';
import type { GalleryExperienceConfig } from '@eventovivo/shared-types';
import { cn } from '@/lib/utils';
import { MediaVirtualFeed } from '@/modules/media/components/MediaVirtualFeed';
import type { GalleryRenderMode } from '../gallery-builder';
import { useGalleryReducedMotion } from '../hooks/useGalleryReducedMotion';
import { GalleryPhotoLightbox, type GalleryLightboxPhoto } from './GalleryPhotoLightbox';
import { GalleryVideoModal } from './GalleryVideoModal';

const FIRST_BAND_COUNT = 4;

interface GalleryRendererProps {
  media: ApiEventMediaItem[];
  experience?: GalleryExperienceConfig | null;
  className?: string;
  renderMode?: GalleryRenderMode;
}

interface GalleryPhoto extends Photo {
  media: ApiEventMediaItem;
  mediaType: string;
  responsiveSizes?: string;
  lightboxIndex: number | null;
}

export function GalleryRenderer({
  media,
  experience,
  className,
  renderMode = 'standard',
}: GalleryRendererProps) {
  const [activePhotoIndex, setActivePhotoIndex] = useState<number | null>(null);
  const [activeVideo, setActiveVideo] = useState<ApiEventMediaItem | null>(null);
  const loadMoreRef = useRef<HTMLDivElement | null>(null);
  const respectUserPreference = experience?.theme_tokens.motion.respect_user_preference ?? true;
  const { shouldReduceMotion } = useGalleryReducedMotion(respectUserPreference);

  const { photos, lightboxPhotos } = useMemo(() => {
    const nextLightboxPhotos: GalleryLightboxPhoto[] = [];
    const nextPhotos = media
      .map((item): GalleryPhoto | null => {
        const source = item.preview_url || item.thumbnail_url || item.original_url;

        if (!source) {
          return null;
        }

        const width = resolveWidth(item);
        const height = resolveHeight(item);
        const alt = item.caption || item.sender_name || 'Midia da galeria';
        const isImage = item.media_type === 'image';
        const lightboxIndex = isImage ? nextLightboxPhotos.length : null;

        if (isImage) {
          nextLightboxPhotos.push({
            src: item.preview_url || source,
            msrc: item.thumbnail_url || source,
            width,
            height,
            alt,
            srcset: item.responsive_sources?.srcset || undefined,
            sizes: item.responsive_sources?.sizes || undefined,
          });
        }

        return {
          key: String(item.id),
          src: source,
          width,
          height,
          alt,
          label: `Abrir ${alt}`,
          srcSet: item.responsive_sources?.variants.map((variant) => ({
            src: variant.src,
            width: variant.width,
            height: variant.height,
          })),
          media: item,
          mediaType: item.media_type,
          responsiveSizes: item.responsive_sources?.sizes,
          lightboxIndex,
        };
      })
      .filter((photo): photo is GalleryPhoto => photo !== null);

    return {
      photos: nextPhotos,
      lightboxPhotos: nextLightboxPhotos,
    };
  }, [media]);

  const photosByMediaId = useMemo(
    () => new Map(photos.map((photo) => [photo.media.id, photo])),
    [photos],
  );

  if (photos.length === 0) {
    return (
      <div className={cn('rounded-3xl border border-white/10 bg-white/5 py-14 text-center text-sm text-white/65', className)}>
        Ainda nao existem imagens publicadas para esta galeria.
      </div>
    );
  }

  const layout = normalizeLayout(experience?.media_behavior.grid.layout);
  const imageClassName = shouldReduceMotion
    ? 'h-full w-full object-cover'
    : 'h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]';
  const contentVisibilityStyle = experience?.media_behavior.loading.content_visibility === 'auto'
    ? {
        contentVisibility: 'auto' as const,
        containIntrinsicSize: '900px 1800px',
      }
    : undefined;
  const albumProps = {
    photos,
    spacing: 12,
    padding: 0,
    breakpoints: experience?.media_behavior.grid.breakpoints ?? [360, 768, 1200],
    defaultContainerWidth: 1200,
    sizes: {
      size: '25vw',
      sizes: [
        { viewport: '(max-width: 640px)', size: '50vw' },
        { viewport: '(max-width: 1200px)', size: '33vw' },
      ],
    },
    componentsProps: {
      container: {
        style: contentVisibilityStyle,
      },
      button: ({ photo }: { photo: GalleryPhoto }) => ({
        type: 'button' as const,
        'aria-label': `Abrir ${photo.alt ?? 'midia da galeria'}`,
        className: 'group relative block overflow-hidden rounded-3xl border border-white/10 bg-white/5 text-left shadow-none',
      }),
      image: ({ index, photo }: { index: number; photo: GalleryPhoto }) => ({
        loading: index < FIRST_BAND_COUNT ? 'eager' as const : 'lazy' as const,
        decoding: 'async' as const,
        sizes: photo.responsiveSizes,
        className: imageClassName,
      }),
    },
    render: {
      extras: (_: object, { photo }: { photo: GalleryPhoto }) => (
        photo.mediaType === 'video' ? (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-black/70 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
            <Play className="h-3 w-3 fill-current" />
            Video
          </span>
        ) : null
      ),
    },
    onClick: ({ photo }: { photo: GalleryPhoto }) => {
      if (photo.mediaType === 'video') {
        setActiveVideo(photo.media);

        return;
      }

      setActivePhotoIndex(photo.lightboxIndex);
    },
  };

  const renderVirtualizedCard = (item: ApiEventMediaItem) => {
    const photo = photosByMediaId.get(item.id);

    if (!photo) {
      return null;
    }

    return (
      <button
        type="button"
        key={item.id}
        aria-label={`Abrir ${photo.alt ?? 'midia da galeria'}`}
        className="group relative block overflow-hidden rounded-3xl border border-white/10 bg-white/5 text-left shadow-none"
        onClick={() => {
          if (photo.mediaType === 'video') {
            setActiveVideo(photo.media);

            return;
          }

          setActivePhotoIndex(photo.lightboxIndex);
        }}
      >
        <img
          src={photo.src}
          alt={photo.alt}
          loading="lazy"
          decoding="async"
          sizes={photo.responsiveSizes}
          className={imageClassName}
        />
        {photo.mediaType === 'video' ? (
          <span className="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-black/70 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
            <Play className="h-3 w-3 fill-current" />
            Video
          </span>
        ) : null}
      </button>
    );
  };

  return (
    <div
      data-testid="gallery-renderer"
      data-layout={layout}
      data-render-mode={renderMode}
      data-reduced-motion={String(shouldReduceMotion)}
      data-respect-reduced-motion={String(respectUserPreference)}
      className={className}
    >
      {renderMode === 'optimized' ? (
        <MediaVirtualFeed
          items={media}
          view="grid"
          loadMoreRef={loadMoreRef}
          renderItem={renderVirtualizedCard}
        />
      ) : layout === 'rows' ? (
        <RowsPhotoAlbum {...albumProps} targetRowHeight={260} />
      ) : layout === 'columns' ? (
        <ColumnsPhotoAlbum {...albumProps} columns={(containerWidth) => (containerWidth < 640 ? 2 : 3)} />
      ) : (
        <MasonryPhotoAlbum {...albumProps} columns={(containerWidth) => (containerWidth < 640 ? 2 : containerWidth < 1200 ? 3 : 4)} />
      )}

      <GalleryPhotoLightbox
        photos={lightboxPhotos}
        activeIndex={activePhotoIndex}
        onClose={() => setActivePhotoIndex(null)}
      />
      <GalleryVideoModal
        media={activeVideo}
        open={activeVideo !== null}
        onOpenChange={(open) => {
          if (!open) {
            setActiveVideo(null);
          }
        }}
      />
    </div>
  );
}

function normalizeLayout(layout?: string) {
  if (layout === 'rows' || layout === 'columns' || layout === 'masonry') {
    return layout;
  }

  return 'masonry';
}

function resolveWidth(item: ApiEventMediaItem) {
  const largest = item.responsive_sources?.variants[item.responsive_sources.variants.length - 1];

  return item.width || largest?.width || 1200;
}

function resolveHeight(item: ApiEventMediaItem) {
  const largest = item.responsive_sources?.variants[item.responsive_sources.variants.length - 1];

  return item.height || largest?.height || 800;
}

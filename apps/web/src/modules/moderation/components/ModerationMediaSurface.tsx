import { useEffect, useMemo, useState } from 'react';
import { ImageIcon, VideoOff } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import type { ApiEventMediaItem } from '@/lib/api-types';
import { cn } from '@/lib/utils';

import { isVideoAsset } from '../utils';

type ModerationSurfaceVariant = 'thumbnail' | 'preview';
type LoadState = 'loading' | 'loaded' | 'error';

interface ModerationMediaSurfaceProps {
  media: Pick<
    ApiEventMediaItem,
    | 'media_type'
    | 'mime_type'
    | 'thumbnail_url'
    | 'thumbnail_source'
    | 'preview_url'
    | 'preview_source'
    | 'moderation_thumbnail_url'
    | 'moderation_thumbnail_source'
    | 'moderation_preview_url'
    | 'moderation_preview_source'
    | 'caption'
    | 'event_title'
  >;
  variant?: ModerationSurfaceVariant;
  className?: string;
  mediaClassName?: string;
  fit?: 'cover' | 'contain';
  sizes?: string;
  videoPreload?: 'none' | 'metadata' | 'auto';
}

function hasDedicatedModerationProfile(media: ModerationMediaSurfaceProps['media']) {
  return media.moderation_thumbnail_url !== undefined
    || media.moderation_thumbnail_source !== undefined
    || media.moderation_preview_url !== undefined
    || media.moderation_preview_source !== undefined;
}

export function resolveModerationSurfaceAsset(
  media: ModerationMediaSurfaceProps['media'],
  variant: ModerationSurfaceVariant,
) {
  const useDedicatedProfile = hasDedicatedModerationProfile(media);
  const thumbnailUrl = useDedicatedProfile
    ? media.moderation_thumbnail_url ?? null
    : media.thumbnail_url ?? null;
  const thumbnailSource = useDedicatedProfile
    ? media.moderation_thumbnail_source ?? null
    : media.thumbnail_source ?? null;
  const previewUrl = useDedicatedProfile
    ? media.moderation_preview_url ?? null
    : media.preview_url ?? null;
  const previewSource = useDedicatedProfile
    ? media.moderation_preview_source ?? null
    : media.preview_source ?? null;
  const canPlayVideo = media.media_type === 'video' && isVideoAsset(media, previewUrl);

  if (canPlayVideo) {
    return {
      kind: 'video' as const,
      url: previewUrl,
      source: previewSource,
      posterUrl: thumbnailUrl,
    };
  }

  if (variant === 'preview' && previewUrl) {
    return {
      kind: 'image' as const,
      url: previewUrl,
      source: previewSource,
      posterUrl: null,
    };
  }

  if (thumbnailUrl) {
    return {
      kind: 'image' as const,
      url: thumbnailUrl,
      source: thumbnailSource,
      posterUrl: null,
    };
  }

  if (previewUrl) {
    return {
      kind: 'image' as const,
      url: previewUrl,
      source: previewSource,
      posterUrl: null,
    };
  }

  return {
    kind: 'image' as const,
    url: null,
    source: null,
    posterUrl: null,
  };
}

export function ModerationMediaSurface({
  media,
  variant = 'thumbnail',
  className,
  mediaClassName,
  fit = 'cover',
  sizes,
  videoPreload = 'metadata',
}: ModerationMediaSurfaceProps) {
  const asset = useMemo(() => resolveModerationSurfaceAsset(media, variant), [media, variant]);
  const [loadState, setLoadState] = useState<LoadState>(asset.url ? 'loading' : 'error');

  useEffect(() => {
    setLoadState(asset.url ? 'loading' : 'error');
  }, [asset.kind, asset.posterUrl, asset.source, asset.url]);

  const fitClassName = fit === 'contain' ? 'object-contain' : 'object-cover';
  const alt = media.caption || media.event_title || 'Midia do evento';
  const showFallback = loadState === 'error';
  const showLoading = loadState === 'loading' && !showFallback;

  return (
    <div className={cn('relative h-full w-full overflow-hidden bg-muted', className)}>
      {showLoading ? (
        <div
          data-testid="media-surface-loading"
          className="absolute inset-0 animate-pulse bg-gradient-to-br from-slate-200/80 via-slate-100 to-white dark:from-slate-900 dark:via-slate-800 dark:to-slate-700"
        />
      ) : null}

      {showFallback ? (
        <div
          data-testid="media-surface-fallback"
          className="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-200 via-slate-100 to-white px-4 text-center text-slate-500 dark:from-slate-900 dark:via-slate-800 dark:to-slate-700 dark:text-slate-300"
        >
          {media.media_type === 'video' ? <VideoOff className="h-8 w-8" /> : <ImageIcon className="h-8 w-8" />}
          <p className="text-sm font-medium">Preview indisponivel</p>
          <p className="text-xs text-muted-foreground">A superficie da moderacao nao conseguiu carregar esta midia agora.</p>
        </div>
      ) : asset.kind === 'video' ? (
        <video
          src={asset.url ?? undefined}
          poster={asset.posterUrl ?? undefined}
          className={cn(
            'h-full w-full transition-opacity duration-200',
            fitClassName,
            loadState === 'loaded' ? 'opacity-100' : 'opacity-0',
            mediaClassName,
          )}
          muted
          playsInline
          preload={videoPreload}
          onLoadedData={() => setLoadState('loaded')}
          onError={() => setLoadState('error')}
        />
      ) : (
        <img
          src={asset.url ?? undefined}
          alt={alt}
          className={cn(
            'h-full w-full transition-opacity duration-200',
            fitClassName,
            loadState === 'loaded' ? 'opacity-100' : 'opacity-0',
            mediaClassName,
          )}
          loading="lazy"
          decoding="async"
          sizes={sizes}
          onLoad={() => setLoadState('loaded')}
          onError={() => setLoadState('error')}
        />
      )}

      {asset.source === 'original' && !showFallback ? (
        <div className="pointer-events-none absolute inset-x-0 top-0 flex justify-end p-3">
          <Badge variant="outline" className="border-amber-300/70 bg-black/45 text-white backdrop-blur">
            Original
          </Badge>
        </div>
      ) : null}
    </div>
  );
}

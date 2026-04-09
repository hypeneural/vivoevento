import { useMemo } from 'react';
import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';
import type {
  WallPlayerStatus,
  WallRuntimeItem,
  WallVideoPlaybackFailureReason,
  WallVideoResumeMode,
} from '../types';
import WallVideoSurface from './WallVideoSurface';

type VideoMetricsPayload = {
  itemId: string;
  currentTime?: number;
  durationSeconds?: number | null;
  readyState?: number;
};

type VideoFailurePayload = VideoMetricsPayload & {
  exitReason: 'play_rejected' | 'stalled_timeout' | 'startup_timeout' | 'poster_then_skip' | 'startup_waiting_timeout' | 'startup_play_rejected';
  failureReason?: WallVideoPlaybackFailureReason | null;
};

export interface MediaSurfaceVideoControlProps {
  playerStatus: WallPlayerStatus;
  startupDeadlineMs: number;
  stallBudgetMs: number;
  resumeMode: WallVideoResumeMode;
  onStarting: (payload: VideoMetricsPayload) => void;
  onFirstFrame: (payload: VideoMetricsPayload) => void;
  onPlaybackReady: (payload: VideoMetricsPayload) => void;
  onPlaying: (payload: VideoMetricsPayload) => void;
  onProgress: (payload: VideoMetricsPayload) => void;
  onWaiting: (payload: VideoMetricsPayload) => void;
  onStalled: (payload: VideoMetricsPayload) => void;
  onEnded: (payload: VideoMetricsPayload) => void;
  onFailure: (payload: VideoFailurePayload) => void;
}

interface MediaSurfaceProps {
  media: WallRuntimeItem;
  fit?: 'contain' | 'cover';
  className?: string;
  imageClassName?: string;
  renderVideoPosterOnly?: boolean;
  videoControl?: MediaSurfaceVideoControlProps | null;
}

export function MediaSurface({
  media,
  fit = 'contain',
  className,
  imageClassName,
  renderVideoPosterOnly = false,
  videoControl = null,
}: MediaSurfaceProps) {
  const sharedClass = useMemo(
    () => cn('h-full w-full', fit === 'cover' ? 'object-cover' : 'object-contain', imageClassName),
    [fit, imageClassName],
  );

  if (media.type === 'video') {
    if (renderVideoPosterOnly) {
      return (
        <div className={cn('relative h-full w-full overflow-hidden bg-black/35', className)}>
          {media.preview_url ? (
            <img
              src={media.preview_url}
              alt=""
              className={sharedClass}
            />
          ) : (
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.12),rgba(0,0,0,0.82))]" />
          )}
        </div>
      );
    }

    return (
      <div className={cn('relative h-full w-full overflow-hidden', className)}>
        {videoControl ? (
          <WallVideoSurface
            itemId={media.id}
            src={media.url}
            poster={media.preview_url}
            className={sharedClass}
            playerStatus={videoControl.playerStatus}
            startupDeadlineMs={videoControl.startupDeadlineMs}
            stallBudgetMs={videoControl.stallBudgetMs}
            resumeMode={videoControl.resumeMode}
            onStarting={videoControl.onStarting}
            onFirstFrame={videoControl.onFirstFrame}
            onPlaybackReady={videoControl.onPlaybackReady}
            onPlaying={videoControl.onPlaying}
            onProgress={videoControl.onProgress}
            onWaiting={videoControl.onWaiting}
            onStalled={videoControl.onStalled}
            onEnded={videoControl.onEnded}
            onFailure={videoControl.onFailure}
          />
        ) : (
          <video
            key={media.id}
            src={media.url ?? undefined}
            className={sharedClass}
            autoPlay
            muted
            playsInline
            preload="auto"
          />
        )}
      </div>
    );
  }

  return (
    <div className={cn('relative h-full w-full overflow-hidden', className)}>
      <motion.img
        key={`${media.id}-${media.url}`}
        src={media.url ?? undefined}
        alt={media.sender_name || 'Foto do evento'}
        className={cn(sharedClass, 'will-change-[opacity,transform]')}
        initial={{ opacity: 0.18, scale: 0.992 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.42, ease: 'easeOut' }}
      />
    </div>
  );
}

export default MediaSurface;

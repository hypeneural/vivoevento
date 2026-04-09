import { useEffect, useMemo, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import { resolveEventVideoAttrs } from '../engine/autoplay';
import type {
  WallPlayerStatus,
  WallVideoPlaybackFailureReason,
  WallVideoResumeMode,
} from '../types';

const HAVE_FUTURE_DATA = 3;

type WallVideoSurfaceMetrics = {
  itemId: string;
  currentTime: number;
  durationSeconds: number | null;
  readyState: number;
};

type WallVideoSurfaceFailure = WallVideoSurfaceMetrics & {
  exitReason: 'play_rejected' | 'stalled_timeout' | 'startup_timeout' | 'poster_then_skip' | 'startup_waiting_timeout' | 'startup_play_rejected';
  failureReason?: WallVideoPlaybackFailureReason | null;
};

export interface WallVideoSurfaceProps {
  itemId: string;
  src: string | null;
  poster?: string | null;
  className?: string;
  playerStatus: WallPlayerStatus;
  startupDeadlineMs: number;
  stallBudgetMs: number;
  resumeMode: WallVideoResumeMode;
  onStarting: (payload: WallVideoSurfaceMetrics) => void;
  onFirstFrame: (payload: WallVideoSurfaceMetrics) => void;
  onPlaybackReady: (payload: WallVideoSurfaceMetrics) => void;
  onPlaying: (payload: WallVideoSurfaceMetrics) => void;
  onProgress: (payload: WallVideoSurfaceMetrics) => void;
  onWaiting: (payload: WallVideoSurfaceMetrics) => void;
  onStalled: (payload: WallVideoSurfaceMetrics) => void;
  onEnded: (payload: WallVideoSurfaceMetrics) => void;
  onFailure: (payload: WallVideoSurfaceFailure) => void;
}

function readMetrics(itemId: string, video: HTMLVideoElement | null): WallVideoSurfaceMetrics {
  return {
    itemId,
    currentTime: video?.currentTime ?? 0,
    durationSeconds: Number.isFinite(video?.duration) ? video?.duration : null,
    readyState: video?.readyState ?? 0,
  };
}

function classifyPlayError(error: unknown): WallVideoPlaybackFailureReason {
  if (error instanceof DOMException) {
    if (error.name === 'NotAllowedError') {
      return 'autoplay_blocked';
    }

    if (error.name === 'NotSupportedError') {
      return 'unsupported_format';
    }
  }

  return 'network_error';
}

function classifyVideoElementError(video: HTMLVideoElement | null): WallVideoPlaybackFailureReason {
  if (!video?.currentSrc) {
    return 'src_missing';
  }

  switch (video.error?.code) {
    case 4:
      return 'unsupported_format';
    case 2:
      return 'network_error';
    case 3:
      return 'decode_degraded';
    default:
      return 'network_error';
  }
}

export function WallVideoSurface({
  itemId,
  src,
  poster,
  className,
  playerStatus,
  startupDeadlineMs,
  stallBudgetMs,
  resumeMode,
  onStarting,
  onFirstFrame,
  onPlaybackReady,
  onPlaying,
  onProgress,
  onWaiting,
  onStalled,
  onEnded,
  onFailure,
}: WallVideoSurfaceProps) {
  const attrs = useMemo(() => resolveEventVideoAttrs(), []);
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const terminalRef = useRef(false);
  const firstFrameRef = useRef(false);
  const playbackReadyRef = useRef(false);
  const playingRef = useRef(false);
  const previousStatusRef = useRef<WallPlayerStatus>('booting');
  const waitingTimerRef = useRef<number | null>(null);
  const [showPoster, setShowPoster] = useState(Boolean(poster));
  const [startupResolved, setStartupResolved] = useState(false);

  const clearWaitingTimer = () => {
    if (waitingTimerRef.current != null) {
      window.clearTimeout(waitingTimerRef.current);
      waitingTimerRef.current = null;
    }
  };

  const fail = (payload: Omit<WallVideoSurfaceFailure, 'itemId' | 'currentTime' | 'durationSeconds' | 'readyState'>) => {
    if (terminalRef.current) {
      return;
    }

    terminalRef.current = true;
    clearWaitingTimer();

    const video = videoRef.current;
    try {
      video?.pause();
    } catch {
      // Ignore pause failures during teardown/fallback.
    }

    onFailure({
      ...readMetrics(itemId, video),
      ...payload,
    });
  };

  const promoteIfReady = () => {
    const video = videoRef.current;
    if (!video || terminalRef.current) {
      return;
    }

    if (video.readyState >= HAVE_FUTURE_DATA && !playbackReadyRef.current) {
      playbackReadyRef.current = true;
      setShowPoster(false);
      setStartupResolved(true);
      onPlaybackReady(readMetrics(itemId, video));
    }
  };

  const scheduleWaitingBudget = (exitReason: WallVideoSurfaceFailure['exitReason']) => {
    clearWaitingTimer();
    waitingTimerRef.current = window.setTimeout(() => {
      fail({
        exitReason,
        failureReason: 'network_error',
      });
    }, stallBudgetMs);
  };

  useEffect(() => {
    terminalRef.current = false;
    firstFrameRef.current = false;
    playbackReadyRef.current = false;
    playingRef.current = false;
    previousStatusRef.current = 'booting';
    clearWaitingTimer();
    setShowPoster(Boolean(poster));
    setStartupResolved(false);
  }, [itemId, poster, src]);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) {
      return;
    }

    if (playerStatus === 'paused') {
      clearWaitingTimer();
      try {
        video.pause();
      } catch {
        // Ignore pause failures on detached media.
      }
      previousStatusRef.current = 'paused';
      return;
    }

    if (playerStatus !== 'playing') {
      previousStatusRef.current = playerStatus;
      return;
    }

    if (!src) {
      fail({
        exitReason: 'startup_play_rejected',
        failureReason: 'src_missing',
      });
      return;
    }

    if (previousStatusRef.current === 'paused' && resumeMode === 'restart_from_zero') {
      video.currentTime = 0;
    }

    previousStatusRef.current = 'playing';
    onStarting(readMetrics(itemId, video));

    void video.play().catch((error: unknown) => {
      fail({
        exitReason: firstFrameRef.current ? 'play_rejected' : 'startup_play_rejected',
        failureReason: classifyPlayError(error),
      });
    });
  }, [fail, itemId, onStarting, playerStatus, resumeMode, src]);

  useEffect(() => {
    if (playerStatus !== 'playing' || terminalRef.current || startupResolved) {
      return;
    }

    const timeout = window.setTimeout(() => {
      fail({
        exitReason: poster ? 'poster_then_skip' : 'startup_timeout',
        failureReason: 'network_error',
      });
    }, startupDeadlineMs);

    return () => {
      window.clearTimeout(timeout);
    };
  }, [fail, playerStatus, poster, startupDeadlineMs, startupResolved]);

  useEffect(() => () => {
    clearWaitingTimer();
  }, []);

  return (
    <>
      {poster && showPoster ? (
        <img
          src={poster}
          alt=""
          className={cn(className, 'absolute inset-0 h-full w-full object-cover')}
        />
      ) : null}
      <video
        ref={videoRef}
        key={itemId}
        src={src ?? undefined}
        poster={poster ?? undefined}
        className={cn(
          className,
          showPoster && poster ? 'opacity-0' : 'opacity-100',
          'transition-opacity duration-200',
        )}
        autoPlay={attrs.autoPlay}
        muted={attrs.muted}
        playsInline={attrs.playsInline}
        loop={attrs.loop}
        preload={attrs.preload}
        onLoadedData={() => {
          const video = videoRef.current;
          if (!video || terminalRef.current) {
            return;
          }

          if (!firstFrameRef.current) {
            firstFrameRef.current = true;
            onFirstFrame(readMetrics(itemId, video));
          }

          promoteIfReady();
        }}
        onCanPlay={() => {
          promoteIfReady();
          clearWaitingTimer();
        }}
        onPlaying={() => {
          const video = videoRef.current;
          if (!video || terminalRef.current) {
            return;
          }

          promoteIfReady();
          clearWaitingTimer();

          if (!playingRef.current) {
            playingRef.current = true;
            setShowPoster(false);
            setStartupResolved(true);
            onPlaying(readMetrics(itemId, video));
          }
        }}
        onTimeUpdate={() => {
          const video = videoRef.current;
          if (!video || terminalRef.current) {
            return;
          }

          promoteIfReady();
          if (video.readyState >= HAVE_FUTURE_DATA) {
            clearWaitingTimer();
          }
          onProgress(readMetrics(itemId, video));
        }}
        onWaiting={() => {
          const video = videoRef.current;
          if (!video || terminalRef.current) {
            return;
          }

          onWaiting(readMetrics(itemId, video));
          scheduleWaitingBudget(firstFrameRef.current ? 'stalled_timeout' : 'startup_waiting_timeout');
        }}
        onStalled={() => {
          const video = videoRef.current;
          if (!video || terminalRef.current) {
            return;
          }

          onStalled(readMetrics(itemId, video));
          scheduleWaitingBudget(firstFrameRef.current ? 'stalled_timeout' : 'startup_waiting_timeout');
        }}
        onEnded={() => {
          if (terminalRef.current) {
            return;
          }

          terminalRef.current = true;
          clearWaitingTimer();
          onEnded(readMetrics(itemId, videoRef.current));
        }}
        onError={() => {
          fail({
            exitReason: firstFrameRef.current ? 'stalled_timeout' : 'startup_play_rejected',
            failureReason: classifyVideoElementError(videoRef.current),
          });
        }}
      />
    </>
  );
}

export default WallVideoSurface;

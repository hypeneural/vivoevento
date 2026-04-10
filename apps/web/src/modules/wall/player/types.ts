import type {
  WallAcceptedOrientation,
  WallAdItem,
  WallAdMode,
  WallBootData,
  WallEventSummary,
  WallExpiredPayload,
  WallHeartbeatPayload,
  WallLayout,
  WallMediaDeletedPayload,
  WallMediaItem,
  WallMediaType,
  WallPlayerCommandPayload,
  WallPersistentStorage,
  WallPublicStatus,
  WallSelectionMode,
  WallSelectionModeOption,
  WallSelectionPolicy,
  WallSettings,
  WallStateData,
  WallStatusChangedPayload,
  WallTransition,
} from '@eventovivo/shared-types/wall';

export type {
  WallAcceptedOrientation,
  WallAdItem,
  WallAdMode,
  WallBootData,
  WallEventSummary,
  WallExpiredPayload,
  WallHeartbeatPayload,
  WallLayout,
  WallMediaDeletedPayload,
  WallMediaItem,
  WallMediaType,
  WallPlayerCommandPayload,
  WallPersistentStorage,
  WallSelectionMode,
  WallSelectionModeOption,
  WallSelectionPolicy,
  WallSettings,
  WallStateData,
  WallStatusChangedPayload,
  WallTransition,
};

export type WallStatus = WallPublicStatus;

export type WallConnectionStatus =
  | 'idle'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'disconnected'
  | 'error';

export type WallPlayerStatus =
  | 'booting'
  | 'idle'
  | 'playing'
  | 'paused'
  | 'stopped'
  | 'expired'
  | 'error';

export type MediaOrientation = 'vertical' | 'horizontal' | 'squareish';

export type WallAssetStatus = 'idle' | 'loading' | 'ready' | 'stale' | 'error';
export type WallLayoutKind = 'single' | 'board';
export type WallReducedMotionSetting = 'always' | 'never' | 'user';
export type WallPerformanceTier = 'performance' | 'premium' | 'preview';

export type WallVideoPlaybackPhase =
  | 'idle'
  | 'probing'
  | 'primed'
  | 'starting'
  | 'playing'
  | 'waiting'
  | 'stalled'
  | 'paused_by_wall'
  | 'completed'
  | 'capped'
  | 'interrupted'
  | 'failed_to_start';

export type WallVideoPlaybackFailureReason =
  | 'network_error'
  | 'unsupported_format'
  | 'autoplay_blocked'
  | 'decode_degraded'
  | 'src_missing'
  | 'variant_missing';

export type WallVideoPlaybackExitReason =
  | 'ended'
  | 'cap_reached'
  | 'paused_by_operator'
  | 'play_rejected'
  | 'stalled_timeout'
  | 'replaced_by_command'
  | 'media_deleted'
  | 'visibility_degraded'
  | 'startup_timeout'
  | 'poster_then_skip'
  | 'startup_waiting_timeout'
  | 'startup_play_rejected';

export type WallVideoResumeMode =
  | 'resume_if_same_item'
  | 'restart_from_zero'
  | 'resume_if_same_item_else_restart';

export interface WallVideoPlaybackState {
  itemId: string | null;
  phase: WallVideoPlaybackPhase;
  currentTime: number;
  durationSeconds: number | null;
  readyState: number;
  exitReason: WallVideoPlaybackExitReason | null;
  failureReason: WallVideoPlaybackFailureReason | null;
  stallCount: number;
  posterVisible: boolean;
  firstFrameReady: boolean;
  playbackReady: boolean;
  playingConfirmed: boolean;
  startupDegraded: boolean;
  playbackStartedAt?: string | null;
  lastItemId?: string | null;
  lastExitReason?: WallVideoPlaybackExitReason | null;
  lastFailureReason?: WallVideoPlaybackFailureReason | null;
}

export interface WallRuntimeItem extends WallMediaItem {
  senderKey: string;
  duplicateClusterKey?: string | null;
  assetStatus: WallAssetStatus;
  playedAt?: string | null;
  playCount: number;
  lastError?: string | null;
  orientation?: MediaOrientation | null;
  width?: number | null;
  height?: number | null;
}

export interface WallSenderRuntimeStats {
  lastPlayedAt?: string | null;
  recentPlayTimestamps: string[];
  totalPlayCount: number;
}

export interface WallPlayerState {
  code: string;
  status: WallPlayerStatus;
  event: WallEventSummary | null;
  settings: WallSettings | null;
  items: WallRuntimeItem[];
  ads: WallAdItem[];
  currentAd: WallAdItem | null;
  adBaseItemId?: string | null;
  adScheduler: {
    mode: WallAdMode;
    frequency: number;
    photosSinceLastAd: number;
    lastAdPlayedAt: number | null;
    lastAdIndex: number;
    skipNextAdCheck: boolean;
  };
  senderStats: Record<string, WallSenderRuntimeStats>;
  currentIndex: number;
  currentItemId?: string | null;
  currentItemStartedAt?: string | null;
  videoPlayback: WallVideoPlaybackState;
}

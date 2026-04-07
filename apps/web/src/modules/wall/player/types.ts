import type {
  WallAcceptedOrientation,
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
  senderStats: Record<string, WallSenderRuntimeStats>;
  currentIndex: number;
  currentItemId?: string | null;
}

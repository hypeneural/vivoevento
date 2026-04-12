export const EVENT_OPERATIONS_SCHEMA_VERSION = 1 as const;

export const EVENT_OPERATIONS_EVENT_NAMES = {
  stationDelta: 'operations.station.delta',
  timelineAppended: 'operations.timeline.appended',
  alertCreated: 'operations.alert.created',
  healthChanged: 'operations.health.changed',
  snapshotBoot: 'operations.snapshot.boot',
} as const;

export const EVENT_OPERATIONS_STATION_KEYS = [
  'intake',
  'download',
  'variants',
  'safety',
  'intelligence',
  'human_review',
  'gallery',
  'wall',
  'feedback',
  'alerts',
] as const;

export const EVENT_OPERATIONS_EVENT_KEYS = [
  'station.load.changed',
  'station.alert.raised',
  'media.card.arrived',
  'station.throughput.spike',
  'media.download.started',
  'media.download.completed',
  'media.variants.generated',
  'media.safety.review_requested',
  'media.safety.blocked',
  'media.moderation.pending',
  'media.moderation.approved',
  'media.moderation.rejected',
  'media.published.gallery',
  'media.published.wall',
  'feedback.sent',
  'wall.health.changed',
  'operator.presence.changed',
] as const;

export const EVENT_OPERATIONS_VISUAL_ROLES = [
  'coordinator',
  'dispatcher',
  'runner',
  'reviewer',
  'operator',
  'triage',
] as const;

export type EventOperationsEventName =
  (typeof EVENT_OPERATIONS_EVENT_NAMES)[keyof typeof EVENT_OPERATIONS_EVENT_NAMES];
export type EventOperationsStationKey = (typeof EVENT_OPERATIONS_STATION_KEYS)[number];
export type EventOperationsEventKey = (typeof EVENT_OPERATIONS_EVENT_KEYS)[number];
export type EventOperationsVisualRole = (typeof EVENT_OPERATIONS_VISUAL_ROLES)[number];

export type EventOperationsHealthStatus = 'healthy' | 'attention' | 'risk' | 'offline';
export type EventOperationsConnectionStatus = 'connecting' | 'connected' | 'resyncing' | 'degraded' | 'offline';
export type EventOperationsSeverity = 'info' | 'warning' | 'critical';
export type EventOperationsUrgency = 'low' | 'normal' | 'high' | 'critical';
export type EventOperationsRenderGroup = 'intake' | 'processing' | 'review' | 'publishing' | 'wall' | 'system';
export type EventOperationsAnimationHint =
  | 'none'
  | 'intake_pulse'
  | 'download_active'
  | 'variants_active'
  | 'safety_scan'
  | 'review_backlog'
  | 'gallery_publish'
  | 'wall_health'
  | 'feedback_sent'
  | 'critical_alert';
export type EventOperationsBroadcastPriority = 'critical_immediate' | 'operational_normal' | 'timeline_coalescible';

export interface EventOperationsVersionContract {
  schema_version: typeof EVENT_OPERATIONS_SCHEMA_VERSION;
  snapshot_version: number;
  timeline_cursor: string | null;
  event_sequence: number;
  server_time: string;
}

export interface EventOperationsEventSummary {
  id: number;
  title: string;
  slug: string;
  status: 'draft' | 'live' | 'paused' | 'ended' | 'archived';
  timezone?: string | null;
}

export interface EventOperationsHealthSummary {
  status: EventOperationsHealthStatus;
  dominant_station_key?: EventOperationsStationKey | null;
  summary: string;
  updated_at: string;
}

export interface EventOperationsConnectionSummary {
  status: EventOperationsConnectionStatus;
  realtime_connected: boolean;
  last_connected_at?: string | null;
  last_resync_at?: string | null;
  degraded_reason?: string | null;
}

export interface EventOperationsTimelineEntry {
  id: string;
  event_sequence: number;
  station_key: EventOperationsStationKey;
  event_key: EventOperationsEventKey;
  severity: EventOperationsSeverity;
  urgency: EventOperationsUrgency;
  title: string;
  summary: string;
  occurred_at: string;
  correlation_key?: string | null;
  event_media_id?: number | null;
  inbound_message_id?: number | null;
  render_group?: EventOperationsRenderGroup | null;
  animation_hint?: EventOperationsAnimationHint | null;
}

export interface EventOperationsRecentItem {
  id: string;
  event_sequence: number;
  title: string;
  summary?: string | null;
  occurred_at: string;
  event_media_id?: number | null;
  preview_url?: string | null;
  media_type?: 'image' | 'video' | null;
}

export interface EventOperationsStationState {
  station_key: EventOperationsStationKey;
  label: string;
  health: EventOperationsHealthStatus;
  backlog_count: number;
  queue_depth: number;
  station_load: number;
  throughput_per_minute: number;
  recent_items: EventOperationsRecentItem[];
  animation_hint: EventOperationsAnimationHint;
  render_group: EventOperationsRenderGroup;
  dominant_reason?: string | null;
  updated_at: string;
}

export interface EventOperationsAlert {
  id: string;
  severity: EventOperationsSeverity;
  urgency: EventOperationsUrgency;
  station_key: EventOperationsStationKey;
  title: string;
  summary: string;
  occurred_at: string;
  acknowledged_at?: string | null;
}

export interface EventOperationsWallSummary {
  health: EventOperationsHealthStatus;
  online_players: number;
  degraded_players: number;
  offline_players: number;
  current_item_id?: string | null;
  next_item_id?: string | null;
  confidence: 'high' | 'medium' | 'low' | 'unknown';
}

export interface EventOperationsCounters {
  backlog_total: number;
  human_review_pending: number;
  processing_failures: number;
  intake_per_minute: number;
  published_gallery_total: number;
  published_wall_total: number;
}

export interface EventOperationsRoomSnapshot extends EventOperationsVersionContract {
  event: EventOperationsEventSummary;
  health: EventOperationsHealthSummary;
  connection: EventOperationsConnectionSummary;
  counters: EventOperationsCounters;
  stations: EventOperationsStationState[];
  alerts: EventOperationsAlert[];
  wall: EventOperationsWallSummary;
  timeline: EventOperationsTimelineEntry[];
}

export type EventOperationsDeltaKind =
  | 'station.delta'
  | 'timeline.appended'
  | 'alert.created'
  | 'health.changed'
  | 'snapshot.boot';

export interface EventOperationsStationDelta {
  station_key: EventOperationsStationKey;
  patch: Partial<
    Pick<
      EventOperationsStationState,
      | 'health'
      | 'backlog_count'
      | 'queue_depth'
      | 'station_load'
      | 'throughput_per_minute'
      | 'recent_items'
      | 'animation_hint'
      | 'dominant_reason'
      | 'updated_at'
    >
  >;
}

export interface EventOperationsDelta extends EventOperationsVersionContract {
  kind: EventOperationsDeltaKind;
  broadcast_priority: EventOperationsBroadcastPriority;
  station_delta?: EventOperationsStationDelta | null;
  timeline_entry?: EventOperationsTimelineEntry | null;
  alert?: EventOperationsAlert | null;
  health?: EventOperationsHealthSummary | null;
  snapshot?: EventOperationsRoomSnapshot | null;
  resync_required?: boolean;
}

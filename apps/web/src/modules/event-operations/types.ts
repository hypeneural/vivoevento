import type {
  ApiEventMediaItem,
  ApiWallDiagnosticsResponse,
  ApiWallLiveSnapshotResponse,
  ModerationStatsMeta,
} from '@/lib/api-types';
import type { EventJourneyProjection } from '@/modules/events/journey/types';

import type { EventOperationsRoomSnapshot } from '@eventovivo/shared-types/event-operations';

export interface EventOperationsPipelineMetricDistribution {
  count: number;
  avg: number | null;
  p50: number | null;
  p95: number | null;
}

export interface EventOperationsPipelineMetrics {
  event: {
    id: number;
    title: string;
  };
  summary: {
    media_total: number;
    approved_total: number;
    pending_total: number;
    rejected_total: number;
    published_total: number;
    blocked_total: number;
    review_total: number;
  };
  sla: {
    upload_to_publish_seconds: EventOperationsPipelineMetricDistribution;
    inbound_to_publish_seconds: EventOperationsPipelineMetricDistribution;
    upload_to_first_update_seconds: EventOperationsPipelineMetricDistribution;
    upload_to_face_index_seconds: EventOperationsPipelineMetricDistribution;
  };
  queues: {
    backlog: Array<{
      queue_name: string;
      processing_runs: number;
    }>;
  };
  failures: Array<{
    stage_key: string;
    failure_class: string;
    count: number;
  }>;
}

export interface EventOperationsAuditTimelineItem {
  id: string;
  kind: string;
  label: string;
  actor_name: string;
  created_at: string | null;
}

export interface EventOperationsModerationFeedPage {
  data: ApiEventMediaItem[];
  meta: {
    per_page: number;
    next_cursor: string | null;
    prev_cursor: string | null;
    has_more: boolean;
    request_id: string;
    stats: null;
  };
}

export interface EventOperationsBootSources {
  journey: EventJourneyProjection;
  pipeline_metrics: EventOperationsPipelineMetrics;
  moderation_stats: ModerationStatsMeta;
  moderation_feed: EventOperationsModerationFeedPage;
  timeline: EventOperationsAuditTimelineItem[];
  wall_live_snapshot: ApiWallLiveSnapshotResponse;
  wall_diagnostics: ApiWallDiagnosticsResponse;
}

export interface EventOperationsV0Room extends EventOperationsRoomSnapshot {
  v0: {
    mode: 'read_only';
    journey_summary_text: string;
    active_entry_channels: string[];
    destinations: {
      gallery: boolean;
      wall: boolean;
      print: boolean;
    };
    dominant_station_reason: string | null;
  };
}

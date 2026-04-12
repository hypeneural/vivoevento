import { api } from '@/lib/api';
import type {
  ApiEventMediaItem,
  ApiWallDiagnosticsResponse,
  ApiWallLiveSnapshotResponse,
  ModerationStatsMeta,
} from '@/lib/api-types';
import { getEventJourneyBuilder } from '@/modules/events/journey/api';

import {
  EVENT_OPERATIONS_SCHEMA_VERSION,
  type EventOperationsAlert,
  type EventOperationsAnimationHint,
  type EventOperationsConnectionSummary,
  type EventOperationsEventKey,
  type EventOperationsHealthStatus,
  type EventOperationsRecentItem,
  type EventOperationsRenderGroup,
  type EventOperationsRoomSnapshot,
  type EventOperationsStationKey,
  type EventOperationsStationState,
  type EventOperationsTimelineEntry,
  type EventOperationsWallSummary,
} from '@eventovivo/shared-types/event-operations';

import type {
  EventOperationsAuditTimelineItem,
  EventOperationsBootSources,
  EventOperationsModerationFeedPage,
  EventOperationsPipelineMetrics,
  EventOperationsV0Room,
} from './types';

const STATION_LABELS: Record<EventOperationsStationKey, string> = {
  intake: 'Recepcao',
  download: 'Download / Arquivo',
  variants: 'Laboratorio / Variantes',
  safety: 'Safety AI',
  intelligence: 'IA de contexto',
  human_review: 'Moderacao humana',
  gallery: 'Galeria',
  wall: 'Telao',
  feedback: 'Feedback',
  alerts: 'Alertas',
};

const STATION_RENDER_GROUPS: Record<EventOperationsStationKey, EventOperationsRenderGroup> = {
  intake: 'intake',
  download: 'processing',
  variants: 'processing',
  safety: 'review',
  intelligence: 'review',
  human_review: 'review',
  gallery: 'publishing',
  wall: 'wall',
  feedback: 'publishing',
  alerts: 'system',
};

const STATION_HINTS: Record<EventOperationsStationKey, EventOperationsAnimationHint> = {
  intake: 'intake_pulse',
  download: 'download_active',
  variants: 'variants_active',
  safety: 'safety_scan',
  intelligence: 'safety_scan',
  human_review: 'review_backlog',
  gallery: 'gallery_publish',
  wall: 'wall_health',
  feedback: 'feedback_sent',
  alerts: 'critical_alert',
};

function normalizeStationLoad(value: number, ceiling: number): number {
  if (ceiling <= 0) {
    return 0.1;
  }

  return Math.max(0.1, Math.min(1, value / ceiling));
}

function mapWallHealthStatus(status: ApiWallDiagnosticsResponse['summary']['health_status']): EventOperationsHealthStatus {
  switch (status) {
    case 'healthy':
      return 'healthy';
    case 'degraded':
      return 'attention';
    case 'offline':
      return 'risk';
    default:
      return 'attention';
  }
}

function mapStationFromTimeline(activity: EventOperationsAuditTimelineItem): EventOperationsStationKey {
  const haystack = `${activity.kind} ${activity.label}`.toLowerCase();

  if (haystack.includes('wall') || haystack.includes('telao') || haystack.includes('diagnostic')) {
    return 'wall';
  }

  if (haystack.includes('moderation') || haystack.includes('modera')) {
    return 'human_review';
  }

  if (haystack.includes('gallery') || haystack.includes('galeria')) {
    return 'gallery';
  }

  if (haystack.includes('whatsapp') || haystack.includes('telegram') || haystack.includes('upload')) {
    return 'intake';
  }

  return 'alerts';
}

function mapEventKey(activity: EventOperationsAuditTimelineItem, stationKey: EventOperationsStationKey): EventOperationsEventKey {
  const haystack = `${activity.kind} ${activity.label}`.toLowerCase();

  if (haystack.includes('wall') && (haystack.includes('diagnostic') || haystack.includes('health'))) {
    return 'wall.health.changed';
  }

  if (haystack.includes('publish') || haystack.includes('publicad')) {
    return stationKey === 'wall' ? 'media.published.wall' : 'media.published.gallery';
  }

  if (haystack.includes('rejeit') || haystack.includes('reject')) {
    return 'media.moderation.rejected';
  }

  if (haystack.includes('aprov') || haystack.includes('approve')) {
    return 'media.moderation.approved';
  }

  if (haystack.includes('moderation') || haystack.includes('modera')) {
    return 'media.moderation.pending';
  }

  if (stationKey === 'intake') {
    return 'media.card.arrived';
  }

  return 'station.load.changed';
}

function mapSeverity(activity: EventOperationsAuditTimelineItem): EventOperationsTimelineEntry['severity'] {
  const haystack = `${activity.kind} ${activity.label}`.toLowerCase();

  if (haystack.includes('error') || haystack.includes('falh') || haystack.includes('offline')) {
    return 'critical';
  }

  if (haystack.includes('review') || haystack.includes('warning') || haystack.includes('pend')) {
    return 'warning';
  }

  return 'info';
}

function mapUrgency(severity: EventOperationsTimelineEntry['severity']): EventOperationsTimelineEntry['urgency'] {
  switch (severity) {
    case 'critical':
      return 'critical';
    case 'warning':
      return 'high';
    default:
      return 'normal';
  }
}

function buildTimelineEntries(timeline: EventOperationsAuditTimelineItem[]): EventOperationsTimelineEntry[] {
  const ordered = [...timeline]
    .filter((item) => item.created_at)
    .sort((left, right) => String(left.created_at).localeCompare(String(right.created_at)));

  return ordered.map((activity, index) => {
    const stationKey = mapStationFromTimeline(activity);
    const severity = mapSeverity(activity);

    return {
      id: activity.id,
      event_sequence: index + 1,
      station_key: stationKey,
      event_key: mapEventKey(activity, stationKey),
      severity,
      urgency: mapUrgency(severity),
      title: activity.label,
      summary: `${activity.actor_name}: ${activity.label}`,
      occurred_at: activity.created_at ?? new Date().toISOString(),
      correlation_key: activity.id,
      render_group: STATION_RENDER_GROUPS[stationKey],
      animation_hint: STATION_HINTS[stationKey],
    };
  });
}

function buildRecentItem(media: ApiEventMediaItem): EventOperationsRecentItem {
  return {
    id: `media_${media.id}`,
    event_sequence: media.id,
    title: media.caption || media.client_filename || media.original_filename || `Midia ${media.id}`,
    summary: media.sender_name,
    occurred_at: media.created_at ?? new Date().toISOString(),
    event_media_id: media.id,
    preview_url: media.preview_url ?? media.thumbnail_url ?? null,
    media_type: media.media_type === 'video' ? 'video' : 'image',
  };
}

function buildWallSummary(
  liveSnapshot: ApiWallLiveSnapshotResponse,
  diagnostics: ApiWallDiagnosticsResponse,
): EventOperationsWallSummary {
  const health = mapWallHealthStatus(diagnostics.summary.health_status);

  return {
    health,
    online_players: diagnostics.summary.online_players,
    degraded_players: diagnostics.summary.degraded_players,
    offline_players: diagnostics.summary.offline_players,
    current_item_id: liveSnapshot.currentItem?.id ?? null,
    next_item_id: liveSnapshot.nextItem?.id ?? null,
    confidence:
      diagnostics.summary.offline_players > 0
        ? 'low'
        : liveSnapshot.nextItem
          ? 'high'
          : liveSnapshot.currentItem
            ? 'medium'
            : 'unknown',
  };
}

function buildConnectionSummary(serverTime: string): EventOperationsConnectionSummary {
  return {
    status: 'connected',
    realtime_connected: false,
    last_connected_at: serverTime,
    last_resync_at: null,
    degraded_reason: 'polling_only_v0',
  };
}

function buildAlerts(sources: EventOperationsBootSources, occurredAt: string): EventOperationsAlert[] {
  const alerts: EventOperationsAlert[] = [];
  const diagnostics = sources.wall_diagnostics.summary;
  const failureCount = sources.pipeline_metrics.failures.reduce((total, item) => total + item.count, 0);

  if (diagnostics.offline_players > 0) {
    alerts.push({
      id: 'v0_wall_offline',
      severity: 'critical',
      urgency: 'critical',
      station_key: 'wall',
      title: 'Player do telao offline',
      summary: `${diagnostics.offline_players} player(s) offline no wall.`,
      occurred_at: diagnostics.updated_at ?? occurredAt,
    });
  }

  if (sources.moderation_stats.pending > 0) {
    alerts.push({
      id: 'v0_human_review_backlog',
      severity: 'warning',
      urgency: 'high',
      station_key: 'human_review',
      title: 'Fila humana crescendo',
      summary: `${sources.moderation_stats.pending} midias aguardam moderacao humana.`,
      occurred_at: occurredAt,
    });
  }

  if (failureCount > 0) {
    alerts.push({
      id: 'v0_pipeline_failures',
      severity: 'warning',
      urgency: 'high',
      station_key: 'variants',
      title: 'Falhas recentes no pipeline',
      summary: `${failureCount} falha(s) recente(s) nas etapas tecnicas.`,
      occurred_at: occurredAt,
    });
  }

  return alerts;
}

function buildStations(
  sources: EventOperationsBootSources,
  serverTime: string,
  wallSummary: EventOperationsWallSummary,
  alerts: EventOperationsAlert[],
  timeline: EventOperationsTimelineEntry[],
): EventOperationsStationState[] {
  const backlogTotal = sources.pipeline_metrics.queues.backlog.reduce(
    (total, queue) => total + queue.processing_runs,
    0,
  );
  const variantsBacklog = sources.pipeline_metrics.queues.backlog
    .filter((queue) => queue.queue_name.includes('variant'))
    .reduce((total, queue) => total + queue.processing_runs, 0);
  const variantFailures = sources.pipeline_metrics.failures
    .filter((failure) => failure.stage_key === 'variants')
    .reduce((total, failure) => total + failure.count, 0);
  const intelligenceFailures = sources.pipeline_metrics.failures
    .filter((failure) => failure.stage_key === 'vlm')
    .reduce((total, failure) => total + failure.count, 0);
  const intakeRecentItems = sources.moderation_feed.data.slice(0, 3).map(buildRecentItem);
  const galleryRecentItems = timeline
    .filter((entry) => entry.station_key === 'gallery')
    .slice(-3)
    .map((entry) => ({
      id: entry.id,
      event_sequence: entry.event_sequence,
      title: entry.title,
      summary: entry.summary,
      occurred_at: entry.occurred_at,
      event_media_id: entry.event_media_id ?? null,
      preview_url: null,
      media_type: null,
    }));

  const wallRecentItems = [sources.wall_live_snapshot.currentItem, sources.wall_live_snapshot.nextItem]
    .filter(Boolean)
    .map((item) => ({
      id: item!.id,
      event_sequence: Number(item!.id.replace(/\D+/g, '')) || 0,
      title: item!.caption || item!.senderName || item!.id,
      summary: item!.senderName || item!.source,
      occurred_at: item!.createdAt ?? serverTime,
      preview_url: item!.previewUrl,
      media_type: item!.isVideo ? 'video' as const : 'image' as const,
    }));

  const alertRecentItems = alerts.map((alert, index) => ({
    id: alert.id,
    event_sequence: 9000 + index,
    title: alert.title,
    summary: alert.summary,
    occurred_at: alert.occurred_at,
    preview_url: null,
    media_type: null,
  }));

  const stations: Array<EventOperationsStationState> = [
    {
      station_key: 'intake',
      label: STATION_LABELS.intake,
      health: intakeRecentItems.length > 0 ? 'healthy' : 'attention',
      backlog_count: 0,
      queue_depth: 0,
      station_load: normalizeStationLoad(intakeRecentItems.length, 6),
      throughput_per_minute: intakeRecentItems.length,
      recent_items: intakeRecentItems,
      animation_hint: STATION_HINTS.intake,
      render_group: STATION_RENDER_GROUPS.intake,
      dominant_reason: intakeRecentItems.length > 0 ? 'Entradas recentes detectadas no evento.' : 'Sem entradas recentes.',
      updated_at: serverTime,
    },
    {
      station_key: 'download',
      label: STATION_LABELS.download,
      health: backlogTotal > 0 ? 'attention' : 'healthy',
      backlog_count: backlogTotal,
      queue_depth: backlogTotal,
      station_load: normalizeStationLoad(backlogTotal, 12),
      throughput_per_minute: 0,
      recent_items: [],
      animation_hint: STATION_HINTS.download,
      render_group: STATION_RENDER_GROUPS.download,
      dominant_reason: backlogTotal > 0 ? 'Runs tecnicos ainda em processamento.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'variants',
      label: STATION_LABELS.variants,
      health: variantFailures > 0 ? 'attention' : variantsBacklog > 0 ? 'attention' : 'healthy',
      backlog_count: variantsBacklog,
      queue_depth: variantsBacklog,
      station_load: normalizeStationLoad(variantsBacklog + variantFailures, 8),
      throughput_per_minute: 0,
      recent_items: [],
      animation_hint: STATION_HINTS.variants,
      render_group: STATION_RENDER_GROUPS.variants,
      dominant_reason: variantFailures > 0 ? 'Falhas recentes na geracao de variantes.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'safety',
      label: STATION_LABELS.safety,
      health: sources.pipeline_metrics.summary.review_total > 0 ? 'attention' : 'healthy',
      backlog_count: sources.pipeline_metrics.summary.review_total,
      queue_depth: sources.pipeline_metrics.summary.review_total,
      station_load: normalizeStationLoad(sources.pipeline_metrics.summary.review_total, 12),
      throughput_per_minute: 0,
      recent_items: sources.moderation_feed.data
        .filter((item) => item.safety_status === 'review' || item.safety_status === 'block')
        .slice(0, 3)
        .map(buildRecentItem),
      animation_hint: STATION_HINTS.safety,
      render_group: STATION_RENDER_GROUPS.safety,
      dominant_reason: sources.pipeline_metrics.summary.review_total > 0 ? 'Safety empurrou midias para revisao.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'intelligence',
      label: STATION_LABELS.intelligence,
      health: intelligenceFailures > 0 ? 'attention' : 'healthy',
      backlog_count: intelligenceFailures,
      queue_depth: intelligenceFailures,
      station_load: normalizeStationLoad(intelligenceFailures, 4),
      throughput_per_minute: 0,
      recent_items: sources.moderation_feed.data
        .filter((item) => item.vlm_status === 'review' || item.context_decision === 'review')
        .slice(0, 3)
        .map(buildRecentItem),
      animation_hint: STATION_HINTS.intelligence,
      render_group: STATION_RENDER_GROUPS.intelligence,
      dominant_reason: intelligenceFailures > 0 ? 'Falhas recentes na etapa de contexto.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'human_review',
      label: STATION_LABELS.human_review,
      health: sources.moderation_stats.pending > 0 ? 'attention' : 'healthy',
      backlog_count: sources.moderation_stats.pending,
      queue_depth: sources.moderation_stats.pending,
      station_load: normalizeStationLoad(sources.moderation_stats.pending, 18),
      throughput_per_minute: sources.moderation_feed.data.length,
      recent_items: sources.moderation_feed.data.slice(0, 4).map(buildRecentItem),
      animation_hint: STATION_HINTS.human_review,
      render_group: STATION_RENDER_GROUPS.human_review,
      dominant_reason: sources.moderation_stats.pending > 0 ? 'Fila humana pendente na moderacao.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'gallery',
      label: STATION_LABELS.gallery,
      health: 'healthy',
      backlog_count: 0,
      queue_depth: 0,
      station_load: normalizeStationLoad(galleryRecentItems.length, 4),
      throughput_per_minute: galleryRecentItems.length,
      recent_items: galleryRecentItems,
      animation_hint: STATION_HINTS.gallery,
      render_group: STATION_RENDER_GROUPS.gallery,
      dominant_reason: galleryRecentItems.length > 0 ? 'Publicacoes recentes na galeria.' : null,
      updated_at: serverTime,
    },
    {
      station_key: 'wall',
      label: STATION_LABELS.wall,
      health: wallSummary.health,
      backlog_count: wallSummary.offline_players + wallSummary.degraded_players,
      queue_depth: 0,
      station_load: normalizeStationLoad(
        wallSummary.offline_players + wallSummary.degraded_players + wallSummary.online_players,
        4,
      ),
      throughput_per_minute: wallRecentItems.length,
      recent_items: wallRecentItems,
      animation_hint: STATION_HINTS.wall,
      render_group: STATION_RENDER_GROUPS.wall,
      dominant_reason: wallSummary.health !== 'healthy' ? 'Health do wall exige atencao.' : null,
      updated_at: sources.wall_diagnostics.updated_at ?? serverTime,
    },
    {
      station_key: 'feedback',
      label: STATION_LABELS.feedback,
      health: 'healthy',
      backlog_count: 0,
      queue_depth: 0,
      station_load: 0.1,
      throughput_per_minute: 0,
      recent_items: [],
      animation_hint: STATION_HINTS.feedback,
      render_group: STATION_RENDER_GROUPS.feedback,
      dominant_reason: null,
      updated_at: serverTime,
    },
    {
      station_key: 'alerts',
      label: STATION_LABELS.alerts,
      health: alerts.length > 0 ? 'risk' : 'healthy',
      backlog_count: alerts.length,
      queue_depth: alerts.length,
      station_load: normalizeStationLoad(alerts.length, 3),
      throughput_per_minute: alerts.length,
      recent_items: alertRecentItems,
      animation_hint: STATION_HINTS.alerts,
      render_group: STATION_RENDER_GROUPS.alerts,
      dominant_reason: alerts[0]?.summary ?? null,
      updated_at: alerts[0]?.occurred_at ?? serverTime,
    },
  ];

  return stations;
}

function resolveActiveEntryChannels(journey: EventOperationsBootSources['journey']): string[] {
  const channels: string[] = [];

  if (journey.intake_channels.whatsapp_direct.enabled) {
    channels.push('WhatsApp privado');
  }

  if (journey.intake_channels.whatsapp_groups.enabled) {
    channels.push('WhatsApp grupos');
  }

  if (journey.intake_channels.telegram.enabled) {
    channels.push('Telegram');
  }

  if (journey.intake_channels.public_upload.enabled) {
    channels.push('Link de envio');
  }

  return channels;
}

function resolveServerTime(sources: EventOperationsBootSources): string {
  const candidates = [
    sources.wall_live_snapshot.updatedAt,
    sources.wall_live_snapshot.advancedAt,
    sources.wall_diagnostics.updated_at,
    sources.wall_diagnostics.summary.updated_at,
    sources.timeline.at(-1)?.created_at,
  ].filter(Boolean) as string[];

  if (candidates.length === 0) {
    return new Date().toISOString();
  }

  return [...candidates].sort().at(-1) ?? new Date().toISOString();
}

function buildHealthSummary(
  stations: EventOperationsStationState[],
  alerts: EventOperationsAlert[],
): EventOperationsRoomSnapshot['health'] {
  const wallStation = stations.find((station) => station.station_key === 'wall');
  const humanReviewStation = stations.find((station) => station.station_key === 'human_review');

  if (alerts.some((alert) => alert.severity === 'critical') || wallStation?.health === 'risk') {
    return {
      status: 'risk',
      dominant_station_key: wallStation?.station_key ?? 'alerts',
      summary: 'Operacao em risco',
      updated_at: alerts[0]?.occurred_at ?? wallStation?.updated_at ?? new Date().toISOString(),
    };
  }

  if ((humanReviewStation?.queue_depth ?? 0) > 0 || stations.some((station) => station.health === 'attention')) {
    return {
      status: 'attention',
      dominant_station_key: (humanReviewStation?.queue_depth ?? 0) > 0 ? 'human_review' : wallStation?.station_key ?? 'variants',
      summary: 'Operacao em atencao',
      updated_at: humanReviewStation?.updated_at ?? new Date().toISOString(),
    };
  }

  return {
    status: 'healthy',
    dominant_station_key: null,
    summary: 'Operacao saudavel',
    updated_at: wallStation?.updated_at ?? new Date().toISOString(),
  };
}

function countPublishedWallItems(timeline: EventOperationsTimelineEntry[]): number {
  return timeline.filter((entry) => entry.event_key === 'media.published.wall').length;
}

export async function getEventOperationsBootSources(eventId: string | number): Promise<EventOperationsBootSources> {
  const normalizedEventId = String(eventId);

  const [
    journey,
    pipeline_metrics,
    moderation_stats,
    moderation_feed,
    timeline,
    wall_live_snapshot,
    wall_diagnostics,
  ] = await Promise.all([
    getEventJourneyBuilder(normalizedEventId),
    api.get<EventOperationsPipelineMetrics>(`/events/${normalizedEventId}/media/pipeline-metrics`),
    api.get<ModerationStatsMeta>('/media/feed/stats', {
      params: {
        event_id: normalizedEventId,
      },
    }),
    api.getRaw<EventOperationsModerationFeedPage>('/media/feed', {
      params: {
        event_id: normalizedEventId,
        status: 'pending_moderation',
        per_page: 6,
      },
    }),
    api.get<EventOperationsAuditTimelineItem[]>(`/events/${normalizedEventId}/timeline`, {
      params: {
        limit: 20,
      },
    }),
    api.get<ApiWallLiveSnapshotResponse>(`/events/${normalizedEventId}/wall/live-snapshot`),
    api.get<ApiWallDiagnosticsResponse>(`/events/${normalizedEventId}/wall/diagnostics`),
  ]);

  return {
    journey,
    pipeline_metrics,
    moderation_stats,
    moderation_feed,
    timeline,
    wall_live_snapshot,
    wall_diagnostics,
  };
}

export function buildEventOperationsV0Room(sources: EventOperationsBootSources): EventOperationsV0Room {
  const serverTime = resolveServerTime(sources);
  const activeEntryChannels = resolveActiveEntryChannels(sources.journey);
  const timeline = buildTimelineEntries(sources.timeline);
  const wall = buildWallSummary(sources.wall_live_snapshot, sources.wall_diagnostics);
  const alerts = buildAlerts(sources, serverTime);
  const stations = buildStations(sources, serverTime, wall, alerts, timeline);
  const health = buildHealthSummary(stations, alerts);
  const dominantStation = stations.find((station) => station.station_key === health.dominant_station_key);

  return {
    schema_version: EVENT_OPERATIONS_SCHEMA_VERSION,
    snapshot_version: 0,
    timeline_cursor: timeline.at(-1)?.id ?? null,
    event_sequence: timeline.at(-1)?.event_sequence ?? 0,
    server_time: serverTime,
    event: {
      id: sources.journey.event.id,
      title: sources.journey.event.title,
      slug: `event-${sources.journey.event.id}`,
      status: sources.journey.event.status === 'archived' ? 'archived' : sources.journey.event.status === 'ended' ? 'ended' : sources.journey.event.status === 'paused' ? 'paused' : 'live',
      timezone: 'America/Sao_Paulo',
    },
    health,
    connection: buildConnectionSummary(serverTime),
    counters: {
      backlog_total: sources.pipeline_metrics.summary.pending_total
        + sources.pipeline_metrics.queues.backlog.reduce((total, queue) => total + queue.processing_runs, 0),
      human_review_pending: sources.moderation_stats.pending,
      processing_failures: sources.pipeline_metrics.failures.reduce((total, item) => total + item.count, 0),
      intake_per_minute: activeEntryChannels.length,
      published_gallery_total: sources.pipeline_metrics.summary.published_total,
      published_wall_total: countPublishedWallItems(timeline),
    },
    stations,
    alerts,
    wall,
    timeline,
    v0: {
      mode: 'read_only',
      journey_summary_text: sources.journey.summary.human_text,
      active_entry_channels: activeEntryChannels,
      destinations: sources.journey.settings.destinations,
      dominant_station_reason: dominantStation?.dominant_reason ?? null,
    },
  };
}

export async function getEventOperationsBootRoom(eventId: string | number): Promise<EventOperationsV0Room> {
  const sources = await getEventOperationsBootSources(eventId);

  return buildEventOperationsV0Room(sources);
}

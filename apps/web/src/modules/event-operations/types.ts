import type {
  EventOperationsRoomSnapshot,
  EventOperationsSeverity,
  EventOperationsStationKey,
  EventOperationsTimelineEntry,
  EventOperationsVersionContract,
} from '@eventovivo/shared-types/event-operations';

export interface EventOperationsLegacyReadOnlyContext {
  mode: 'read_only';
  journey_summary_text: string;
  active_entry_channels: string[];
  destinations: {
    gallery: boolean;
    wall: boolean;
    print: boolean;
  };
  dominant_station_reason: string | null;
}

export interface EventOperationsV0Room extends EventOperationsRoomSnapshot {
  v0?: EventOperationsLegacyReadOnlyContext;
}

export interface EventOperationsTimelineFilters {
  cursor?: string | null;
  station_key?: EventOperationsStationKey | null;
  severity?: EventOperationsSeverity | null;
  event_media_id?: number | null;
  limit?: number;
}

export interface EventOperationsTimelinePage extends EventOperationsVersionContract {
  entries: EventOperationsTimelineEntry[];
  filters: {
    cursor: string | null;
    station_key: EventOperationsStationKey | null;
    severity: EventOperationsSeverity | null;
    event_media_id: number | null;
    limit: number;
  };
}

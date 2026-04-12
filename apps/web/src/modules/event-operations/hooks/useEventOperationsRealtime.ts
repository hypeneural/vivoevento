import { useCallback, useEffect, useRef, useState } from 'react';

import { useQueryClient } from '@tanstack/react-query';

import {
  EVENT_OPERATIONS_EVENT_NAMES,
  type EventOperationsDelta,
} from '@eventovivo/shared-types/event-operations';

import { eventOperationsHudStore } from '../stores/hud-store';
import { eventOperationsRoomStore, useEventOperationsRoomSnapshot } from '../stores/room-store';
import { eventOperationsTimelineStore } from '../stores/timeline-store';
import { createEventOperationsPusher, disconnectEventOperationsPusher } from '../realtime/pusher';
import { eventOperationsBootRoomQueryOptions, eventOperationsBootTimelineQueryOptions } from './useEventOperationsBoot';
import type { EventOperationsRealtimeState } from './useEventOperationsFallback';

interface EventOperationsRealtimeResult {
  connectionState: EventOperationsRealtimeState;
  statusMessage: string | null;
  lastResyncCompletedAt: string | null;
}

type EventOperationsRealtimeEventName =
  (typeof EVENT_OPERATIONS_EVENT_NAMES)[keyof typeof EVENT_OPERATIONS_EVENT_NAMES];

type EventOperationsRealtimePayload = Omit<EventOperationsDelta, 'kind' | 'broadcast_priority'> & {
  station_delta?: EventOperationsDelta['station_delta'];
  timeline_entry?: EventOperationsDelta['timeline_entry'];
  alert?: EventOperationsDelta['alert'];
  health?: EventOperationsDelta['health'];
  snapshot?: EventOperationsDelta['snapshot'];
};

const EVENT_KIND_MAP: Record<EventOperationsRealtimeEventName, EventOperationsDelta['kind']> = {
  [EVENT_OPERATIONS_EVENT_NAMES.stationDelta]: 'station.delta',
  [EVENT_OPERATIONS_EVENT_NAMES.timelineAppended]: 'timeline.appended',
  [EVENT_OPERATIONS_EVENT_NAMES.alertCreated]: 'alert.created',
  [EVENT_OPERATIONS_EVENT_NAMES.healthChanged]: 'health.changed',
  [EVENT_OPERATIONS_EVENT_NAMES.snapshotBoot]: 'snapshot.boot',
};

function mapBroadcastPriority(kind: EventOperationsDelta['kind']): EventOperationsDelta['broadcast_priority'] {
  if (kind === 'alert.created' || kind === 'health.changed') {
    return 'critical_immediate';
  }

  if (kind === 'snapshot.boot') {
    return 'operational_normal';
  }

  return 'operational_normal';
}

function buildRealtimeDelta(
  eventName: EventOperationsRealtimeEventName,
  payload: EventOperationsRealtimePayload,
): EventOperationsDelta {
  const kind = EVENT_KIND_MAP[eventName];

  return {
    schema_version: payload.schema_version,
    snapshot_version: payload.snapshot_version,
    timeline_cursor: payload.timeline_cursor,
    event_sequence: payload.event_sequence,
    server_time: payload.server_time,
    kind,
    broadcast_priority: mapBroadcastPriority(kind),
    station_delta: payload.station_delta ?? null,
    timeline_entry: payload.timeline_entry ?? null,
    alert: payload.alert ?? null,
    health: payload.health ?? null,
    snapshot: payload.snapshot ?? null,
    resync_required: payload.resync_required ?? false,
  };
}

export function useEventOperationsRealtime(eventId: string): EventOperationsRealtimeResult {
  const queryClient = useQueryClient();
  const roomSnapshot = useEventOperationsRoomSnapshot();
  const [connectionState, setConnectionState] = useState<EventOperationsRealtimeState>('idle');
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [lastResyncCompletedAt, setLastResyncCompletedAt] = useState<string | null>(null);
  const isResyncingRef = useRef(false);
  const connectedOnceRef = useRef(false);
  const lastConnectedAtRef = useRef<string | null>(null);
  const lastResyncCompletedAtRef = useRef<string | null>(null);

  useEffect(() => {
    lastConnectedAtRef.current = roomSnapshot?.connection.last_connected_at ?? null;
  }, [roomSnapshot?.connection.last_connected_at]);

  useEffect(() => {
    lastResyncCompletedAtRef.current = lastResyncCompletedAt;
  }, [lastResyncCompletedAt]);

  const syncRoomConnection = useCallback((
    nextState: EventOperationsRealtimeState,
    options: {
      serverTime?: string | null;
      degradedReason?: string | null;
    } = {},
  ) => {
    const now = options.serverTime ?? new Date().toISOString();

    const nextConnection = (() => {
      switch (nextState) {
        case 'connected':
          return {
            status: 'connected' as const,
            realtime_connected: true,
            last_connected_at: options.serverTime ?? lastConnectedAtRef.current ?? now,
            degraded_reason: null,
          };
        case 'connecting':
        case 'reconnecting':
          return {
            status: 'connecting' as const,
            realtime_connected: false,
            last_connected_at: lastConnectedAtRef.current,
            degraded_reason: 'reconnecting',
          };
        case 'resyncing':
          return {
            status: 'resyncing' as const,
            realtime_connected: false,
            last_connected_at: lastConnectedAtRef.current,
            degraded_reason: 'resync_required',
          };
        case 'offline':
          return {
            status: 'offline' as const,
            realtime_connected: false,
            last_connected_at: lastConnectedAtRef.current,
            degraded_reason: options.degradedReason ?? 'realtime_unavailable',
          };
        default:
          return {
            status: 'degraded' as const,
            realtime_connected: false,
            last_connected_at: lastConnectedAtRef.current,
            degraded_reason: options.degradedReason ?? 'websocket_disconnected',
          };
      }
    })();

    const nextRoom = eventOperationsRoomStore.setRealtimeConnection(nextConnection);

    if (nextRoom) {
      eventOperationsHudStore.setRoom(nextRoom);
    }
  }, []);

  useEffect(() => {
    syncRoomConnection(connectionState);
  }, [connectionState, syncRoomConnection]);

  useEffect(() => {
    if (!roomSnapshot) {
      return;
    }

    if (connectionState !== 'idle') {
      syncRoomConnection(connectionState);
    }
  }, [connectionState, roomSnapshot?.snapshot_version, syncRoomConnection]);

  useEffect(() => {
    if (!eventId) {
      setConnectionState('idle');
      setStatusMessage(null);
      setLastResyncCompletedAt(null);

      return undefined;
    }

    const pusher = createEventOperationsPusher();

    if (!pusher) {
      setConnectionState('offline');
      setStatusMessage('Sala degradada: dados ao vivo indisponiveis');
      syncRoomConnection('offline', {
        degradedReason: 'realtime_unavailable',
      });

      return undefined;
    }

    const resync = async (reason: string) => {
      if (isResyncingRef.current) {
        return;
      }

      isResyncingRef.current = true;
      setConnectionState('resyncing');
      setStatusMessage('Sincronizando a sala...');
      syncRoomConnection('resyncing', {
        degradedReason: reason,
      });

      try {
        const [room, timeline] = await Promise.all([
          queryClient.fetchQuery(eventOperationsBootRoomQueryOptions(eventId, false)),
          queryClient.fetchQuery(eventOperationsBootTimelineQueryOptions(eventId, false)),
        ]);

        eventOperationsRoomStore.setSnapshot(room);
        eventOperationsTimelineStore.setPage(timeline);
        lastConnectedAtRef.current = room.server_time;
        const nextRoom = eventOperationsRoomStore.setRealtimeConnection({
          status: 'connected',
          realtime_connected: true,
          last_connected_at: room.server_time,
          last_resync_at: room.server_time,
          degraded_reason: null,
        }) ?? room;

        eventOperationsHudStore.setRoom(nextRoom);
        setConnectionState('connected');
        setStatusMessage('Resync concluido');
        setLastResyncCompletedAt(room.server_time);
      } catch {
        setConnectionState('degraded');
        setStatusMessage('Sala degradada: dados ao vivo indisponiveis');
        syncRoomConnection('degraded', {
          degradedReason: reason,
        });
      } finally {
        isResyncingRef.current = false;
      }
    };

    const applyRealtimeDelta = (
      eventName: EventOperationsRealtimeEventName,
      payload: EventOperationsRealtimePayload,
    ) => {
      const delta = buildRealtimeDelta(eventName, payload);

      if (delta.kind === 'snapshot.boot' && delta.snapshot) {
        eventOperationsRoomStore.applySnapshotBoot(delta.snapshot);
        eventOperationsTimelineStore.setFromRoom(delta.snapshot);
        lastConnectedAtRef.current = delta.server_time;
        const nextRoom = eventOperationsRoomStore.setRealtimeConnection({
          status: 'connected',
          realtime_connected: true,
          last_connected_at: delta.server_time,
          last_resync_at: delta.server_time,
          degraded_reason: null,
        }) ?? delta.snapshot;

        eventOperationsHudStore.setRoom(nextRoom);
        setConnectionState('connected');
        setStatusMessage('Resync concluido');
        setLastResyncCompletedAt(delta.server_time);
        return;
      }

      const roomResult = eventOperationsRoomStore.applyDelta(delta);
      const timelineResult = eventOperationsTimelineStore.applyDelta(delta);

      if (
        roomResult.status === 'missing_snapshot'
        || roomResult.status === 'resync_required'
        || timelineResult.status === 'resync_required'
      ) {
        void resync(roomResult.reason ?? timelineResult.reason ?? 'resync_required');
        return;
      }

      const nextRoom = eventOperationsRoomStore.getSnapshot();

      if (nextRoom) {
        lastConnectedAtRef.current = delta.server_time;
        const connectedRoom = eventOperationsRoomStore.setRealtimeConnection({
          status: 'connected',
          realtime_connected: true,
          last_connected_at: delta.server_time,
          degraded_reason: null,
        }) ?? nextRoom;

        eventOperationsHudStore.setRoom(connectedRoom);
      }

      setConnectionState('connected');
      setStatusMessage(lastResyncCompletedAtRef.current ? 'Resync concluido' : null);
    };

    const handleStateChange = ({ current }: { current: string }) => {
      if (current === 'connected') {
        if (connectedOnceRef.current) {
          void resync('reconnected');
        } else {
          connectedOnceRef.current = true;
          lastConnectedAtRef.current = new Date().toISOString();
          setConnectionState('connected');
          setStatusMessage(lastResyncCompletedAtRef.current ? 'Resync concluido' : null);
          syncRoomConnection('connected', {
            serverTime: lastConnectedAtRef.current,
          });
        }

        return;
      }

      if (current === 'connecting') {
        const nextState = connectedOnceRef.current ? 'reconnecting' : 'connecting';
        setConnectionState(nextState);
        setStatusMessage(connectedOnceRef.current ? 'Reconectando...' : null);
        syncRoomConnection(nextState);
        return;
      }

      if (current === 'unavailable' || current === 'failed') {
        setConnectionState('offline');
        setStatusMessage('Sala degradada: dados ao vivo indisponiveis');
        syncRoomConnection('offline', {
          degradedReason: current,
        });
        return;
      }

      setConnectionState('degraded');
      setStatusMessage('Sala degradada: dados ao vivo indisponiveis');
      syncRoomConnection('degraded', {
        degradedReason: current,
      });
    };

    const channelName = `private-event.${eventId}.operations`;
    const channel = pusher.subscribe(channelName);

    setConnectionState('connecting');
    setStatusMessage(null);
    pusher.connection.bind('state_change', handleStateChange);
    channel.bind(
      EVENT_OPERATIONS_EVENT_NAMES.stationDelta,
      (payload: EventOperationsRealtimePayload) => applyRealtimeDelta(EVENT_OPERATIONS_EVENT_NAMES.stationDelta, payload),
    );
    channel.bind(
      EVENT_OPERATIONS_EVENT_NAMES.timelineAppended,
      (payload: EventOperationsRealtimePayload) => applyRealtimeDelta(EVENT_OPERATIONS_EVENT_NAMES.timelineAppended, payload),
    );
    channel.bind(
      EVENT_OPERATIONS_EVENT_NAMES.alertCreated,
      (payload: EventOperationsRealtimePayload) => applyRealtimeDelta(EVENT_OPERATIONS_EVENT_NAMES.alertCreated, payload),
    );
    channel.bind(
      EVENT_OPERATIONS_EVENT_NAMES.healthChanged,
      (payload: EventOperationsRealtimePayload) => applyRealtimeDelta(EVENT_OPERATIONS_EVENT_NAMES.healthChanged, payload),
    );
    channel.bind(
      EVENT_OPERATIONS_EVENT_NAMES.snapshotBoot,
      (payload: EventOperationsRealtimePayload) => applyRealtimeDelta(EVENT_OPERATIONS_EVENT_NAMES.snapshotBoot, payload),
    );

    return () => {
      pusher.connection.unbind('state_change', handleStateChange);
      channel.unbind_all();
      pusher.unsubscribe(channelName);
      disconnectEventOperationsPusher();
      isResyncingRef.current = false;
      connectedOnceRef.current = false;
      setConnectionState('idle');
      setStatusMessage(null);
    };
  }, [eventId, queryClient, syncRoomConnection]);

  return {
    connectionState,
    statusMessage,
    lastResyncCompletedAt,
  };
}

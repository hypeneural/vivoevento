import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { WALL_EVENT_NAMES } from '@eventovivo/shared-types/wall';

import type { ApiWallLiveSnapshotResponse } from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';

import { createWallManagerPusher, disconnectWallManagerPusher } from '../realtime/pusher';

export type WallManagerRealtimeState = 'connecting' | 'connected' | 'disconnected' | 'offline';

export function realtimeLabel(status: WallManagerRealtimeState) {
  switch (status) {
    case 'connecting':
      return 'Atualizacao ao vivo conectando';
    case 'connected':
      return 'Atualizacao ao vivo ativa';
    case 'offline':
      return 'Atualizacao ao vivo indisponivel';
    default:
      return 'Atualizacao ao vivo desconectada';
  }
}

export function useWallRealtimeSync(eventId: string) {
  const queryClient = useQueryClient();
  const [realtimeState, setRealtimeState] = useState<WallManagerRealtimeState>('disconnected');

  useEffect(() => {
    if (!eventId) {
      setRealtimeState('disconnected');
      return undefined;
    }

    const pusher = createWallManagerPusher();

    if (!pusher) {
      setRealtimeState('offline');
      return undefined;
    }

    const refreshAll = () => {
      void Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.settings(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.insights(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.liveSnapshot(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(eventId) }),
      ]);
    };

    const refreshDiagnostics = () => {
      void Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.insights(eventId) }),
        queryClient.invalidateQueries({ queryKey: queryKeys.wall.liveSnapshot(eventId) }),
      ]);
    };

    const applyLiveSnapshot = (payload: ApiWallLiveSnapshotResponse) => {
      queryClient.setQueryData(queryKeys.wall.liveSnapshot(eventId), payload);
    };

    const handleStateChange = ({ current }: { current: string }) => {
      if (current === 'connected') {
        setRealtimeState('connected');
        refreshAll();
        return;
      }

      if (current === 'connecting') {
        setRealtimeState('connecting');
        return;
      }

      setRealtimeState('disconnected');
    };

    const channelName = `private-event.${eventId}.wall`;
    const channel = pusher.subscribe(channelName);

    setRealtimeState('connecting');
    pusher.connection.bind('state_change', handleStateChange);
    channel.bind(WALL_EVENT_NAMES.settingsUpdated, refreshAll);
    channel.bind(WALL_EVENT_NAMES.statusChanged, refreshAll);
    channel.bind(WALL_EVENT_NAMES.expired, refreshAll);
    channel.bind(WALL_EVENT_NAMES.diagnosticsUpdated, refreshDiagnostics);
    channel.bind(WALL_EVENT_NAMES.liveSnapshotUpdated, applyLiveSnapshot);

    return () => {
      pusher.connection.unbind('state_change', handleStateChange);
      channel.unbind(WALL_EVENT_NAMES.settingsUpdated, refreshAll);
      channel.unbind(WALL_EVENT_NAMES.statusChanged, refreshAll);
      channel.unbind(WALL_EVENT_NAMES.expired, refreshAll);
      channel.unbind(WALL_EVENT_NAMES.diagnosticsUpdated, refreshDiagnostics);
      channel.unbind(WALL_EVENT_NAMES.liveSnapshotUpdated, applyLiveSnapshot);
      pusher.unsubscribe(channelName);
      disconnectWallManagerPusher();
      setRealtimeState('disconnected');
    };
  }, [eventId, queryClient]);

  return realtimeState;
}

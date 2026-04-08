import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { WALL_EVENT_NAMES } from '@eventovivo/shared-types/wall';

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

export function useWallManagerRealtime(eventId: string) {
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

    const refresh = () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.byEvent(eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.insights(eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(eventId) });
    };
    const refreshDiagnostics = () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.byEvent(eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.diagnostics(eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.wall.insights(eventId) });
    };

    const handleStateChange = ({ current }: { current: string }) => {
      if (current === 'connected') {
        setRealtimeState('connected');
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
    channel.bind(WALL_EVENT_NAMES.settingsUpdated, refresh);
    channel.bind(WALL_EVENT_NAMES.statusChanged, refresh);
    channel.bind(WALL_EVENT_NAMES.expired, refresh);
    channel.bind(WALL_EVENT_NAMES.diagnosticsUpdated, refreshDiagnostics);

    return () => {
      pusher.connection.unbind('state_change', handleStateChange);
      channel.unbind(WALL_EVENT_NAMES.settingsUpdated, refresh);
      channel.unbind(WALL_EVENT_NAMES.statusChanged, refresh);
      channel.unbind(WALL_EVENT_NAMES.expired, refresh);
      channel.unbind(WALL_EVENT_NAMES.diagnosticsUpdated, refreshDiagnostics);
      pusher.unsubscribe(channelName);
      disconnectWallManagerPusher();
      setRealtimeState('disconnected');
    };
  }, [eventId, queryClient]);

  return realtimeState;
}

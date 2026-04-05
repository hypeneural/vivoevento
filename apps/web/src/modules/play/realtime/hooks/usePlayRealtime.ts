import { useEffect, useRef, useState } from 'react';
import { PLAY_EVENT_NAMES } from '@eventovivo/shared-types/play';
import type { PlayRealtimeLeaderboardPayload } from '@eventovivo/shared-types/play';

import { createPlayPusher, disconnectPlayPusher } from '../pusher';

type PlayConnectionStatus = 'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected' | 'error';

interface UsePlayRealtimeOptions {
  channelName?: string | null;
  onLeaderboardUpdated: (payload: PlayRealtimeLeaderboardPayload) => void;
}

export function usePlayRealtime({ channelName, onLeaderboardUpdated }: UsePlayRealtimeOptions) {
  const [connectionStatus, setConnectionStatus] = useState<PlayConnectionStatus>('idle');
  const callbackRef = useRef(onLeaderboardUpdated);

  useEffect(() => {
    callbackRef.current = onLeaderboardUpdated;
  }, [onLeaderboardUpdated]);

  useEffect(() => {
    if (!channelName) {
      return undefined;
    }

    const pusher = createPlayPusher();

    if (!pusher) {
      setConnectionStatus('error');
      return undefined;
    }

    let hasConnectedOnce = false;

    const handleStateChange = (states: { current: string; previous: string }) => {
      if (states.current === 'connected') {
        setConnectionStatus('connected');
        hasConnectedOnce = true;
      } else if (states.current === 'connecting') {
        setConnectionStatus(hasConnectedOnce ? 'reconnecting' : 'connecting');
      } else if (states.current === 'disconnected') {
        setConnectionStatus('disconnected');
      } else if (states.current === 'unavailable' || states.current === 'failed') {
        setConnectionStatus('error');
      }
    };

    setConnectionStatus('connecting');
    pusher.connection.bind('state_change', handleStateChange);

    const channel = pusher.subscribe(channelName);

    channel.bind(PLAY_EVENT_NAMES.leaderboardUpdated, (data: PlayRealtimeLeaderboardPayload) => {
      callbackRef.current(data);
    });

    return () => {
      channel.unbind_all();
      pusher.unsubscribe(channelName);
      pusher.connection.unbind('state_change', handleStateChange);
      disconnectPlayPusher();
    };
  }, [channelName]);

  return {
    connectionStatus,
  };
}

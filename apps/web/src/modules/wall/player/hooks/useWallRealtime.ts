/**
 * useWallRealtime — WebSocket hook for the wall player.
 *
 * Subscribes to the public Pusher channel wall.{wallCode}
 * and routes events to the appropriate callbacks.
 */

import { useEffect, useRef, useState } from 'react';
import { createWallPusher, disconnectWallPusher } from '../pusher';
import type {
  WallConnectionStatus,
  WallExpiredPayload,
  WallMediaItem,
  WallMediaDeletedPayload,
  WallSettings,
  WallStatusChangedPayload,
} from '../types';

interface UseWallRealtimeOptions {
  code: string;
  onNewMedia: (payload: WallMediaItem) => void;
  onMediaUpdated: (payload: WallMediaItem) => void;
  onMediaDeleted: (payload: WallMediaDeletedPayload) => void;
  onSettingsUpdated: (payload: WallSettings) => void;
  onStatusChanged: (payload: WallStatusChangedPayload) => void;
  onExpired: (payload: WallExpiredPayload) => void;
}

export function useWallRealtime({
  code,
  onNewMedia,
  onMediaUpdated,
  onMediaDeleted,
  onSettingsUpdated,
  onStatusChanged,
  onExpired,
}: UseWallRealtimeOptions) {
  const [connectionStatus, setConnectionStatus] = useState<WallConnectionStatus>('idle');

  // Ref to always have latest callbacks without re-subscribing
  const callbacksRef = useRef({
    onNewMedia,
    onMediaUpdated,
    onMediaDeleted,
    onSettingsUpdated,
    onStatusChanged,
    onExpired,
  });

  useEffect(() => {
    callbacksRef.current = {
      onNewMedia,
      onMediaUpdated,
      onMediaDeleted,
      onSettingsUpdated,
      onStatusChanged,
      onExpired,
    };
  });

  useEffect(() => {
    const pusher = createWallPusher();

    if (!pusher) {
      setConnectionStatus('error');
      return undefined;
    }

    const channelName = `wall.${code}`;
    let hasConnectedOnce = false;

    // Connection state tracking
    const handleStateChange = (states: { current: string; previous: string }) => {
      const { current } = states;

      if (current === 'connected') {
        setConnectionStatus('connected');
        hasConnectedOnce = true;
      } else if (current === 'connecting') {
        setConnectionStatus(hasConnectedOnce ? 'reconnecting' : 'connecting');
      } else if (current === 'disconnected') {
        setConnectionStatus('disconnected');
      } else if (current === 'unavailable' || current === 'failed') {
        setConnectionStatus('error');
      }
    };

    setConnectionStatus('connecting');
    pusher.connection.bind('state_change', handleStateChange);

    // Subscribe to the public channel
    const channel = pusher.subscribe(channelName);

    // Bind broadcast events (prefixed with . for custom event names)
    channel.bind('wall.media.published', (data: WallMediaItem) => {
      callbacksRef.current.onNewMedia(data);
    });

    channel.bind('wall.media.updated', (data: WallMediaItem) => {
      callbacksRef.current.onMediaUpdated(data);
    });

    channel.bind('wall.media.deleted', (data: WallMediaDeletedPayload) => {
      callbacksRef.current.onMediaDeleted(data);
    });

    channel.bind('wall.settings.updated', (data: WallSettings) => {
      callbacksRef.current.onSettingsUpdated(data);
    });

    channel.bind('wall.status.changed', (data: WallStatusChangedPayload) => {
      callbacksRef.current.onStatusChanged(data);
    });

    channel.bind('wall.expired', (data: WallExpiredPayload) => {
      callbacksRef.current.onExpired(data);
    });

    return () => {
      pusher.connection.unbind('state_change', handleStateChange);
      pusher.unsubscribe(channelName);
      disconnectWallPusher();
      setConnectionStatus('disconnected');
    };
  }, [code]);

  return { connectionStatus };
}

export default useWallRealtime;

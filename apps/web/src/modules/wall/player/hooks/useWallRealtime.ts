/**
 * useWallRealtime — WebSocket hook for the wall player.
 *
 * Subscribes to the public Pusher channel wall.{wallCode}
 * and routes events to the appropriate callbacks.
 */

import { useEffect, useRef, useState } from 'react';
import { WALL_EVENT_NAMES } from '@eventovivo/shared-types/wall';
import { createWallPusher, disconnectWallPusher } from '../pusher';
import type {
  WallAdItem,
  WallConnectionStatus,
  WallExpiredPayload,
  WallMediaItem,
  WallMediaDeletedPayload,
  WallPlayerCommandPayload,
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
  onPlayerCommand: (payload: WallPlayerCommandPayload) => void;
  onAdsUpdated?: (payload: { ads: WallAdItem[] }) => void;
}

function mapConnectionState(current: string, hasConnectedOnce: boolean): WallConnectionStatus {
  if (current === 'connected') {
    return 'connected';
  }

  if (current === 'connecting' || current === 'initialized') {
    return hasConnectedOnce ? 'reconnecting' : 'connecting';
  }

  if (current === 'disconnected') {
    return 'disconnected';
  }

  if (current === 'unavailable' || current === 'failed') {
    return 'error';
  }

  return 'idle';
}

export function useWallRealtime({
  code,
  onNewMedia,
  onMediaUpdated,
  onMediaDeleted,
  onSettingsUpdated,
  onStatusChanged,
  onExpired,
  onPlayerCommand,
  onAdsUpdated,
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
    onPlayerCommand,
    onAdsUpdated,
  });

  useEffect(() => {
    callbacksRef.current = {
      onNewMedia,
      onMediaUpdated,
      onMediaDeleted,
      onSettingsUpdated,
      onStatusChanged,
      onExpired,
      onPlayerCommand,
      onAdsUpdated,
    };
  });

  useEffect(() => {
    const pusher = createWallPusher();

    if (!pusher) {
      setConnectionStatus('error');
      return undefined;
    }

    const channelName = `wall.${code}`;
    let hasConnectedOnce = pusher.connection.state === 'connected';

    // Connection state tracking
    const handleStateChange = (states: { current: string; previous: string }) => {
      const { current } = states;

      if (current === 'connected') {
        hasConnectedOnce = true;
      }

      setConnectionStatus(mapConnectionState(current, hasConnectedOnce));
    };

    setConnectionStatus(mapConnectionState(pusher.connection.state, hasConnectedOnce));
    pusher.connection.bind('state_change', handleStateChange);

    // Subscribe to the public channel
    const channel = pusher.subscribe(channelName);

    // Bind broadcast events (prefixed with . for custom event names)
    channel.bind(WALL_EVENT_NAMES.mediaPublished, (data: WallMediaItem) => {
      callbacksRef.current.onNewMedia(data);
    });

    channel.bind(WALL_EVENT_NAMES.mediaUpdated, (data: WallMediaItem) => {
      callbacksRef.current.onMediaUpdated(data);
    });

    channel.bind(WALL_EVENT_NAMES.mediaDeleted, (data: WallMediaDeletedPayload) => {
      callbacksRef.current.onMediaDeleted(data);
    });

    channel.bind(WALL_EVENT_NAMES.settingsUpdated, (data: WallSettings) => {
      callbacksRef.current.onSettingsUpdated(data);
    });

    channel.bind(WALL_EVENT_NAMES.statusChanged, (data: WallStatusChangedPayload) => {
      callbacksRef.current.onStatusChanged(data);
    });

    channel.bind(WALL_EVENT_NAMES.expired, (data: WallExpiredPayload) => {
      callbacksRef.current.onExpired(data);
    });

    channel.bind(WALL_EVENT_NAMES.playerCommand, (data: WallPlayerCommandPayload) => {
      callbacksRef.current.onPlayerCommand(data);
    });

    channel.bind(WALL_EVENT_NAMES.adsUpdated, (data: { ads: WallAdItem[] }) => {
      callbacksRef.current.onAdsUpdated?.(data);
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

import { useEffect, useRef, useState } from 'react';

import type { ApiEventMediaItem } from '@/lib/api-types';

import { MODERATION_EVENT_NAMES } from '../types';
import { createModerationPusher, disconnectModerationPusher } from '../realtime/pusher';

interface ModerationDeletedPayload {
  id: number;
  event_id: number;
}

interface UseModerationRealtimeOptions {
  enabled: boolean;
  organizationId?: number | null;
  onCreated: (media: ApiEventMediaItem) => void;
  onUpdated: (media: ApiEventMediaItem) => void;
  onDeleted: (payload: ModerationDeletedPayload) => void;
}

export function useModerationRealtime({
  enabled,
  organizationId,
  onCreated,
  onUpdated,
  onDeleted,
}: UseModerationRealtimeOptions) {
  const [connectionStatus, setConnectionStatus] = useState<'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected' | 'error'>('idle');

  const callbacksRef = useRef({
    onCreated,
    onUpdated,
    onDeleted,
  });

  useEffect(() => {
    callbacksRef.current = {
      onCreated,
      onUpdated,
      onDeleted,
    };
  }, [onCreated, onDeleted, onUpdated]);

  useEffect(() => {
    if (!enabled || !organizationId) {
      setConnectionStatus('idle');
      return undefined;
    }

    const pusher = createModerationPusher();

    if (!pusher) {
      setConnectionStatus('error');
      return undefined;
    }

    const channelName = `private-organization.${organizationId}.moderation`;
    let connectedOnce = false;

    const handleStateChange = (states: { current: string }) => {
      if (states.current === 'connected') {
        connectedOnce = true;
        setConnectionStatus('connected');
      } else if (states.current === 'connecting') {
        setConnectionStatus(connectedOnce ? 'reconnecting' : 'connecting');
      } else if (states.current === 'disconnected') {
        setConnectionStatus('disconnected');
      } else if (states.current === 'unavailable' || states.current === 'failed') {
        setConnectionStatus('error');
      }
    };

    setConnectionStatus('connecting');
    pusher.connection.bind('state_change', handleStateChange);

    const channel = pusher.subscribe(channelName);

    channel.bind(MODERATION_EVENT_NAMES.created, (payload: ApiEventMediaItem) => {
      callbacksRef.current.onCreated(payload);
    });

    channel.bind(MODERATION_EVENT_NAMES.updated, (payload: ApiEventMediaItem) => {
      callbacksRef.current.onUpdated(payload);
    });

    channel.bind(MODERATION_EVENT_NAMES.deleted, (payload: ModerationDeletedPayload) => {
      callbacksRef.current.onDeleted(payload);
    });

    return () => {
      channel.unbind(MODERATION_EVENT_NAMES.created);
      channel.unbind(MODERATION_EVENT_NAMES.updated);
      channel.unbind(MODERATION_EVENT_NAMES.deleted);
      pusher.unsubscribe(channelName);
      pusher.connection.unbind('state_change', handleStateChange);
      disconnectModerationPusher();
      setConnectionStatus('disconnected');
    };
  }, [enabled, organizationId]);

  return {
    connectionStatus,
  };
}

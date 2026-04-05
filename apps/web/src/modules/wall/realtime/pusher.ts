import Pusher from 'pusher-js';

import { getToken } from '@/lib/api';

let pusherInstance: Pusher | null = null;

const REVERB_KEY = import.meta.env.VITE_REVERB_APP_KEY || '';
const REVERB_HOST = import.meta.env.VITE_REVERB_HOST || '';
const REVERB_PORT = import.meta.env.VITE_REVERB_PORT || '8080';
const REVERB_SCHEME = import.meta.env.VITE_REVERB_SCHEME || 'http';

const PUSHER_KEY = import.meta.env.VITE_PUSHER_APP_KEY || '';
const PUSHER_CLUSTER = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'sa1';
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

function isReverb() {
  return Boolean(REVERB_KEY && REVERB_HOST);
}

function getAppKey() {
  return isReverb() ? REVERB_KEY : PUSHER_KEY;
}

function getBroadcastAuthEndpoint() {
  const url = new URL(API_BASE_URL, window.location.origin);
  const normalizedBasePath = url.pathname
    .replace(/\/api\/v\d+\/?$/i, '')
    .replace(/\/api\/?$/i, '')
    .replace(/\/$/, '');

  url.pathname = `${normalizedBasePath}/broadcasting/auth` || '/broadcasting/auth';
  url.search = '';

  return url.toString();
}

export function createWallManagerPusher() {
  const key = getAppKey();

  if (!key) {
    console.warn('[WallManager] Nenhuma chave WebSocket configurada para realtime.');
    return null;
  }

  if (pusherInstance) {
    return pusherInstance;
  }

  const token = getToken();
  const channelAuthorization = {
    endpoint: getBroadcastAuthEndpoint(),
    transport: 'ajax' as const,
    headers: token
      ? {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      }
      : {
        Accept: 'application/json',
      },
  };

  if (isReverb()) {
    const forceTLS = REVERB_SCHEME === 'https';

    pusherInstance = new Pusher(key, {
      wsHost: REVERB_HOST,
      wsPort: forceTLS ? undefined : Number(REVERB_PORT),
      wssPort: forceTLS ? Number(REVERB_PORT) : undefined,
      forceTLS,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      cluster: '',
      channelAuthorization,
    });
  } else {
    pusherInstance = new Pusher(key, {
      cluster: PUSHER_CLUSTER,
      forceTLS: true,
      channelAuthorization,
    });
  }

  return pusherInstance;
}

export function disconnectWallManagerPusher() {
  if (pusherInstance) {
    pusherInstance.disconnect();
    pusherInstance = null;
  }
}

import Pusher from 'pusher-js';

import { shouldDisableRealtimeInDev } from '@/lib/realtime';

let pusherInstance: Pusher | null = null;

const REVERB_KEY = import.meta.env.VITE_REVERB_APP_KEY || '';
const REVERB_HOST = import.meta.env.VITE_REVERB_HOST || '';
const REVERB_PORT = import.meta.env.VITE_REVERB_PORT || '8080';
const REVERB_SCHEME = import.meta.env.VITE_REVERB_SCHEME || 'http';

const PUSHER_KEY = import.meta.env.VITE_PUSHER_APP_KEY || '';
const PUSHER_CLUSTER = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'sa1';

function isReverb(): boolean {
  return Boolean(REVERB_KEY && REVERB_HOST);
}

function getAppKey(): string {
  return isReverb() ? REVERB_KEY : PUSHER_KEY;
}

export function createPlayPusher(): Pusher | null {
  const key = getAppKey();

  if (!key) {
    console.warn('[PlayRealtime] Nenhuma chave WebSocket configurada.');
    return null;
  }

  if (pusherInstance) {
    return pusherInstance;
  }

  if (isReverb()) {
    if (shouldDisableRealtimeInDev('PlayRealtime', {
      key: REVERB_KEY,
      host: REVERB_HOST,
      port: REVERB_PORT,
      scheme: REVERB_SCHEME,
    })) {
      return null;
    }

    const forceTLS = REVERB_SCHEME === 'https';

    pusherInstance = new Pusher(key, {
      wsHost: REVERB_HOST,
      wsPort: forceTLS ? undefined : Number(REVERB_PORT),
      wssPort: forceTLS ? Number(REVERB_PORT) : undefined,
      forceTLS,
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      cluster: '',
    });
  } else {
    pusherInstance = new Pusher(key, {
      cluster: PUSHER_CLUSTER,
      forceTLS: true,
    });
  }

  return pusherInstance;
}

export function disconnectPlayPusher(): void {
  if (pusherInstance) {
    pusherInstance.disconnect();
    pusherInstance = null;
  }
}

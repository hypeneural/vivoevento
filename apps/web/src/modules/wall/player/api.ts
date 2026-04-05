/**
 * Wall Player — API Client
 *
 * Communicates with the public wall endpoints (no auth required).
 * Uses the base fetch API instead of the auth-wrapped api client.
 */

import type { WallBootData, WallHeartbeatPayload, WallStateData } from './types';

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export async function getWallBoot(wallCode: string): Promise<WallBootData> {
  const res = await fetch(`${API_BASE}/public/wall/${wallCode}/boot`, {
    headers: { Accept: 'application/json' },
  });

  if (res.status === 410) {
    throw new WallUnavailableError('O telão foi encerrado ou está indisponível.');
  }

  if (!res.ok) {
    throw new Error(`Falha ao carregar o telão (HTTP ${res.status})`);
  }

  const json = await res.json();
  return json.data ?? json;
}

export async function getWallState(wallCode: string): Promise<WallStateData> {
  const res = await fetch(`${API_BASE}/public/wall/${wallCode}/state`, {
    headers: { Accept: 'application/json' },
  });

  if (res.status === 410) {
    throw new WallUnavailableError('O telão foi encerrado ou está indisponível.');
  }

  if (!res.ok) {
    throw new Error(`Falha ao sincronizar o telão (HTTP ${res.status})`);
  }

  const json = await res.json();
  return json.data ?? json;
}

export async function sendWallHeartbeat(
  wallCode: string,
  payload: WallHeartbeatPayload,
): Promise<void> {
  const res = await fetch(`${API_BASE}/public/wall/${wallCode}/heartbeat`, {
    method: 'POST',
    keepalive: true,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  if (res.status === 410) {
    throw new WallUnavailableError('O telao foi encerrado ou esta indisponivel.');
  }

  if (!res.ok) {
    throw new Error(`Falha ao enviar heartbeat do telao (HTTP ${res.status})`);
  }
}

export class WallUnavailableError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'WallUnavailableError';
  }
}

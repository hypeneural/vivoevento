export type StoredPlaySession = {
  eventSlug: string;
  gameSlug: string;
  sessionUuid: string;
  resumeToken: string;
  eventGameId: number;
  gameKey: string;
  playerIdentifier: string;
  playerName?: string | null;
  startedAt?: string | null;
  lastActivityAt?: string | null;
  expiresAt?: string | null;
  sessionSeed?: string | null;
};

function storageKey(eventSlug: string, gameSlug: string) {
  return `eventovivo:play:active-session:${eventSlug}:${gameSlug}`;
}

export function readStoredPlaySession(eventSlug: string, gameSlug: string): StoredPlaySession | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const raw = window.localStorage.getItem(storageKey(eventSlug, gameSlug));
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as StoredPlaySession;
  } catch {
    window.localStorage.removeItem(storageKey(eventSlug, gameSlug));
    return null;
  }
}

export function writeStoredPlaySession(session: StoredPlaySession) {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.setItem(storageKey(session.eventSlug, session.gameSlug), JSON.stringify(session));
}

export function clearStoredPlaySession(eventSlug: string, gameSlug: string) {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.removeItem(storageKey(eventSlug, gameSlug));
}

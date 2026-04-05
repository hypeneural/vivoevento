import api from '@/lib/api';
import type {
  PlayAnalyticsResponse,
  EventPlayManagerResponse,
  FinishPlaySessionResponse,
  FinishPlaySessionPayload,
  HeartbeatPlaySessionPayload,
  HeartbeatPlaySessionResponse,
  PlayCatalogItem,
  PlaySessionAnalyticsResponse,
  PlayGameAsset,
  PlayRankingEntry,
  PlayEventGame,
  PublicPlayEventManifest,
  PublicPlayGameResponse,
  ResumePlaySessionPayload,
  ResumePlaySessionResponse,
  StartPlaySessionPayload,
  StartPlaySessionResponse,
  StorePlayMovesPayload,
  StorePlayMovesResponse,
} from '@/lib/api-types';

export function fetchPlayCatalog() {
  return api.get<PlayCatalogItem[]>('/play/catalog');
}

export function fetchEventPlayManager(eventId: string | number) {
  return api.get<EventPlayManagerResponse>(`/events/${eventId}/play`);
}

export function fetchEventPlayAnalytics(
  eventId: string | number,
  filters?: {
    play_game_id?: number | null;
    date_from?: string;
    date_to?: string;
    status?: string;
    search?: string;
    session_limit?: number;
  },
) {
  return api.get<PlayAnalyticsResponse>(`/events/${eventId}/play/analytics`, {
    params: filters,
  });
}

export function updateEventPlaySettings(
  eventId: string | number,
  payload: {
    is_enabled?: boolean;
    memory_enabled?: boolean;
    puzzle_enabled?: boolean;
    memory_card_count?: number;
    puzzle_piece_count?: number;
    auto_refresh_assets?: boolean;
    ranking_enabled?: boolean;
  },
) {
  return api.patch<EventPlayManagerResponse['settings']>(`/events/${eventId}/play/settings`, { body: payload });
}

export function createEventPlayGame(
  eventId: string | number,
  payload: {
    game_type_key: string;
    title: string;
    slug?: string;
    is_active?: boolean;
    ranking_enabled?: boolean;
    sort_order?: number;
    settings?: Record<string, unknown>;
  },
) {
  return api.post<PlayEventGame>(`/events/${eventId}/play/games`, { body: payload });
}

export function updateEventPlayGame(
  eventId: string | number,
  playGameId: string | number,
  payload: {
    title?: string;
    slug?: string;
    is_active?: boolean;
    ranking_enabled?: boolean;
    sort_order?: number;
    settings?: Record<string, unknown>;
  },
) {
  return api.patch<PlayEventGame>(`/events/${eventId}/play/games/${playGameId}`, { body: payload });
}

export function deleteEventPlayGame(eventId: string | number, playGameId: string | number) {
  return api.delete<void>(`/events/${eventId}/play/games/${playGameId}`);
}

export function syncEventPlayGameAssets(
  eventId: string | number,
  playGameId: string | number,
  assets: Array<{
    media_id: number;
    role: string;
    sort_order?: number;
    metadata?: Record<string, unknown>;
  }>,
) {
  return api.post<PlayEventGame>(`/events/${eventId}/play/games/${playGameId}/assets`, {
    body: { assets },
  });
}

export function fetchEventPlayGameAssets(eventId: string | number, playGameId: string | number) {
  return api.get<PlayGameAsset[]>(`/events/${eventId}/play/games/${playGameId}/assets`);
}

export function fetchPublicPlayManifest(eventSlug: string) {
  return api.get<PublicPlayEventManifest>(`/public/events/${eventSlug}/play`);
}

export function fetchPublicPlayGame(
  eventSlug: string,
  gameSlug: string,
  params?: Record<string, string | number | boolean>,
) {
  return api.get<PublicPlayGameResponse>(`/public/events/${eventSlug}/play/${gameSlug}`, {
    params,
  });
}

export function startPublicPlaySession(
  eventSlug: string,
  gameSlug: string,
  payload: StartPlaySessionPayload,
) {
  return api.post<StartPlaySessionResponse>(`/public/events/${eventSlug}/play/${gameSlug}/sessions`, {
    body: payload,
  });
}

export function finishPublicPlaySession(sessionUuid: string, payload: FinishPlaySessionPayload) {
  return api.post<FinishPlaySessionResponse>(
    `/public/play/sessions/${sessionUuid}/finish`,
    { body: payload },
  );
}

export function storePublicPlayMoves(sessionUuid: string, payload: StorePlayMovesPayload) {
  return api.post<StorePlayMovesResponse>(`/public/play/sessions/${sessionUuid}/moves`, {
    body: payload,
  });
}

export function heartbeatPublicPlaySession(sessionUuid: string, payload: HeartbeatPlaySessionPayload) {
  return api.post<HeartbeatPlaySessionResponse>(`/public/play/sessions/${sessionUuid}/heartbeat`, {
    body: payload,
  });
}

export function resumePublicPlaySession(sessionUuid: string, payload: ResumePlaySessionPayload) {
  return api.post<ResumePlaySessionResponse>(`/public/play/sessions/${sessionUuid}/resume`, {
    body: payload,
  });
}

export function fetchPublicPlaySessionAnalytics(sessionUuid: string) {
  return api.get<PlaySessionAnalyticsResponse>(`/public/play/sessions/${sessionUuid}/analytics`);
}

export function fetchPublicPlayRanking(eventSlug: string, gameSlug: string) {
  return api.get<PlayRankingEntry[]>(`/public/events/${eventSlug}/play/${gameSlug}/ranking`);
}

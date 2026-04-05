export const PLAY_EVENT_NAMES = {
  leaderboardUpdated: 'play.leaderboard.updated',
} as const;

export interface PlaySessionAnalytics {
  total_moves: number;
  unique_move_types: number;
  move_type_breakdown: Record<string, number>;
  last_move_number: number | null;
  first_move_at: string | null;
  last_move_at: string | null;
  elapsed_ms: number | null;
  activity_window_ms: number;
  completed: boolean;
  score: number | null;
  time_ms: number | null;
  moves_reported: number | null;
  mistakes: number | null;
  accuracy: number | null;
}

export interface PlayGameAnalytics {
  total_sessions: number;
  finished_sessions: number;
  abandoned_sessions: number;
  active_sessions: number;
  completion_rate: number;
  unique_players: number;
  total_moves: number;
  average_score: number | null;
  average_time_ms: number | null;
  average_moves: number | null;
  best_score: number | null;
  last_finished_at: string | null;
}

export interface PlayRealtimeLeaderboardPayload {
  game_uuid: string;
  game_slug: string;
  leaderboard: Array<Record<string, unknown>>;
  last_plays: Array<Record<string, unknown>>;
  analytics: PlayGameAnalytics;
  updated_at: string;
}

import type { ApiEvent, ApiOrganization } from '@/lib/api-types';

export type AnalyticsPeriod = '7d' | '30d' | '90d' | 'custom';
export type AnalyticsModule = 'live' | 'hub' | 'wall' | 'play';

export interface AnalyticsOption {
  id: number;
  label: string;
  description?: string | null;
}

export interface AnalyticsFilters {
  period: AnalyticsPeriod;
  date_from?: string;
  date_to?: string;
  organization_id?: number;
  client_id?: number;
  event_status?: string;
  module?: AnalyticsModule;
  event_id?: number;
}

export interface AnalyticsDelta {
  type: 'percentage' | 'points';
  value: number;
  difference: number;
  previous: number;
  direction: -1 | 0 | 1;
}

export interface AnalyticsSummary {
  uploads_received: number;
  uploads_approved: number;
  uploads_published: number;
  approval_rate: number;
  publication_rate: number;
  hub_views: number;
  gallery_views: number;
  wall_views: number;
  upload_views: number;
  upload_completed: number;
  play_views: number;
  play_game_views: number;
  play_sessions: number;
  unique_players: number;
  public_interactions: number;
}

export interface AnalyticsFiltersResolved {
  period: AnalyticsPeriod;
  date_from: string;
  date_to: string;
  comparison: {
    date_from: string;
    date_to: string;
  };
  organization_id?: number | null;
  client_id?: number | null;
  event_status?: string | null;
  module?: AnalyticsModule | null;
}

export interface AnalyticsBreakdownItem {
  key: string;
  label: string;
  count: number;
  percentage: number;
}

export interface AnalyticsMediaTimelinePoint {
  date: string;
  uploads_received: number;
  uploads_approved: number;
  uploads_published: number;
  approval_rate: number;
  publication_rate: number;
}

export interface AnalyticsTrafficTimelinePoint {
  date: string;
  hub_views: number;
  gallery_views: number;
  wall_views: number;
  upload_views: number;
  upload_completed: number;
  play_views: number;
  play_game_views: number;
  public_interactions: number;
}

export interface AnalyticsPlayTimelinePoint {
  date: string;
  sessions: number;
  unique_players: number;
}

export interface AnalyticsTopEvent {
  event_id: number;
  title: string;
  slug: string;
  status: string | null;
  organization_name: string | null;
  client_name: string | null;
  cover_image_url: string | null;
  uploads: number;
  approval_rate: number;
  publication_rate: number;
  hub_views: number;
  gallery_views: number;
  wall_views: number;
  play_sessions: number;
  public_interactions: number;
  share_percentage: number;
}

export interface PlatformAnalyticsResponse {
  filters: AnalyticsFiltersResolved;
  summary: AnalyticsSummary;
  deltas: Record<string, AnalyticsDelta>;
  timelines: {
    media: AnalyticsMediaTimelinePoint[];
    traffic: AnalyticsTrafficTimelinePoint[];
    play: AnalyticsPlayTimelinePoint[];
  };
  breakdowns: {
    modules: AnalyticsBreakdownItem[];
    source_types: AnalyticsBreakdownItem[];
    event_statuses: AnalyticsBreakdownItem[];
  };
  rankings: {
    top_events: AnalyticsTopEvent[];
  };
}

export interface AnalyticsFunnelStep {
  key: string;
  label: string;
  count: number;
  percentage: number;
}

export interface EventAnalyticsPlayGame {
  id: number;
  title: string;
  slug: string;
  game_type_key: string | null;
  game_type_name: string | null;
  is_active: boolean;
  ranking_enabled: boolean;
  sessions: number;
  unique_players: number;
  completion_rate: number;
  share_percentage: number;
}

export interface EventAnalyticsPlayBlock {
  enabled: boolean;
  ranking_enabled: boolean;
  games_count: number;
  sessions: number;
  unique_players: number;
  games: EventAnalyticsPlayGame[];
}

export interface EventAnalyticsResponse {
  event: ApiEvent;
  filters: AnalyticsFiltersResolved;
  summary: AnalyticsSummary;
  deltas: Record<string, AnalyticsDelta>;
  funnel: AnalyticsFunnelStep[];
  timelines: {
    media: AnalyticsMediaTimelinePoint[];
    traffic: AnalyticsTrafficTimelinePoint[];
    play: AnalyticsPlayTimelinePoint[];
  };
  breakdowns: {
    source_types: AnalyticsBreakdownItem[];
    surfaces: AnalyticsBreakdownItem[];
  };
  play: EventAnalyticsPlayBlock | null;
}

export interface AnalyticsOrganizationOption extends AnalyticsOption {
  organization?: ApiOrganization;
}

import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';

// ─── Types ─────────────────────────────────────────────────

export interface DashboardKpis {
  active_events: number;
  active_events_subscription_covered: number;
  active_events_single_purchase: number;
  active_events_trial: number;
  active_events_bonus: number;
  photos_today: number;
  photos_approved_today: number;
  moderation_rate: number;
  games_played: number;
  hub_accesses: number;
  revenue_cents: number;
  subscription_revenue_cents: number;
  event_revenue_cents: number;
  pending_moderation: number;
  processing_errors: number;
  active_partners: number;
}

export interface DashboardChanges {
  photos_today_change: number;
  events_new_this_week: number;
  games_played_today: number;
}

export interface UploadsPerHourItem {
  hour: string;
  uploads: number;
}

export interface EventsByTypeItem {
  type: string;
  label: string;
  count: number;
  fill: string;
}

export interface EngagementItem {
  module: string;
  interactions: number;
  percentage: number;
}

export interface RecentEvent {
  id: number;
  uuid: string;
  title: string;
  slug: string;
  event_type: string;
  status: string;
  starts_at: string | null;
  cover_image_url: string | null;
  organization_name: string | null;
  photos_received: number;
}

export interface ModerationQueueItem {
  id: number;
  thumbnail_url: string | null;
}

export interface TopPartner {
  id: number;
  name: string;
  type: string;
  logo_url: string | null;
  active_events: number;
  active_subscription_events: number;
  active_paid_events: number;
  subscription_revenue: number;
  event_revenue: number;
  revenue: number;
}

export interface DashboardAlert {
  type: 'warning' | 'error' | 'info';
  icon: string;
  message: string;
  entity_type: string;
  entity_id: number | null;
}

export interface DashboardData {
  kpis: DashboardKpis;
  changes: DashboardChanges;
  charts: {
    uploads_per_hour: UploadsPerHourItem[];
    events_by_type: EventsByTypeItem[];
    engagement_by_module: EngagementItem[];
  };
  recent_events: RecentEvent[];
  moderation_queue: ModerationQueueItem[];
  top_partners: TopPartner[];
  alerts: DashboardAlert[];
}

// ─── Hook ──────────────────────────────────────────────────

export function dashboardStatsQueryOptions() {
  return {
    queryKey: queryKeys.analytics.dashboard(),
    queryFn: async () => {
      return api.get<DashboardData>('/dashboard/stats');
    },
    staleTime: 60_000,
    retry: 2,
  } as const;
}

export function useDashboardStats() {
  return useQuery<DashboardData>({
    ...dashboardStatsQueryOptions(),
    refetchInterval: 120_000,
  });
}

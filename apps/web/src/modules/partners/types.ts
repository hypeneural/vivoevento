import type { ApiPaginationMeta } from '@/lib/api-types';
import type { ClientItem } from '@/modules/clients/types';
import type { EventCommercialMode, EventListItem } from '@/modules/events/types';

export type PartnerStatus = 'active' | 'inactive' | 'suspended';
export type PartnerSubscriptionStatus = 'trialing' | 'active' | 'canceled' | 'suspended';
export type PartnerSortBy = 'created_at' | 'name' | 'revenue_cents' | 'active_events_count' | 'clients_count' | 'team_size';
export type SortDirection = 'asc' | 'desc';

export interface PartnerOwner {
  id: number | null;
  name: string | null;
  email: string | null;
  phone: string | null;
}

export interface PartnerSubscriptionSummary {
  plan_key: string | null;
  plan_name: string | null;
  status: string | null;
  billing_cycle: string | null;
}

export interface PartnerRevenueSummary {
  currency: string;
  subscription_cents: number;
  event_package_cents: number;
  total_cents: number;
}

export interface PartnerListItem {
  id: number;
  uuid: string;
  type: 'partner' | string;
  name: string;
  legal_name: string | null;
  trade_name: string | null;
  document_number: string | null;
  slug: string;
  email: string | null;
  billing_email: string | null;
  phone: string | null;
  logo_path: string | null;
  timezone: string | null;
  status: PartnerStatus | string;
  segment: string | null;
  notes: string | null;
  clients_count: number;
  events_count: number;
  active_events_count: number;
  team_size: number;
  active_bonus_grants_count: number;
  current_subscription: PartnerSubscriptionSummary;
  revenue: PartnerRevenueSummary;
  stats_refreshed_at: string | null;
  owner: PartnerOwner | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PartnerActivity {
  id: number;
  event: string | null;
  description: string | null;
  actor: {
    id: number | null;
    name: string | null;
    email: string | null;
  } | null;
  properties: Record<string, unknown>;
  created_at: string | null;
}

export interface PartnerDetailItem extends PartnerListItem {
  events_summary: {
    total: number;
    active: number;
    draft: number;
    bonus: number;
    manual_override: number;
    single_purchase: number;
    subscription_covered: number;
  };
  clients_summary: {
    total: number;
  };
  staff_summary: {
    total: number;
    owners: number;
  };
  grants_summary: {
    active_bonus: number;
    active_manual_override: number;
  };
  latest_activity: PartnerActivity[];
}

export interface PartnerStaffMember {
  id: number;
  role_key: string;
  is_owner: boolean;
  status: string;
  invited_at: string | null;
  joined_at: string | null;
  user: {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
  } | null;
}

export interface PartnerGrant {
  id: number;
  event_id: number;
  source_type: 'subscription' | 'event_purchase' | 'trial' | 'bonus' | 'manual_override' | string;
  status: string;
  priority: number;
  merge_strategy: string | null;
  notes: string | null;
  features: Record<string, unknown> | null;
  limits: Record<string, unknown> | null;
  starts_at: string | null;
  ends_at: string | null;
  event: {
    id: number;
    title: string;
    slug: string;
  } | null;
  granted_by: {
    id: number;
    name: string;
    email: string | null;
  } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PartnerListFilters {
  search?: string;
  status?: PartnerStatus;
  segment?: string;
  plan_code?: string;
  subscription_status?: PartnerSubscriptionStatus | string;
  has_active_events?: boolean;
  has_clients?: boolean;
  has_active_bonus_grants?: boolean;
  sort_by?: PartnerSortBy;
  sort_direction?: SortDirection;
  page?: number;
  per_page?: number;
}

export interface PartnerEventsFilters {
  search?: string;
  status?: string;
  event_type?: string;
  commercial_mode?: EventCommercialMode;
  sort_by?: string;
  sort_direction?: SortDirection;
  page?: number;
  per_page?: number;
}

export interface PartnerClientsFilters {
  search?: string;
  type?: string;
  has_events?: boolean;
  sort_by?: string;
  sort_direction?: SortDirection;
  page?: number;
  per_page?: number;
}

export interface PartnerStaffFilters {
  search?: string;
  role_key?: string;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface PartnerGrantsFilters {
  event_id?: number;
  source_type?: string;
  status?: string;
  page?: number;
  per_page?: number;
}

export interface PartnerActivityFilters {
  activity_event?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface PartnerFormPayload {
  name: string;
  legal_name?: string | null;
  document_number?: string | null;
  email?: string | null;
  billing_email?: string | null;
  phone?: string | null;
  timezone?: string | null;
  status?: PartnerStatus;
  segment?: string | null;
  notes?: string | null;
  owner?: {
    name: string;
    email: string;
    phone?: string | null;
    send_invite?: boolean;
  };
}

export interface PartnerSuspendPayload {
  reason: string;
  notes?: string | null;
}

export interface PartnerStaffPayload {
  user: {
    name: string;
    email: string;
    phone?: string | null;
  };
  role_key: string;
  is_owner?: boolean;
  send_invite?: boolean;
}

export interface PartnerGrantPayload {
  event_id: number;
  source_type: 'bonus' | 'manual_override' | 'trial';
  reason?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  features?: Record<string, unknown>;
  limits?: Record<string, unknown>;
}

export interface PaginatedPartnersResponse {
  success: boolean;
  data: PartnerListItem[];
  meta: ApiPaginationMeta;
}

export interface PaginatedPartnerEventsResponse {
  success: boolean;
  data: EventListItem[];
  meta: ApiPaginationMeta;
}

export interface PaginatedPartnerClientsResponse {
  success: boolean;
  data: ClientItem[];
  meta: ApiPaginationMeta;
}

export interface PaginatedPartnerStaffResponse {
  success: boolean;
  data: PartnerStaffMember[];
  meta: ApiPaginationMeta;
}

export interface PaginatedPartnerGrantsResponse {
  success: boolean;
  data: PartnerGrant[];
  meta: ApiPaginationMeta;
}

export interface PaginatedPartnerActivityResponse {
  success: boolean;
  data: PartnerActivity[];
  meta: ApiPaginationMeta;
}

export const PARTNER_STATUS_LABELS: Record<PartnerStatus, string> = {
  active: 'Ativo',
  inactive: 'Inativo',
  suspended: 'Suspenso',
};

export const PARTNER_SUBSCRIPTION_STATUS_LABELS: Record<PartnerSubscriptionStatus, string> = {
  trialing: 'Trial',
  active: 'Ativa',
  canceled: 'Cancelada',
  suspended: 'Suspensa',
};

export const PARTNER_SORT_OPTIONS: Array<{ value: PartnerSortBy; label: string }> = [
  { value: 'created_at', label: 'Cadastro' },
  { value: 'name', label: 'Nome' },
  { value: 'revenue_cents', label: 'Receita' },
  { value: 'active_events_count', label: 'Eventos ativos' },
  { value: 'clients_count', label: 'Clientes' },
  { value: 'team_size', label: 'Equipe' },
];

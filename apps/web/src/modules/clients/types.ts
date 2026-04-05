import type { ApiPaginationMeta, ApiOrganization } from '@/lib/api-types';

export type ClientType = 'pessoa_fisica' | 'empresa';
export type ClientSortBy = 'created_at' | 'name' | 'events_count';
export type SortDirection = 'asc' | 'desc';

export interface ClientItem {
  id: number;
  organization_id: number;
  type: ClientType | null;
  name: string;
  email: string | null;
  phone: string | null;
  document_number: string | null;
  notes: string | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  events_count?: number;
  organization_name?: string | null;
  organization_slug?: string | null;
  organization_status?: string | null;
  organization_billing?: {
    source: 'organization_subscription';
    plan_key: string | null;
    plan_name: string | null;
    subscription_status: string | null;
    billing_cycle: string | null;
    starts_at: string | null;
    trial_ends_at: string | null;
    renews_at: string | null;
    ends_at: string | null;
  } | null;
  plan_key?: string | null;
  plan_name?: string | null;
  subscription_status?: string | null;
}

export interface ClientFormPayload {
  organization_id?: number;
  type?: ClientType;
  name: string;
  email?: string | null;
  phone?: string | null;
  document_number?: string | null;
  notes?: string | null;
}

export interface ClientListFilters {
  organization_id?: number;
  search?: string;
  type?: ClientType;
  plan_code?: string;
  has_events?: boolean;
  sort_by?: ClientSortBy;
  sort_direction?: SortDirection;
  page?: number;
  per_page?: number;
}

export interface PaginatedClientsResponse {
  success: boolean;
  data: ClientItem[];
  meta: ApiPaginationMeta;
}

export interface PaginatedOrganizationsResponse {
  success: boolean;
  data: ApiOrganization[];
  meta: ApiPaginationMeta;
}

export interface ClientOrganizationOption {
  id: number;
  label: string;
  type: string;
  status: string;
}

export interface ClientPlanOption {
  id: number;
  code: string;
  name: string;
}

export const CLIENT_TYPE_LABELS: Record<ClientType, string> = {
  pessoa_fisica: 'Pessoa física',
  empresa: 'Empresa',
};

export const CLIENT_TYPE_OPTIONS = Object.entries(CLIENT_TYPE_LABELS).map(([value, label]) => ({
  value: value as ClientType,
  label,
}));

export const CLIENT_SORT_OPTIONS: Array<{ value: ClientSortBy; label: string }> = [
  { value: 'created_at', label: 'Data de cadastro' },
  { value: 'name', label: 'Nome do cliente' },
  { value: 'events_count', label: 'Eventos vinculados' },
];

export function formatOrganizationLabel(organization: ApiOrganization): string {
  return organization.trade_name || organization.legal_name || organization.slug;
}

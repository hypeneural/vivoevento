export interface AuditScopeMeta {
  is_global: boolean;
  organization_id: number | null;
  organization_name: string | null;
}

export interface AuditPaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id: string;
  scope: AuditScopeMeta;
}

export interface AuditActor {
  id: number;
  name: string;
  email: string | null;
}

export interface AuditSubject {
  type: string;
  type_label: string;
  id: number | null;
  label: string;
  route: string | null;
}

export interface AuditOrganization {
  id: number;
  name: string;
  slug: string | null;
}

export interface AuditRelatedEvent {
  id: number;
  title: string;
  slug: string | null;
  route: string | null;
}

export interface AuditChanges {
  count: number;
  fields: string[];
  old: Record<string, unknown> | null;
  new: Record<string, unknown> | null;
}

export interface AuditEntry {
  id: number;
  description: string;
  activity_event: string | null;
  category: string;
  severity: string;
  batch_uuid: string | null;
  actor: AuditActor | null;
  subject: AuditSubject;
  organization: AuditOrganization | null;
  related_event: AuditRelatedEvent | null;
  changes: AuditChanges;
  metadata: Record<string, unknown>;
  created_at: string;
}

export interface AuditListResponse {
  data: AuditEntry[];
  meta: AuditPaginationMeta;
}

export interface AuditFilterOption {
  key: string;
  label: string;
}

export interface AuditFiltersResponse {
  actors: AuditActor[];
  subject_types: AuditFilterOption[];
  activity_events: string[];
  scope: AuditScopeMeta;
}

export interface AuditListFilters {
  search?: string;
  actor_id?: number;
  subject_type?: string;
  activity_event?: string;
  batch_uuid?: string;
  has_changes?: boolean;
  date_from?: string;
  date_to?: string;
  page?: number;
  per_page?: number;
}

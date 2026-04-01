/**
 * API Types — TypeScript contracts aligned 1:1 with backend Resources.
 *
 * These types reflect EXACTLY what the Laravel API returns.
 * Internal app types in shared/types/index.ts may differ.
 */

// ─── Auth ──────────────────────────────────────────────────

/** POST /api/v1/auth/login — Request */
export interface LoginPayload {
  login: string;          // Phone or email
  password: string;
  device_name?: string;
}

/** POST /api/v1/auth/login — Response.data */
export interface LoginResponse {
  user: ApiUser;
  token: string;
}

/** POST /api/v1/auth/forgot-password — Request */
export interface ForgotPasswordPayload {
  login: string;
}

/** POST /api/v1/auth/forgot-password — Response.data */
export interface ForgotPasswordResponse {
  message: string;
  method: 'whatsapp' | 'email';
  expires_in?: number;
}

/** POST /api/v1/auth/reset-password — Request */
export interface ResetPasswordPayload {
  login: string;
  code: string;
  password: string;
  password_confirmation: string;
}

/** POST /api/v1/auth/reset-password — Response.data */
export interface ResetPasswordResponse {
  message: string;
  user: ApiUser;
  token: string;
}

// ─── User (UserResource) ──────────────────────────────────

export interface ApiUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_path: string | null;
  status: string;
  email_verified_at: string | null;
  last_login_at: string | null;
  created_at: string;
  roles?: string[];
  organizations?: ApiUserOrganization[];
}

export interface ApiUserOrganization {
  id: number;
  name: string;
  slug: string;
  role_key: string;
  is_owner: boolean;
}

// ─── /auth/me (MeResource) ────────────────────────────────

/** GET /api/v1/auth/me — full session payload */
export interface MeResponse {
  user: MeUser;
  organization: MeOrganization | null;
  access: MeAccess;
  subscription: MeSubscription | null;
}

export interface MeUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
  status: string;
  role: { key: string; name: string };
  permissions: string[];
  preferences: MePreferences;
  last_login_at: string | null;
}

export interface MePreferences {
  theme: 'light' | 'dark';
  timezone: string;
  locale: string;
}

export interface MeOrganization {
  id: number;
  uuid: string;
  type: string;
  name: string;
  slug: string;
  status: string;
  logo_url: string | null;
  branding: MeOrgBranding;
}

export interface MeOrgBranding {
  primary_color: string | null;
  secondary_color: string | null;
  subdomain: string | null;
  custom_domain: string | null;
}

export interface MeAccess {
  accessible_modules: string[];
  modules: Array<{ key: string; enabled: boolean; visible: boolean }>;
  feature_flags: Record<string, boolean>;
}

export interface MeSubscription {
  plan_key: string;
  plan_name: string;
  billing_type: string;
  status: string;
  trial_ends_at: string | null;
  renews_at: string | null;
}

// ─── Access Matrix ─────────────────────────────────────────

export interface AccessMatrixResponse {
  roles: Array<{ key: string; name: string }>;
  permissions: string[];
  modules: Array<{ key: string; enabled: boolean; visible: boolean }>;
  features: Record<string, boolean>;
}

// ─── Organization (OrganizationResource) ───────────────────

export interface ApiOrganization {
  id: number;
  uuid: string;
  type: string;
  legal_name: string | null;
  trade_name: string | null;
  slug: string;
  email: string | null;
  billing_email?: string | null;
  phone: string | null;
  logo_path: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  subdomain: string | null;
  custom_domain: string | null;
  timezone: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  document_number?: string | null;
  clients_count?: number;
  events_count?: number;
}

// ─── Event (EventResource) ─────────────────────────────────

export interface ApiEvent {
  id: number;
  uuid: string;
  organization_id: number;
  client_id: number | null;
  title: string;
  slug: string;
  upload_slug: string;
  event_type: string;
  status: string;
  visibility: string;
  moderation_mode: string;
  starts_at: string | null;
  ends_at: string | null;
  location_name: string | null;
  description: string | null;
  cover_image_path: string | null;
  logo_path: string | null;
  qr_code_path: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  public_url: string | null;
  upload_url: string | null;
  upload_api_url?: string | null;
  retention_days: number | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  client?: any;
  modules?: Array<{ module_key: string; is_enabled: boolean }>;
  channels?: any[];
  banners?: any[];
  team_members?: any[];
  media_count?: number;
}

// ─── Pagination ────────────────────────────────────────────

export interface PaginatedMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
}

// ─── Envelope ──────────────────────────────────────────────

export interface ApiEnvelope<T = unknown> {
  success: boolean;
  data: T;
  meta: { request_id: string } & Record<string, unknown>;
}

export interface ApiError {
  success: false;
  message: string;
  errors: Record<string, string[]> | null;
  meta: { request_id: string };
}

export interface PublicEventUploadBootstrap {
  event: {
    id: number;
    title: string;
    slug: string;
    upload_slug: string;
    cover_image_path: string | null;
    cover_image_url: string | null;
    logo_path: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    starts_at: string | null;
    location_name: string | null;
  };
  upload: {
    enabled: boolean;
    status: 'available' | 'disabled' | 'inactive';
    reason: string | null;
    message: string;
    accepts_multiple: boolean;
    max_files: number;
    max_file_size_mb: number;
    accept_hint: string;
    moderation_mode: 'auto' | 'manual' | string;
    instructions: string;
  };
  links: {
    upload_url: string;
    upload_api_url: string;
    hub_url: string | null;
  };
}

export interface PublicEventUploadResult {
  message: string;
  uploaded_count: number;
  media_ids: number[];
  moderation: string;
}

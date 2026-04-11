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

/** POST /api/v1/auth/register/request-otp — Request */
export interface RegisterRequestOtpPayload {
  name: string;
  phone: string;
  journey?: 'partner_signup' | 'trial_event' | 'single_event_checkout' | 'admin_assisted';
}

/** POST /api/v1/auth/register/request-otp — Response.data */
export interface RegisterRequestOtpResponse {
  message: string;
  session_token: string;
  delivery: 'whatsapp';
  phone_masked: string;
  expires_in: number;
  resend_in: number;
  debug_code?: string;
}

/** POST /api/v1/auth/register/resend-otp — Request */
export interface RegisterResendOtpPayload {
  session_token: string;
}

/** POST /api/v1/auth/register/resend-otp — Response.data */
export interface RegisterResendOtpResponse extends RegisterRequestOtpResponse {}

/** POST /api/v1/auth/register/verify-otp — Request */
export interface RegisterVerifyOtpPayload {
  session_token: string;
  code: string;
  device_name?: string;
}

/** POST /api/v1/auth/register/verify-otp — Response.data */
export interface RegisterVerifyOtpResponse {
  message: string;
  user: ApiUser;
  token: string;
  onboarding: {
    title: string;
    description: string;
    next_path: string;
  };
}

export interface PublicTrialEventPayload {
  responsible_name: string;
  whatsapp: string;
  email?: string | null;
  organization_name?: string | null;
  device_name?: string;
  event: {
    title: string;
    event_type: 'wedding' | 'birthday' | 'fifteen' | 'corporate' | 'fair' | 'graduation' | 'other';
    event_date?: string | null;
    city?: string | null;
    description?: string | null;
  };
}

export interface PublicTrialEventResponse {
  message: string;
  token: string;
  user: ApiUser;
  organization: ApiOrganization;
  event: ApiEvent;
  commercial_status: ApiEventCommercialStatus;
  trial: {
    grant_id: number;
    status: 'active' | 'pending' | 'expired' | 'revoked' | string | null;
    starts_at: string | null;
    ends_at: string | null;
    modules: {
      live: boolean;
      wall: boolean;
      play: boolean;
      hub: boolean;
    };
    limits: {
      retention_days: number;
      max_active_events: number;
      max_photos: number;
    };
    branding: {
      watermark: boolean;
    };
  };
  onboarding: {
    title: string;
    description: string;
    next_path: string;
  };
}

export interface PublicEventCheckoutPayload {
  responsible_name: string;
  whatsapp: string;
  email?: string | null;
  organization_name?: string | null;
  device_name?: string;
  package_id: number;
  payer?: {
    name?: string | null;
    email?: string | null;
    document?: string | null;
    document_type?: string | null;
    phone?: string | null;
    address?: {
      street?: string | null;
      number?: string | null;
      district?: string | null;
      complement?: string | null;
      zip_code?: string | null;
      city?: string | null;
      state?: string | null;
      country?: string | null;
    } | null;
  } | null;
  payment?: {
    method?: 'pix' | 'credit_card';
    pix?: {
      expires_in?: number | null;
    } | null;
    credit_card?: {
      installments?: number | null;
      statement_descriptor?: string | null;
      card_token?: string | null;
      billing_address?: {
        street?: string | null;
        number?: string | null;
        district?: string | null;
        complement?: string | null;
        zip_code?: string | null;
        city?: string | null;
        state?: string | null;
        country?: string | null;
      } | null;
    } | null;
  } | null;
  event: {
    title: string;
    event_type: 'wedding' | 'birthday' | 'fifteen' | 'corporate' | 'fair' | 'graduation' | 'other';
    event_date?: string | null;
    city?: string | null;
    description?: string | null;
  };
}

export interface PublicCheckoutIdentityCheckPayload {
  whatsapp: string;
  email?: string | null;
}

export interface PublicCheckoutIdentityCheckResponse {
  identity_status: 'new_account' | 'login_suggested' | 'authenticated_match' | 'authenticated_mismatch';
  title: string | null;
  description: string | null;
  action_label: string | null;
  login_url: string | null;
  cooldown_seconds: number | null;
}

export interface PublicEventCheckoutConfirmPayload {
  gateway_provider?: string | null;
  gateway_order_id?: string | null;
  confirmed_at?: string | null;
}

export interface PublicEventCheckoutResponse {
  message: string | null;
  token: string | null;
  user: ApiUser | null;
  organization: ApiOrganization | null;
  event: ApiEvent | null;
  commercial_status: ApiEventCommercialStatus | null;
  checkout: {
    id: number;
    uuid: string;
    mode: 'event_package';
    status: 'draft' | 'pending_payment' | 'paid' | 'canceled' | 'failed' | 'refunded';
    currency: string;
    total_cents: number;
    created_at: string | null;
    updated_at: string | null;
    confirmed_at: string | null;
    summary?: {
      state: 'idle' | 'pending' | 'processing' | 'paid' | 'failed' | 'refunded' | string;
      tone: 'idle' | 'info' | 'success' | 'warning' | 'error' | string;
      payment_status_title: string | null;
      order_status_label: string | null;
      payment_status_label: string | null;
      payment_status_description: string | null;
      next_action:
        | 'continue_checkout'
        | 'complete_payment'
        | 'wait_payment_confirmation'
        | 'open_event'
        | 'retry_payment'
        | 'contact_support'
        | string
        | null;
      expires_in_seconds: number | null;
      is_waiting_payment: boolean;
      can_retry: boolean;
    } | null;
    payment: {
      provider: string | null;
      method: 'pix' | 'credit_card' | string | null;
      gateway_order_id: string | null;
      gateway_charge_id: string | null;
      gateway_transaction_id: string | null;
      gateway_status: string | null;
      status: 'draft' | 'pending_payment' | 'paid' | 'canceled' | 'failed' | 'refunded' | string | null;
      checkout_url: string | null;
      confirm_url: string | null;
      expires_at: string | null;
      pix: {
        qr_code: string | null;
        qr_code_url: string | null;
        expires_at: string | null;
      } | null;
      credit_card: {
        installments: number | null;
        acquirer_message: string | null;
        acquirer_return_code: string | null;
        last_status: string | null;
      } | null;
      whatsapp: {
        pix_generated: {
          type: 'pix_generated' | string | null;
          status: string | null;
          recipient_phone: string | null;
          dispatched_at: string | null;
          failed_at: string | null;
          whatsapp_message_id: number | null;
          pix_button_message_id: number | null;
          pix_button_enabled: boolean | null;
          pix_button_value_source: string | null;
        } | null;
        payment_paid: {
          type: 'payment_paid' | string | null;
          status: string | null;
          recipient_phone: string | null;
          dispatched_at: string | null;
          failed_at: string | null;
          whatsapp_message_id: number | null;
          pix_button_message_id: number | null;
          pix_button_enabled: boolean | null;
          pix_button_value_source: string | null;
        } | null;
        payment_failed: {
          type: 'payment_failed' | string | null;
          status: string | null;
          recipient_phone: string | null;
          dispatched_at: string | null;
          failed_at: string | null;
          whatsapp_message_id: number | null;
          pix_button_message_id: number | null;
          pix_button_enabled: boolean | null;
          pix_button_value_source: string | null;
        } | null;
        payment_refunded: {
          type: 'payment_refunded' | string | null;
          status: string | null;
          recipient_phone: string | null;
          dispatched_at: string | null;
          failed_at: string | null;
          whatsapp_message_id: number | null;
          pix_button_message_id: number | null;
          pix_button_enabled: boolean | null;
          pix_button_value_source: string | null;
        } | null;
      };
    };
    package: {
      id: number;
      code: string;
      name: string;
      description: string | null;
      target_audience: 'direct_customer' | 'partner' | 'both' | string | null;
    } | null;
    items: Array<{
      id: number;
      item_type: string;
      reference_id: number | null;
      description: string | null;
      quantity: number;
      unit_amount_cents: number;
      total_amount_cents: number;
      snapshot: {
        package?: {
          id: number;
          code: string;
          name: string;
          description: string | null;
          target_audience: string | null;
        };
        price?: {
          id: number | null;
          billing_mode: string | null;
          currency: string | null;
          amount_cents: number | null;
        } | null;
        modules?: {
          live: boolean;
          wall: boolean;
          play: boolean;
          hub: boolean;
        };
        limits?: {
          retention_days: number | null;
          max_photos: number | null;
        };
        branding?: {
          watermark: boolean;
          white_label: boolean;
        };
        feature_map?: Record<string, unknown>;
      } | Record<string, unknown> | null;
    }>;
  };
  purchase: {
    id: number;
    status: string;
    package_id: number | null;
    price_snapshot_cents: number | null;
    currency: string | null;
    purchased_at: string | null;
  } | null;
  onboarding: {
    title: string;
    description: string;
    next_path: string;
  } | null;
}

export interface AdminQuickEventPayload {
  responsible_name: string;
  whatsapp: string;
  email?: string | null;
  organization_id?: number | null;
  organization_name?: string | null;
  organization_type?: 'partner' | 'direct_customer' | null;
  send_access?: boolean;
  event: {
    title: string;
    event_type: 'wedding' | 'birthday' | 'fifteen' | 'corporate' | 'fair' | 'graduation' | 'other';
    event_date?: string | null;
    city?: string | null;
    description?: string | null;
    visibility?: 'public' | 'private';
    moderation_mode?: 'none' | 'manual' | 'ai';
  };
  grant: {
    source_type: 'bonus' | 'manual_override';
    package_id: number;
    merge_strategy?: 'expand' | 'replace' | 'restrict';
    starts_at?: string | null;
    ends_at?: string | null;
    reason: string;
    origin?: string | null;
    notes?: string | null;
  };
}

export interface AdminQuickEventResponse {
  message: string | null;
  responsible_user: ApiUser | null;
  organization: ApiOrganization | null;
  event: ApiEvent | null;
  commercial_status: ApiEventCommercialStatus | null;
  grant: {
    id: number;
    source_type: 'bonus' | 'manual_override' | string | null;
    status: string | null;
    priority: number | null;
    merge_strategy: 'expand' | 'replace' | 'restrict' | string | null;
    package_id: number;
    package_code: string;
    package_name: string;
    starts_at: string | null;
    ends_at: string | null;
    reason: string;
    origin: string | null;
    notes: string | null;
    granted_by_user_id: number;
    granted_by_name: string;
  } | null;
  setup: {
    organization_reused: boolean;
    responsible_user_reused: boolean;
    membership_role_key: string;
    membership_is_owner: boolean;
  } | null;
  access_delivery: {
    requested: boolean;
    channel: 'whatsapp' | null;
    target: string | null;
    status: 'not_requested' | 'pending_not_implemented' | string;
  } | null;
  onboarding: {
    title: string;
    description: string;
    next_path: string;
  } | null;
}

export interface ApiPaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id?: string;
  [key: string]: unknown;
}

export interface ApiPlan {
  id: number;
  code: string;
  name: string;
  audience: string | null;
  status: string;
  description: string | null;
  prices: ApiPlanPrice[];
  features: ApiPlanFeature[];
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ApiPlanPrice {
  id: number;
  plan_id: number;
  billing_cycle: 'monthly' | 'yearly' | string;
  currency: string;
  amount_cents: number;
  gateway_provider?: string | null;
  gateway_price_id?: string | null;
  is_default: boolean;
}

export interface ApiPlanFeature {
  id: number;
  plan_id: number;
  feature_key: string;
  feature_value: string | null;
}

export interface ApiBillingSubscription {
  id: number;
  plan_key: string | null;
  plan_name: string | null;
  billing_cycle: string | null;
  status: string;
  contract_status?: string | null;
  billing_status?: string | null;
  access_status?: string | null;
  payment_method?: string | null;
  starts_at: string | null;
  trial_ends_at: string | null;
  current_period_started_at?: string | null;
  current_period_ends_at?: string | null;
  renews_at: string | null;
  next_billing_at?: string | null;
  ends_at: string | null;
  canceled_at: string | null;
  cancel_at_period_end: boolean;
  cancellation_effective_at: string | null;
  gateway_provider?: string | null;
  gateway_subscription_id?: string | null;
  gateway_customer_id?: string | null;
  gateway_card_id?: string | null;
  features: Record<string, string | null>;
}

export interface ApiBillingCard {
  id: string;
  brand: string | null;
  holder_name: string | null;
  last_four: string | null;
  exp_month: number | null;
  exp_year: number | null;
  status: string | null;
  is_default: boolean;
  label: string;
}

export interface ApiBillingInvoice {
  id: number;
  invoice_number: string | null;
  status: string | null;
  amount_cents: number;
  currency: string;
  issued_at: string | null;
  due_at: string | null;
  paid_at: string | null;
  order: {
    id: number;
    uuid: string;
    mode: 'subscription' | 'event_package' | string;
    status: string | null;
  } | null;
  event: {
    id: number;
    title: string;
  } | null;
  package: {
    id?: number | null;
    code?: string | null;
    name?: string | null;
    description?: string | null;
  } | null;
  plan: {
    id?: number | null;
    code?: string | null;
    name?: string | null;
    audience?: string | null;
    description?: string | null;
  } | null;
  payment: {
    id: number;
    status: string | null;
    amount_cents: number;
    currency: string;
    gateway_provider: string | null;
    gateway_payment_id: string | null;
    paid_at: string | null;
  } | null;
  snapshot: Record<string, unknown>;
}

export interface ApiBillingCheckoutResponse {
  subscription_id: number | null;
  plan_name: string;
  status: string;
  starts_at: string | null;
  renews_at: string | null;
  billing_order_id: number;
  payment_id: number | null;
  invoice_id: number | null;
  checkout: {
    provider: string | null;
    gateway_order_id: string | null;
    status: string | null;
    checkout_url: string | null;
    confirm_url: string | null;
    expires_at: string | null;
  };
}

export interface ApiBillingCancelSubscriptionResponse {
  message: string;
  cancel_effective: 'period_end' | 'immediately';
  access_until: string | null;
  subscription: ApiBillingSubscription;
}

export interface ApiBillingUpdateCardResponse {
  message: string;
  subscription: ApiBillingSubscription;
}

export interface ApiBillingReconcileResponse {
  provider_key: string;
  subscription_id: number;
  gateway_subscription_id: string;
  cycles_reconciled: number;
  invoices_reconciled: number;
  charges_reconciled: number;
  charge_details_loaded: number;
  page: number;
  size: number;
  subscription: ApiBillingSubscription;
}

/** POST /api/v1/auth/forgot-password — Request */
export interface ForgotPasswordPayload {
  login: string;
}

/** POST /api/v1/auth/forgot-password — Response.data */
export interface ForgotPasswordResponse {
  message: string;
  session_token?: string;
  method: 'whatsapp' | 'email';
  destination_masked?: string;
  expires_in?: number;
  resend_in?: number;
  debug_code?: string;
}

export interface ForgotPasswordRequestOtpResponse extends ForgotPasswordResponse {
  session_token: string;
  destination_masked: string;
  expires_in: number;
  resend_in: number;
}

/** POST /api/v1/auth/forgot-password/resend-otp — Request */
export interface ForgotPasswordResendOtpPayload {
  session_token: string;
}

/** POST /api/v1/auth/forgot-password/resend-otp — Response.data */
export interface ForgotPasswordResendOtpResponse extends ForgotPasswordRequestOtpResponse {}

/** POST /api/v1/auth/forgot-password/verify-otp — Request */
export interface ForgotPasswordVerifyOtpPayload {
  session_token: string;
  code: string;
}

/** POST /api/v1/auth/forgot-password/verify-otp — Response.data */
export interface ForgotPasswordVerifyOtpResponse {
  message: string;
  session_token: string;
  method: 'whatsapp' | 'email';
  destination_masked: string;
}

/** POST /api/v1/auth/reset-password — Request */
export interface ResetPasswordPayload {
  login: string;
  code: string;
  password: string;
  password_confirmation: string;
  device_name?: string;
}

/** POST /api/v1/auth/reset-password — Request for OTP-verified reset */
export interface ResetPasswordWithOtpPayload {
  session_token: string;
  password: string;
  password_confirmation: string;
  device_name?: string;
}

/** POST /api/v1/auth/reset-password — Response.data */
export interface ResetPasswordResponse {
  message: string;
  user: ApiUser;
  token: string;
}

/** PATCH /api/v1/auth/me/password — Request */
export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/** Generic success payload with a message */
export interface MessageResponse {
  message: string;
}

/** POST /api/v1/auth/me/avatar — Response.data */
export interface AvatarUploadResponse {
  avatar_path: string;
  avatar_url: string;
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
  active_context: MeActiveContext | null;
  workspaces: MeWorkspaces;
  access: MeAccess;
  subscription: MeSubscription | null;
}

export interface MeActiveContext {
  type: 'organization' | 'event';
  organization_id: number | null;
  event_id: number | null;
  role_key: string;
  role_label: string;
  capabilities: string[];
  entry_path: string;
}

export interface MeWorkspaces {
  organizations: MeOrganizationWorkspace[];
  event_accesses: MeEventAccessWorkspace[];
}

export interface MeOrganizationWorkspace {
  organization_id: number;
  organization_uuid: string;
  organization_name: string;
  organization_slug: string;
  organization_type: string | null;
  organization_status: string | null;
  role_key: string;
  role_label: string;
  is_owner: boolean;
  entry_path: string;
}

export interface MeEventAccessWorkspace {
  event_id: number;
  event_uuid: string;
  event_title: string;
  event_slug: string;
  event_date: string | null;
  event_status: string | null;
  organization_id: number;
  organization_name: string;
  organization_slug: string | null;
  role_key: string;
  role_label: string;
  persisted_role: string;
  capabilities: string[];
  entry_path: string;
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
  email_notifications: boolean;
  push_notifications: boolean;
  compact_mode: boolean;
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
  logo_path?: string | null;
  logo_url?: string | null;
  logo_dark_path?: string | null;
  logo_dark_url?: string | null;
  favicon_path?: string | null;
  favicon_url?: string | null;
  watermark_path?: string | null;
  watermark_url?: string | null;
  cover_path?: string | null;
  cover_url?: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  subdomain: string | null;
  custom_domain: string | null;
}

export interface MeAccess {
  accessible_modules: string[];
  modules: Array<{ key: string; enabled: boolean; visible: boolean }>;
  feature_flags: Record<string, boolean>;
  entitlements: MeResolvedEntitlements;
}

export interface MeResolvedEntitlements {
  version: number;
  organization_type: string | null;
  modules: {
    live_gallery: boolean;
    wall: boolean;
    play: boolean;
    hub: boolean;
    whatsapp_ingestion: boolean;
    analytics_advanced: boolean;
  };
  limits: {
    max_active_events: number | null;
    retention_days: number | null;
  };
  branding: {
    white_label: boolean;
    custom_domain: boolean;
    expanded_assets?: boolean;
    watermark?: boolean;
  };
  source_summary: Array<{
    source_type: 'subscription';
    status: string;
    plan_id: number | null;
    plan_key: string | null;
    plan_name: string | null;
    billing_cycle: string | null;
    starts_at: string | null;
    trial_ends_at: string | null;
    renews_at: string | null;
    ends_at: string | null;
    canceled_at: string | null;
    cancel_at_period_end: boolean;
    cancellation_effective_at: string | null;
    active: boolean;
  }>;
}

export interface MeSubscription {
  plan_key: string;
  plan_name: string;
  billing_type: string;
  status: string;
  trial_ends_at: string | null;
  renews_at: string | null;
  ends_at: string | null;
  canceled_at: string | null;
  cancel_at_period_end: boolean;
  cancellation_effective_at: string | null;
}

export interface ApiEventPackage {
  id: number;
  code: string;
  name: string;
  description: string | null;
  target_audience: 'direct_customer' | 'partner' | 'both';
  is_active: boolean;
  sort_order: number;
  default_price: ApiEventPackagePrice | null;
  prices: ApiEventPackagePrice[];
  features: ApiEventPackageFeature[];
  feature_map: Record<string, unknown>;
  checkout_marketing?: {
    slug: string;
    subtitle: string;
    ideal_for: string;
    benefits: string[];
    badge: string | null;
    recommended: boolean;
  } | null;
  modules: {
    hub: boolean;
    wall: boolean;
    play: boolean;
  };
  limits: {
    retention_days: number | null;
    max_photos: number | null;
  };
}

export interface ApiEventPackagePrice {
  id: number;
  billing_mode: 'one_time';
  currency: string;
  amount_cents: number;
  is_active: boolean;
  is_default: boolean;
}

export interface ApiEventPackageFeature {
  id: number;
  feature_key: string;
  feature_value: string | null;
}

// ─── Access Matrix ─────────────────────────────────────────

export interface AccessMatrixResponse {
  roles: Array<{ key: string; name: string }>;
  permissions: string[];
  modules: Array<{ key: string; enabled: boolean; visible: boolean }>;
  features: Record<string, boolean>;
  entitlements: MeResolvedEntitlements;
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
  moderation_mode: 'none' | 'manual' | 'ai' | string;
  commercial_mode?: 'none' | 'subscription_covered' | 'trial' | 'single_purchase' | 'bonus' | 'manual_override';
  starts_at: string | null;
  ends_at: string | null;
  location_name: string | null;
  description: string | null;
  cover_image_path: string | null;
  cover_image_url?: string | null;
  logo_path: string | null;
  logo_url?: string | null;
  qr_code_path: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  inherit_branding: boolean;
  effective_branding: ApiEventEffectiveBranding | null;
  public_url: string | null;
  upload_url: string | null;
  upload_api_url?: string | null;
  retention_days: number | null;
  current_entitlements?: Record<string, unknown> | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  organization_name?: string | null;
  organization_slug?: string | null;
  client_name?: string | null;
  content_moderation?: ApiEventContentModerationSettings | null;
  face_search?: ApiEventFaceSearchSettings | null;
  media_intelligence?: ApiEventMediaIntelligenceSettings | null;
  enabled_modules?: string[];
  module_count?: number;
  wall?: ApiEventWallSummary | null;
  client?: any;
  modules?: Array<{ module_key: string; is_enabled: boolean }>;
  channels?: any[];
  banners?: any[];
  team_members?: any[];
  media_count?: number;
}

export type ApiEventEffectiveBrandingSource = 'organization' | 'event' | 'mixed';

export interface ApiEventEffectiveBranding {
  logo_path: string | null;
  logo_url: string | null;
  cover_image_path: string | null;
  cover_image_url: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  source: ApiEventEffectiveBrandingSource;
  inherits_from_organization: boolean;
}

export interface ApiEventWallSummary {
  id: number;
  wall_code: string | null;
  is_enabled: boolean;
  status: string | null;
  public_url: string | null;
}

export interface ApiEventFaceSearchOperationalSummary {
  status: 'disabled' | 'local_only' | 'provisioning' | 'converging' | 'ready_for_internal_validation' | 'ready_for_guests' | string;
  search_mode: 'faces' | 'users' | string;
  collection_ready: boolean;
  catalog_ready: boolean;
  is_converging: boolean;
  internal_search_ready: boolean;
  guest_search_ready: boolean;
  requires_attention: boolean;
  counts: {
    total_media: number;
    queued_media: number;
    processing_media: number;
    indexed_media: number;
    failed_media: number;
    skipped_media: number;
    searchable_records: number;
    distinct_ready_users: number;
  };
}

export interface ApiEventFaceSearchSettings {
  id: number | null;
  event_id: number;
  provider_key: 'noop' | 'compreface' | string;
  embedding_model_key: string;
  vector_store_key: 'pgvector' | string;
  search_strategy: 'exact' | 'ann' | string;
  enabled: boolean;
  min_face_size_px: number;
  min_quality_score: number;
  search_threshold: number;
  top_k: number;
  allow_public_selfie_search: boolean;
  selfie_retention_hours: number;
  recognition_enabled: boolean;
  search_backend_key: 'local_pgvector' | 'aws_rekognition' | 'luxand_managed' | string;
  fallback_backend_key: 'local_pgvector' | 'aws_rekognition' | 'luxand_managed' | string | null;
  routing_policy: 'local_only' | 'aws_primary_local_fallback' | 'aws_primary_local_shadow' | 'local_primary_aws_on_error' | string;
  shadow_mode_percentage: number;
  aws_region: string;
  aws_collection_id: string | null;
  aws_collection_arn: string | null;
  aws_face_model_version: string | null;
  aws_search_mode: 'faces' | 'users' | string;
  aws_index_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE' | string;
  aws_search_faces_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE' | string;
  aws_search_users_quality_filter: 'AUTO' | 'LOW' | 'MEDIUM' | 'HIGH' | 'NONE' | string;
  aws_search_face_match_threshold: number;
  aws_search_user_match_threshold: number;
  aws_associate_user_match_threshold: number;
  aws_max_faces_per_image: number;
  aws_index_profile_key: string;
  aws_detection_attributes_json: string[];
  delete_remote_vectors_on_event_close: boolean;
  operational_summary?: ApiEventFaceSearchOperationalSummary | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ApiEventFaceSearchHealthCheck {
  backend_key: string;
  status: string;
  checked_at: string;
  required_actions?: string[];
  checks?: Record<string, string>;
  identity?: {
    account?: string | null;
    arn?: string | null;
    user_id?: string | null;
  } | null;
  collection?: {
    collection_id?: string | null;
    collection_arn?: string | null;
    face_model_version?: string | null;
    face_count?: number | null;
  } | null;
  error_code?: string | null;
  error_message?: string | null;
}

export interface ApiEventFaceSearchOperationResponse {
  status: string;
  backend_key: string;
  queued_media_count?: number;
  job?: string;
  collection_id?: string | null;
  skipped_reason?: string | null;
}

export interface ApiEventContentModerationSettings {
  id: number | null;
  event_id: number;
  enabled: boolean;
  provider_key: 'openai' | 'noop' | string;
  mode: 'enforced' | 'observe_only' | string | null;
  threshold_version: string | null;
  hard_block_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  review_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  fallback_mode: 'review' | 'block' | string;
  created_at: string | null;
  updated_at: string | null;
}

export interface ApiEventMediaIntelligenceSettings {
  id: number | null;
  event_id: number;
  enabled: boolean;
  provider_key: 'vllm' | 'openrouter' | 'noop' | string;
  model_key: string;
  mode: 'enrich_only' | 'gate' | string;
  prompt_version: string | null;
  approval_prompt: string | null;
  caption_style_prompt: string | null;
  response_schema_version: string | null;
  timeout_ms: number;
  fallback_mode: 'review' | 'skip' | string;
  require_json_output: boolean;
  reply_text_mode: 'disabled' | 'ai' | 'fixed_random' | string;
  reply_text_enabled: boolean;
  reply_prompt_override: string | null;
  reply_fixed_templates: string[];
  reply_prompt_preset_id: number | null;
  reply_prompt_preset?: {
    id: number;
    slug: string;
    name: string;
    category: string | null;
    description: string | null;
    prompt_template: string;
    sort_order: number;
    is_active: boolean;
    created_by: number | null;
    created_at: string | null;
    updated_at: string | null;
  } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ApiEventModuleFlags {
  live: boolean;
  wall: boolean;
  play: boolean;
  hub: boolean;
}

export interface ApiEventMenuItem {
  key: 'overview' | 'uploads' | 'moderation' | 'gallery' | 'wall' | 'play' | 'hub' | 'analytics';
  label: string;
  visible: boolean;
}

export interface ApiEventStats {
  media_total: number;
  media_pending: number;
  media_approved: number;
  media_published: number;
  active_modules: number;
}

export interface ApiEventPublicLink {
  key: 'gallery' | 'upload' | 'wall' | 'hub' | 'play' | 'find_me';
  label: string;
  enabled: boolean;
  identifier_type: 'slug' | 'upload_slug' | 'wall_code';
  identifier: string | null;
  url: string | null;
  api_url: string | null;
  qr_value: string | null;
}

export interface ApiEventPublicIdentifier {
  value: string | null;
  editable: boolean;
  regenerates: Array<'gallery' | 'upload' | 'wall' | 'hub' | 'play' | 'find_me'>;
}

export interface ApiEventPublicLinksPayload {
  links: Record<'gallery' | 'upload' | 'wall' | 'hub' | 'play' | 'find_me', ApiEventPublicLink>;
  identifiers: Record<'slug' | 'upload_slug' | 'wall_code', ApiEventPublicIdentifier>;
}

export type ApiHubLayoutKey = 'classic-cover' | 'hero-cards' | 'minimal-center';

export type ApiHubThemeKey = 'midnight' | 'sunset' | 'pearl' | 'wedding' | 'quince' | 'corporate';

export type ApiHubBlockKey = 'hero' | 'meta_cards' | 'welcome' | 'countdown' | 'info_grid' | 'cta_list' | 'social_strip' | 'sponsor_strip';

export type ApiHubSocialProviderKey =
  | 'instagram'
  | 'whatsapp'
  | 'tiktok'
  | 'youtube'
  | 'spotify'
  | 'website'
  | 'map'
  | 'tickets';

export interface ApiHubThemeTokens {
  page_background: string;
  page_accent: string;
  surface_background: string;
  surface_border: string;
  text_primary: string;
  text_secondary: string;
  hero_overlay_color: string;
}

export interface ApiHubHeroBlockConfig {
  enabled: boolean;
  show_logo: boolean;
  show_badge: boolean;
  show_meta_cards: boolean;
  height: 'sm' | 'md' | 'lg';
  overlay_opacity: number;
}

export interface ApiHubMetaCardsBlockConfig {
  enabled: boolean;
  show_date: boolean;
  show_location: boolean;
  style: 'glass' | 'solid' | 'minimal';
}

export interface ApiHubWelcomeBlockConfig {
  enabled: boolean;
  style: 'card' | 'inline' | 'bubble';
}

export interface ApiHubCtaListBlockConfig {
  enabled: boolean;
  style: 'solid' | 'outline' | 'soft';
  size: 'sm' | 'md' | 'lg';
  icon_position: 'left' | 'top';
}

export interface ApiHubCountdownBlockConfig {
  enabled: boolean;
  style: 'cards' | 'inline' | 'minimal';
  target_mode: 'event_start' | 'custom';
  target_at: string | null;
  title: string;
  completed_message: string;
  hide_after_start: boolean;
}

export interface ApiHubInfoGridItem {
  id: string;
  title: string;
  value: string;
  description: string | null;
  icon: HubButtonIconKey;
  is_visible: boolean;
}

export interface ApiHubInfoGridBlockConfig {
  enabled: boolean;
  title: string;
  style: 'cards' | 'minimal' | 'highlight';
  columns: 2 | 3;
  items: ApiHubInfoGridItem[];
}

export interface ApiHubSocialItem {
  id: string;
  provider: ApiHubSocialProviderKey;
  label: string;
  href: string | null;
  icon: HubButtonIconKey;
  is_visible: boolean;
  opens_in_new_tab: boolean;
}

export interface ApiHubSocialStripBlockConfig {
  enabled: boolean;
  style: 'icons' | 'chips' | 'cards';
  size: 'sm' | 'md' | 'lg';
  items: ApiHubSocialItem[];
}

export interface ApiHubSponsorItem {
  id: string;
  name: string;
  subtitle: string | null;
  logo_path: string | null;
  href: string | null;
  is_visible: boolean;
  opens_in_new_tab: boolean;
}

export interface ApiHubSponsorStripBlockConfig {
  enabled: boolean;
  title: string;
  style: 'logos' | 'cards' | 'compact';
  items: ApiHubSponsorItem[];
}

export interface ApiHubBuilderConfig {
  version: 1;
  layout_key: ApiHubLayoutKey;
  theme_key: ApiHubThemeKey;
  theme_tokens: ApiHubThemeTokens;
  block_order: ApiHubBlockKey[];
  blocks: {
    hero: ApiHubHeroBlockConfig;
    meta_cards: ApiHubMetaCardsBlockConfig;
    welcome: ApiHubWelcomeBlockConfig;
    countdown: ApiHubCountdownBlockConfig;
    info_grid: ApiHubInfoGridBlockConfig;
    cta_list: ApiHubCtaListBlockConfig;
    social_strip: ApiHubSocialStripBlockConfig;
    sponsor_strip: ApiHubSponsorStripBlockConfig;
  };
}

export interface ApiEventDetail extends ApiEvent {
  module_flags: ApiEventModuleFlags;
  menu: ApiEventMenuItem[];
  stats: ApiEventStats;
  public_links: Record<'gallery' | 'upload' | 'wall' | 'hub' | 'play' | 'find_me', ApiEventPublicLink>;
  public_identifiers: Record<'slug' | 'upload_slug' | 'wall_code', ApiEventPublicIdentifier>;
  wall?: ApiEventWallSummary | null;
  play?: {
    id: number;
    is_enabled: boolean;
    memory_enabled: boolean;
    puzzle_enabled: boolean;
    ranking_enabled: boolean;
  } | null;
  hub?: {
    id: number;
    is_enabled: boolean;
    headline: string | null;
    subheadline: string | null;
    welcome_text?: string | null;
    hero_image_path?: string | null;
    hero_image_url?: string | null;
    button_style?: ApiHubButtonStyle;
    buttons?: ApiHubButton[];
    builder_config?: ApiHubBuilderConfig;
    show_gallery_button: boolean;
    show_upload_button: boolean;
    show_wall_button: boolean;
    show_play_button: boolean;
  } | null;
}

export interface ApiEventCommercialStatus {
  event_id: number;
  commercial_mode: 'none' | 'subscription_covered' | 'trial' | 'single_purchase' | 'bonus' | 'manual_override';
  subscription_summary: {
    source_type: 'subscription';
    status: string;
    plan_id: number;
    plan_key: string | null;
    plan_name: string | null;
    billing_cycle: string | null;
    starts_at: string | null;
    trial_ends_at: string | null;
    renews_at: string | null;
    ends_at: string | null;
  } | null;
  purchase_summary: {
    source_type: 'event_purchase';
    catalog_type: 'event_package' | 'legacy_plan';
    status: string;
    plan_id: number | null;
    plan_key: string | null;
    plan_name: string | null;
    package_id: number | null;
    package_code: string | null;
    package_name: string | null;
    price_snapshot_cents: number | null;
    currency: string | null;
    purchased_at: string | null;
  } | null;
  grants_summary: Array<{
    id: number;
    source_type: 'subscription' | 'event_purchase' | 'trial' | 'bonus' | 'manual_override' | null;
    source_id: number | null;
    package_id: number | null;
    status: string | null;
    priority: number | null;
    merge_strategy: 'expand' | 'replace' | 'restrict' | null;
    starts_at: string | null;
    ends_at: string | null;
    granted_by_user_id: number | null;
    granted_by_name: string | null;
    notes: string | null;
    active: boolean;
  }>;
  event_modules: {
    live: boolean;
    wall: boolean;
    play: boolean;
    hub: boolean;
  };
  resolved_entitlements: Record<string, unknown>;
}

export type HubButtonIconKey =
  | 'camera'
  | 'image'
  | 'monitor'
  | 'gamepad'
  | 'link'
  | 'calendar'
  | 'map-pin'
  | 'ticket'
  | 'music'
  | 'gift'
  | 'sparkles'
  | 'message-circle'
  | 'instagram';

export interface ApiHubButtonStyle {
  background_color: string;
  text_color: string;
  outline_color: string;
}

export interface ApiHubButton {
  id: string;
  type: 'preset' | 'custom';
  preset_key: 'upload' | 'gallery' | 'wall' | 'play' | null;
  label: string;
  icon: HubButtonIconKey;
  href: string | null;
  resolved_url: string | null;
  is_visible: boolean;
  is_available: boolean;
  opens_in_new_tab: boolean;
  background_color: string | null;
  text_color: string | null;
  outline_color: string | null;
  sort_order: number;
}

export interface ApiHubIconOption {
  value: HubButtonIconKey;
  label: string;
}

export interface ApiHubPresetActionOption {
  preset_key: 'upload' | 'gallery' | 'wall' | 'play';
  label: string;
  icon: HubButtonIconKey;
  description: string;
  is_available: boolean;
  resolved_url: string | null;
}

export interface ApiHubButtonInsight {
  button_id: string;
  label: string;
  type: 'preset' | 'custom' | 'social' | 'sponsor';
  preset_key: 'upload' | 'gallery' | 'wall' | 'play' | null;
  icon: HubButtonIconKey;
  resolved_url: string | null;
  is_visible: boolean;
  clicks: number;
  last_clicked_at: string | null;
}

export interface ApiHubTimelinePoint {
  date: string;
  page_views: number;
  button_clicks: number;
  ctr: number;
}

export interface ApiEventHubInsightsResponse {
  summary: {
    page_views: number;
    unique_visitors: number;
    button_clicks: number;
    ctr: number;
    active_buttons: number;
    last_activity_at: string | null;
  };
  buttons: ApiHubButtonInsight[];
  top_buttons: ApiHubButtonInsight[];
  timeline: ApiHubTimelinePoint[];
  window_days: 7 | 30 | 90;
  generated_at: string;
}

export interface ApiEventHubSettingsResponse {
  event: ApiEvent;
  links: {
    hub_url: string | null;
    hub_api_url: string | null;
    gallery_url: string | null;
    upload_url: string | null;
    wall_url: string | null;
    play_url: string | null;
  };
  settings: {
    id: number;
    event_id: number;
    is_enabled: boolean;
    headline: string | null;
    subheadline: string | null;
    welcome_text: string | null;
    hero_image_path: string | null;
    hero_image_url: string | null;
    button_style: ApiHubButtonStyle;
    buttons: ApiHubButton[];
    builder_config: ApiHubBuilderConfig;
    sponsor: unknown[];
    extra_links: unknown[];
    created_at: string | null;
    updated_at: string | null;
  };
  options: {
    icons: ApiHubIconOption[];
    preset_actions: ApiHubPresetActionOption[];
  };
}

export interface ApiPublicHubResponse {
  event: {
    id: number;
    title: string;
    slug: string;
    starts_at: string | null;
    location_name: string | null;
    description: string | null;
    cover_image_path: string | null;
    cover_image_url: string | null;
    logo_path: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    public_url: string | null;
  };
  hub: {
    headline: string | null;
    subheadline: string | null;
    welcome_text: string | null;
    hero_image_url: string | null;
    button_style: ApiHubButtonStyle;
    builder_config: ApiHubBuilderConfig;
    buttons: ApiHubButton[];
  };
  face_search: {
    public_search_enabled: boolean;
    find_me_url: string | null;
    gallery_url: string | null;
  };
}

export interface ApiHubHeroUploadResponse {
  path: string;
  url: string;
}

export interface ApiHubPreset {
  id: number;
  organization_id: number;
  name: string;
  description: string | null;
  theme_key: ApiHubThemeKey;
  layout_key: ApiHubLayoutKey;
  source_event: {
    id: number;
    title: string;
    slug: string;
  } | null;
  creator: {
    id: number;
    name: string;
  } | null;
  payload: {
    button_style: ApiHubButtonStyle;
    builder_config: ApiHubBuilderConfig;
    buttons: ApiHubButton[];
  };
  created_at: string | null;
  updated_at: string | null;
}

export type ApiWallLayout =
  | 'auto'
  | 'polaroid'
  | 'fullscreen'
  | 'split'
  | 'cinematic'
  | 'kenburns'
  | 'spotlight'
  | 'gallery'
  | 'carousel'
  | 'mosaic'
  | 'grid'
  | 'puzzle';

export type ApiWallTransition =
  | 'fade'
  | 'slide'
  | 'zoom'
  | 'flip'
  | 'lift-fade'
  | 'cross-zoom'
  | 'swipe-up'
  | 'none';
export type ApiWallTransitionMode = 'fixed' | 'random';
export type ApiWallThemePreset = 'compact' | 'standard';
export type ApiWallThemeAnchorMode = 'event_brand' | 'qr_prompt' | 'none';
export type ApiWallThemeBurstIntensity = 'gentle' | 'normal';
export type ApiWallThemeVideoBehavior = 'fallback_single_item';

export interface ApiWallThemeConfig {
  preset?: ApiWallThemePreset;
  anchor_mode?: ApiWallThemeAnchorMode;
  burst_intensity?: ApiWallThemeBurstIntensity;
  hero_enabled?: boolean;
  video_behavior?: ApiWallThemeVideoBehavior;
}

export interface ApiWallSettings {
  interval_ms: number;
  queue_limit: number;
  selection_mode: ApiWallSelectionMode;
  event_phase: ApiWallEventPhase;
  selection_policy: ApiWallSelectionPolicy;
  theme_config: ApiWallThemeConfig;
  layout: ApiWallLayout;
  transition_effect: ApiWallTransition;
  transition_mode?: ApiWallTransitionMode;
  transition_pool?: ApiWallTransition[] | null;
  background_url: string | null;
  partner_logo_url: string | null;
  show_qr: boolean;
  show_branding: boolean;
  show_neon: boolean;
  neon_text: string | null;
  neon_color: string | null;
  show_sender_credit: boolean;
  show_side_thumbnails: boolean;
  accepted_orientation: 'all' | 'landscape' | 'portrait';
  video_enabled: boolean;
  public_upload_video_enabled?: boolean;
  private_inbound_video_enabled?: boolean;
  video_playback_mode: ApiWallVideoPlaybackMode;
  video_max_seconds: number;
  video_resume_mode: ApiWallVideoResumeMode;
  video_audio_policy: ApiWallVideoAudioPolicy;
  video_multi_layout_policy: ApiWallVideoMultiLayoutPolicy;
  video_preferred_variant: ApiWallVideoPreferredVariant;
  ad_mode?: ApiWallAdMode;
  ad_frequency?: number;
  ad_interval_minutes?: number;
  instructions_text: string | null;
}

export type ApiWallAdMode = 'disabled' | 'by_photos' | 'by_minutes';
export type ApiWallVideoPlaybackMode = 'fixed_interval' | 'play_to_end' | 'play_to_end_if_short_else_cap';
export type ApiWallVideoResumeMode = 'resume_if_same_item' | 'restart_from_zero' | 'resume_if_same_item_else_restart';
export type ApiWallVideoAudioPolicy = 'muted';
export type ApiWallVideoMultiLayoutPolicy = 'disallow' | 'one' | 'all';
export type ApiWallVideoPreferredVariant = 'wall_video_720p' | 'wall_video_1080p' | 'original';
export type ApiWallVideoAdmissionState = 'eligible' | 'eligible_with_fallback' | 'blocked';

export interface ApiWallVideoAdmission {
  state: ApiWallVideoAdmissionState;
  reasons: string[];
  has_minimum_metadata: boolean;
  supported_format: boolean;
  preferred_variant_available: boolean;
  preferred_variant_key?: string | null;
  poster_available: boolean;
  poster_variant_key?: string | null;
  asset_source: 'wall_variant' | 'original';
  duration_limit_seconds: number;
}

export interface ApiWallAdItem {
  id: number;
  url: string | null;
  media_type: 'image' | 'video';
  duration_seconds: number;
  position: number;
  is_active?: boolean;
  created_at?: string | null;
}

export type ApiWallSelectionMode =
  | 'balanced'
  | 'live'
  | 'inclusive'
  | 'editorial'
  | 'custom';

export type ApiWallEventPhase =
  | 'reception'
  | 'flow'
  | 'party'
  | 'closing';

export interface ApiWallSelectionPolicy {
  max_eligible_items_per_sender: number;
  max_replays_per_item: number;
  low_volume_max_items: number;
  medium_volume_max_items: number;
  replay_interval_low_minutes: number;
  replay_interval_medium_minutes: number;
  replay_interval_high_minutes: number;
  sender_cooldown_seconds: number;
  sender_window_limit: number;
  sender_window_minutes: number;
  avoid_same_sender_if_alternative_exists: boolean;
  avoid_same_duplicate_cluster_if_alternative_exists: boolean;
}

export type ApiWallPersistentStorage =
  | 'none'
  | 'localstorage'
  | 'indexeddb'
  | 'cache_api'
  | 'unavailable'
  | 'unknown';

export interface ApiWallHeartbeatPayload {
  player_instance_id: string;
  runtime_status: 'booting' | 'idle' | 'playing' | 'paused' | 'stopped' | 'expired' | 'error';
  connection_status: 'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected' | 'error';
  current_item_id?: string | null;
  current_item_started_at?: string | null;
  current_sender_key?: string | null;
  current_media_type?: 'image' | 'video' | null;
  current_video_phase?: 'idle' | 'probing' | 'primed' | 'starting' | 'playing' | 'waiting' | 'stalled' | 'paused_by_wall' | 'completed' | 'capped' | 'interrupted' | 'failed_to_start' | null;
  current_video_exit_reason?: 'ended' | 'cap_reached' | 'paused_by_operator' | 'play_rejected' | 'stalled_timeout' | 'replaced_by_command' | 'media_deleted' | 'visibility_degraded' | 'startup_timeout' | 'poster_then_skip' | 'startup_waiting_timeout' | 'startup_play_rejected' | null;
  current_video_failure_reason?: 'network_error' | 'unsupported_format' | 'autoplay_blocked' | 'decode_degraded' | 'src_missing' | 'variant_missing' | null;
  current_video_position_seconds?: number | null;
  current_video_duration_seconds?: number | null;
  current_video_ready_state?: number | null;
  current_video_stall_count?: number | null;
  current_video_poster_visible?: boolean | null;
  current_video_first_frame_ready?: boolean | null;
  current_video_playback_ready?: boolean | null;
  current_video_playing_confirmed?: boolean | null;
  current_video_startup_degraded?: boolean | null;
  hardware_concurrency?: number | null;
  device_memory_gb?: number | null;
  network_effective_type?: 'slow-2g' | '2g' | '3g' | '4g' | 'unknown' | null;
  network_save_data?: boolean | null;
  network_downlink_mbps?: number | null;
  network_rtt_ms?: number | null;
  prefers_reduced_motion?: boolean | null;
  document_visibility_state?: 'visible' | 'hidden' | 'prerender' | 'unloaded' | null;
  ready_count: number;
  loading_count: number;
  error_count: number;
  stale_count: number;
  cache_enabled: boolean;
  persistent_storage: ApiWallPersistentStorage;
  cache_usage_bytes?: number | null;
  cache_quota_bytes?: number | null;
  cache_hit_count: number;
  cache_miss_count: number;
  cache_stale_fallback_count: number;
  board_piece_count?: number;
  board_burst_count?: number;
  board_budget_downgrade_count?: number;
  decode_backlog_count?: number;
  board_reset_count?: number;
  board_budget_downgrade_reason?: 'small_stage' | 'safe_area_pressure' | 'runtime_budget' | null;
  last_sync_at?: string | null;
  last_fallback_reason?: string | null;
}

export interface ApiWallDiagnosticsSummary {
  health_status: 'idle' | 'healthy' | 'degraded' | 'offline';
  total_players: number;
  online_players: number;
  offline_players: number;
  degraded_players: number;
  ready_count: number;
  loading_count: number;
  error_count: number;
  stale_count: number;
  cache_enabled_players: number;
  persistent_storage_players: number;
  cache_hit_rate_avg: number;
  cache_usage_bytes_max?: number | null;
  cache_quota_bytes_max?: number | null;
  cache_stale_fallback_count: number;
  last_seen_at?: string | null;
  updated_at?: string | null;
}

export interface ApiWallDiagnosticsPlayer {
  player_instance_id: string;
  health_status: 'healthy' | 'degraded' | 'offline';
  is_online: boolean;
  runtime_status: ApiWallHeartbeatPayload['runtime_status'];
  connection_status: ApiWallHeartbeatPayload['connection_status'];
  current_item_id?: string | null;
  current_item_started_at?: string | null;
  current_media_type?: ApiWallHeartbeatPayload['current_media_type'];
  current_video_phase?: ApiWallHeartbeatPayload['current_video_phase'];
  current_video_exit_reason?: ApiWallHeartbeatPayload['current_video_exit_reason'];
  current_video_failure_reason?: ApiWallHeartbeatPayload['current_video_failure_reason'];
  current_video_position_seconds?: number | null;
  current_video_duration_seconds?: number | null;
  current_video_ready_state?: number | null;
  current_video_stall_count?: number | null;
  current_video_poster_visible?: boolean | null;
  current_video_first_frame_ready?: boolean | null;
  current_video_playback_ready?: boolean | null;
  current_video_playing_confirmed?: boolean | null;
  current_video_startup_degraded?: boolean | null;
  hardware_concurrency?: number | null;
  device_memory_gb?: number | null;
  network_effective_type?: ApiWallHeartbeatPayload['network_effective_type'];
  network_save_data?: boolean | null;
  network_downlink_mbps?: number | null;
  network_rtt_ms?: number | null;
  prefers_reduced_motion?: boolean | null;
  document_visibility_state?: ApiWallHeartbeatPayload['document_visibility_state'];
  current_sender_key?: string | null;
  ready_count: number;
  loading_count: number;
  error_count: number;
  stale_count: number;
  cache_enabled: boolean;
  persistent_storage: ApiWallPersistentStorage;
  cache_usage_bytes?: number | null;
  cache_quota_bytes?: number | null;
  cache_hit_count: number;
  cache_miss_count: number;
  cache_stale_fallback_count: number;
  cache_hit_rate: number;
  board_piece_count?: number | null;
  board_burst_count?: number | null;
  board_budget_downgrade_count?: number | null;
  decode_backlog_count?: number | null;
  board_reset_count?: number | null;
  board_budget_downgrade_reason?: ApiWallHeartbeatPayload['board_budget_downgrade_reason'];
  last_sync_at?: string | null;
  last_seen_at?: string | null;
  last_fallback_reason?: string | null;
  updated_at?: string | null;
}

export interface ApiWallDiagnosticsResponse {
  summary: ApiWallDiagnosticsSummary;
  players: ApiWallDiagnosticsPlayer[];
  updated_at?: string | null;
}

export interface ApiWallSimulationSummary {
  selection_mode: ApiWallSelectionMode;
  selection_mode_label: string;
  event_phase: ApiWallEventPhase;
  event_phase_label: string;
  queue_items: number;
  active_senders: number;
  estimated_first_appearance_seconds?: number | null;
  monopolization_risk: 'low' | 'medium' | 'high';
  freshness_intensity: 'low' | 'medium' | 'high';
  fairness_level: 'low' | 'medium' | 'high';
}

export interface ApiWallSimulationPreviewItem {
  position: number;
  eta_seconds: number;
  item_id: string;
  preview_url?: string | null;
  sender_name: string;
  sender_key: string;
  source_type?: ApiWallMediaSource | null;
  caption?: string | null;
  layout_hint?: ApiWallResolvedLayout | null;
  duplicate_cluster_key?: string | null;
  is_featured: boolean;
  is_video?: boolean;
  duration_seconds?: number | null;
  video_policy_label?: string | null;
  video_admission?: ApiWallVideoAdmission | null;
  served_variant_key?: string | null;
  preview_variant_key?: string | null;
  is_replay: boolean;
  created_at?: string | null;
}

export interface ApiWallSimulationResponse {
  summary: ApiWallSimulationSummary;
  sequence_preview: ApiWallSimulationPreviewItem[];
  explanation: string[];
}

export interface ApiWallSettingsResponse {
  id: number;
  event_id: number;
  wall_code: string;
  is_enabled: boolean;
  status: string;
  status_label: string;
  public_url: string;
  settings: ApiWallSettings;
  diagnostics_summary: ApiWallDiagnosticsSummary;
  video_pipeline: {
    ffmpeg_bin: string;
    ffprobe_bin: string;
    ffmpeg_available: boolean;
    ffprobe_available: boolean;
    ffmpeg_resolved_path: string | null;
    ffprobe_resolved_path: string | null;
    ready: boolean;
  };
  expires_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ApiWallOption {
  value: string;
  label: string;
}

export interface ApiWallLayoutCapabilities {
  supports_video_playback: boolean;
  supports_video_poster_only: boolean;
  supports_multi_video: boolean;
  max_simultaneous_videos: number;
  fallback_video_layout: ApiWallLayout | null;
  supports_side_thumbnails: boolean;
  supports_floating_caption: boolean;
  supports_theme_config: boolean;
}

export interface ApiWallLayoutDefaults {
  theme_config: ApiWallThemeConfig;
}

export interface ApiWallLayoutOption extends ApiWallOption {
  value: ApiWallLayout;
  capabilities: ApiWallLayoutCapabilities;
  defaults: ApiWallLayoutDefaults;
}

export interface ApiWallTransitionModeOption extends ApiWallOption {
  value: ApiWallTransitionMode;
}

export interface ApiWallTransitionDefaults {
  transition_effect: ApiWallTransition;
  transition_mode: ApiWallTransitionMode;
}

export interface ApiWallSelectionModeOption extends ApiWallOption {
  value: ApiWallSelectionMode;
  description: string;
  selection_policy: ApiWallSelectionPolicy;
}

export interface ApiWallEventPhaseOption extends ApiWallOption {
  value: ApiWallEventPhase;
  description: string;
}

export interface ApiWallOptionsResponse {
  layouts: ApiWallLayoutOption[];
  transitions: ApiWallOption[];
  transition_modes?: ApiWallTransitionModeOption[];
  transition_defaults?: ApiWallTransitionDefaults;
  statuses: ApiWallOption[];
  selection_modes: ApiWallSelectionModeOption[];
  event_phases: ApiWallEventPhaseOption[];
}

export type ApiWallMediaSource =
  | 'whatsapp'
  | 'telegram'
  | 'upload'
  | 'manual'
  | 'gallery';

export interface ApiWallInsightsTopContributor {
  senderKey: string;
  displayName: string | null;
  maskedContact?: string | null;
  source: ApiWallMediaSource;
  mediaCount: number;
  lastSentAt?: string | null;
  avatarUrl?: string | null;
}

export interface ApiWallInsightsTotals {
  received: number;
  approved: number;
  queued: number;
  displayed: number;
}

export type ApiWallRecentItemStatus =
  | 'received'
  | 'approved'
  | 'queued'
  | 'displayed'
  | 'error';

export interface ApiWallInsightsRecentItem {
  id: string;
  previewUrl: string | null;
  senderName: string | null;
  senderKey: string;
  source: ApiWallMediaSource;
  createdAt: string | null;
  approvedAt?: string | null;
  displayedAt?: string | null;
  status: ApiWallRecentItemStatus;
  isFeatured?: boolean;
  isVideo?: boolean;
  durationSeconds?: number | null;
  videoPolicyLabel?: string | null;
  videoAdmission?: ApiWallVideoAdmission | null;
  servedVariantKey?: string | null;
  previewVariantKey?: string | null;
  isReplay?: boolean;
}

export interface ApiWallInsightsSourceMixItem {
  source: ApiWallMediaSource;
  count: number;
}

export interface ApiWallInsightsResponse {
  topContributor: ApiWallInsightsTopContributor | null;
  totals: ApiWallInsightsTotals;
  recentItems: ApiWallInsightsRecentItem[];
  sourceMix: ApiWallInsightsSourceMixItem[];
  lastCaptureAt?: string | null;
}

export type ApiWallResolvedLayout =
  | 'fullscreen'
  | 'polaroid'
  | 'split'
  | 'cinematic'
  | 'kenburns'
  | 'spotlight'
  | 'gallery'
  | 'carousel'
  | 'mosaic'
  | 'grid'
  | 'puzzle';

export interface ApiWallLiveSnapshotPlayer {
  playerInstanceId: string;
  healthStatus: 'healthy' | 'degraded' | 'offline';
  runtimeStatus: ApiWallHeartbeatPayload['runtime_status'];
  connectionStatus: ApiWallHeartbeatPayload['connection_status'];
  lastSeenAt?: string | null;
}

export interface ApiWallLiveSnapshotItem {
  id: string;
  previewUrl: string | null;
  senderName: string | null;
  senderKey: string;
  source: ApiWallMediaSource;
  caption?: string | null;
  layoutHint?: ApiWallResolvedLayout | null;
  isFeatured?: boolean;
  isVideo?: boolean;
  durationSeconds?: number | null;
  videoPolicyLabel?: string | null;
  videoAdmission?: ApiWallVideoAdmission | null;
  servedVariantKey?: string | null;
  previewVariantKey?: string | null;
  createdAt?: string | null;
}

export interface ApiWallLiveSnapshotResponse {
  wallStatus: string;
  wallStatusLabel: string;
  layout: ApiWallLayout;
  transitionEffect: ApiWallTransition;
  transitionMode?: ApiWallTransitionMode;
  currentPlayer: ApiWallLiveSnapshotPlayer | null;
  currentItem: ApiWallLiveSnapshotItem | null;
  nextItem?: ApiWallLiveSnapshotItem | null;
  advancedAt?: string | null;
  updatedAt?: string | null;
}

export interface ApiWallActionResponse {
  message: string;
  status: string;
  wall_code?: string;
}

export type ApiWallPlayerCommand = 'clear-cache' | 'revalidate-assets' | 'reinitialize-engine';

export interface ApiWallPlayerCommandResponse {
  message: string;
  command: ApiWallPlayerCommand;
  issued_at: string;
}

export interface ApiEventMediaItem {
  id: number;
  event_id: number;
  event_title?: string | null;
  event_slug?: string | null;
  event_status?: string | null;
  event_moderation_mode?: string | null;
  event_face_search_enabled?: boolean;
  event_allow_public_selfie_search?: boolean;
  media_type: 'image' | 'video' | string;
  channel: 'upload' | 'link' | 'whatsapp' | 'telegram' | 'qrcode' | string;
  status: 'received' | 'processing' | 'pending_moderation' | 'approved' | 'published' | 'rejected' | 'error' | string;
  processing_status: string | null;
  moderation_status: string | null;
  publication_status: string | null;
  safety_status?: string | null;
  face_index_status?: string | null;
  vlm_status?: string | null;
  safety_decision?: string | null;
  safety_is_blocking?: boolean;
  context_decision?: string | null;
  context_is_blocking?: boolean;
  operator_decision?: string | null;
  publication_decision?: string | null;
  decision_source?: 'none_mode' | 'manual_review' | 'ai_safety' | 'ai_vlm' | 'user_override' | string | null;
  decision_overridden_at?: string | null;
  decision_overridden_by_user_id?: number | null;
  decision_override_reason?: string | null;
  pipeline_version?: string | null;
  mime_type?: string | null;
  original_filename?: string | null;
  client_filename?: string | null;
  duplicate_group_key?: string | null;
  is_duplicate_candidate?: boolean;
  sender_name: string;
  sender_avatar_url?: string | null;
  sender_phone?: string | null;
  sender_lid?: string | null;
  sender_external_id?: string | null;
  sender_blocked?: boolean;
  sender_blocking_entry_id?: number | null;
  sender_block_reason?: string | null;
  sender_block_expires_at?: string | null;
  sender_blacklist_enabled?: boolean;
  sender_recommended_identity_type?: 'phone' | 'lid' | 'external_id' | string | null;
  sender_recommended_identity_value?: string | null;
  sender_recommended_normalized_phone?: string | null;
  sender_media_count?: number | null;
  caption: string | null;
  thumbnail_url: string | null;
  thumbnail_source?: string | null;
  preview_url?: string | null;
  preview_source?: string | null;
  moderation_thumbnail_url?: string | null;
  moderation_thumbnail_source?: string | null;
  moderation_preview_url?: string | null;
  moderation_preview_source?: string | null;
  original_url?: string | null;
  created_at: string | null;
  updated_at?: string | null;
  published_at: string | null;
  is_featured: boolean;
  is_pinned?: boolean;
  sort_order?: number;
  width?: number | null;
  height?: number | null;
  orientation?: 'portrait' | 'landscape' | 'square' | string | null;
}

export interface ApiEventMediaVariant {
  id: number;
  variant_key: string;
  disk: string | null;
  path: string | null;
  url: string | null;
  mime_type: string | null;
  width: number | null;
  height: number | null;
  size_bytes: number | null;
}

export interface ApiMediaProcessingRun {
  id: number;
  run_type: string;
  stage_key?: string | null;
  provider_key?: string | null;
  provider_version?: string | null;
  model_key?: string | null;
  model_snapshot?: string | null;
  input_ref?: string | null;
  decision_key?: string | null;
  queue_name?: string | null;
  worker_ref?: string | null;
  result_json?: Record<string, unknown> | null;
  metrics_json?: Record<string, unknown> | null;
  cost_units?: number | null;
  idempotency_key?: string | null;
  status: string;
  attempts: number;
  error_message: string | null;
  failure_class?: string | null;
  started_at: string | null;
  finished_at: string | null;
}

export interface ApiEventMediaSafetyEvaluationSummary {
  id: number;
  provider_key?: string | null;
  provider_version?: string | null;
  model_key?: string | null;
  model_snapshot?: string | null;
  threshold_version?: string | null;
  decision: string;
  blocked: boolean;
  review_required: boolean;
  category_scores?: Record<string, number>;
  reason_codes?: string[];
  completed_at: string | null;
}

export interface ApiEventMediaVlmEvaluationSummary {
  id: number;
  provider_key?: string | null;
  provider_version?: string | null;
  model_key?: string | null;
  model_snapshot?: string | null;
  prompt_version?: string | null;
  response_schema_version?: string | null;
  mode_applied?: string | null;
  decision: string;
  review_required: boolean;
  reason?: string | null;
  short_caption?: string | null;
  tags?: string[];
  tokens_input?: number | null;
  tokens_output?: number | null;
  completed_at: string | null;
}

export interface ApiEventMediaDetail extends ApiEventMediaItem {
  title: string | null;
  source_label: string | null;
  original_filename: string | null;
  client_filename?: string | null;
  perceptual_hash?: string | null;
  mime_type: string | null;
  size_bytes: number | null;
  duration_seconds: number | null;
  preview_url: string | null;
  original_url: string | null;
  decision_override?: {
    source: 'none_mode' | 'manual_review' | 'ai_safety' | 'ai_vlm' | 'user_override' | string | null;
    overridden_at: string | null;
    overridden_by_user_id: number | null;
    overridden_by?: {
      id: number;
      name: string;
      email: string | null;
    } | null;
    reason: string | null;
  } | null;
  variants: ApiEventMediaVariant[];
  processing_runs: ApiMediaProcessingRun[];
  latest_safety_evaluation?: ApiEventMediaSafetyEvaluationSummary | null;
  latest_vlm_evaluation?: ApiEventMediaVlmEvaluationSummary | null;
  indexed_faces_count?: number | null;
}

export interface ApiFaceSearchRequestSummary {
  id: number;
  event_id: number;
  requester_type: string;
  requester_user_id: number | null;
  status: string;
  consent_version: string | null;
  selfie_storage_strategy: string | null;
  faces_detected: number;
  query_face_quality_score: number | null;
  top_k: number;
  best_distance: number | null;
  result_photo_ids: number[];
  created_at: string | null;
  expires_at: string | null;
}

export interface ApiFaceSearchMatch {
  rank: number;
  event_media_id: number;
  best_distance: number;
  best_quality_score: number | null;
  best_face_area_ratio: number | null;
  matched_face_ids: number[];
  media: ApiEventMediaItem;
}

export interface ApiFaceSearchResponse {
  request: ApiFaceSearchRequestSummary;
  total_results: number;
  results: ApiFaceSearchMatch[];
}

export interface PublicFaceSearchBootstrap {
  event: {
    id: number;
    title: string;
    slug: string;
    cover_image_path: string | null;
    cover_image_url: string | null;
    logo_path: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
    starts_at: string | null;
    location_name: string | null;
  };
  search: {
    enabled: boolean;
    status: 'available' | 'disabled' | 'inactive' | string;
    reason: string | null;
    message: string;
    instructions: string;
    consent_required: boolean;
    consent_version: string;
    selfie_retention_hours: number;
    top_k: number;
  };
  links: {
    find_me_url: string;
    find_me_api_url: string;
    gallery_url: string | null;
    hub_url: string | null;
  };
}

export interface ApiPublicGalleryResponse {
  data: ApiEventMediaItem[];
  meta: {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
    request_id: string;
    face_search: {
      public_search_enabled: boolean;
      find_me_url: string | null;
    };
  };
}

// ─── Play ──────────────────────────────────────────────────

export interface PlayCatalogItem {
  key: string;
  name: string;
  description: string | null;
  enabled: boolean;
  supports_ranking: boolean;
  supports_photo_assets: boolean;
  config_schema: Record<string, unknown>;
}

export interface PlayGameAssetMedia {
  id: number;
  thumbnail_url: string | null;
  mime_type: string | null;
  width: number | null;
  height: number | null;
}

export interface PlayGameAsset {
  id: number;
  media_id: number;
  role: string;
  sort_order: number;
  metadata: Record<string, unknown>;
  media?: PlayGameAssetMedia;
}

export interface PlayGameReadiness {
  published: boolean;
  launchable: boolean;
  bootable: boolean;
  reason: string | null;
}

export interface PlayEventGame {
  id: number;
  uuid: string;
  event_id: number;
  game_type_key: string | null;
  game_type_name: string | null;
  title: string;
  slug: string;
  is_active: boolean;
  sort_order: number;
  ranking_enabled: boolean;
  settings: Record<string, unknown>;
  readiness?: PlayGameReadiness | null;
  assets?: PlayGameAsset[];
  assets_count?: number;
  sessions_count?: number;
  rankings_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface EventPlaySettings {
  is_enabled: boolean;
  memory_enabled: boolean;
  puzzle_enabled: boolean;
  memory_card_count: number;
  puzzle_piece_count: number;
  auto_refresh_assets: boolean;
  ranking_enabled: boolean;
}

export interface EventPlayManagerResponse {
  event: {
    id: number;
    title: string;
    slug: string;
    status: string | null;
  };
  settings: EventPlaySettings;
  catalog: PlayCatalogItem[];
  games: PlayEventGame[];
}

export interface PublicPlayEventManifest {
  event: {
    id: number;
    title: string;
    slug: string;
    cover_image_url: string | null;
    logo_url: string | null;
    primary_color: string | null;
    secondary_color: string | null;
  };
  settings: {
    is_enabled: boolean;
    ranking_enabled: boolean;
    auto_refresh_assets: boolean;
  };
  games: PlayEventGame[];
  pwa?: {
    installable: boolean;
    min_version: string | null;
  };
}

export interface PlayRuntimeAsset {
  id: string;
  url: string | null;
  width?: number | null;
  height?: number | null;
  mimeType?: string | null;
  role?: string;
  sortOrder?: number;
  orientation?: 'portrait' | 'landscape' | 'square' | null;
  variantKey?: string | null;
  deliveryProfile?: string | null;
  sourceWidth?: number | null;
  sourceHeight?: number | null;
}

export interface PlayRankingEntry {
  position: number | null;
  player_identifier: string;
  player_name: string | null;
  best_score: number;
  best_time_ms: number | null;
  best_moves: number | null;
  total_sessions: number;
  total_wins: number;
  last_played_at: string | null;
  metrics: Record<string, unknown>;
}

export interface PlayGameSession {
  uuid: string;
  event_game_id: number;
  player_identifier: string;
  player_name: string | null;
  status: string;
  started_at: string | null;
  last_activity_at?: string | null;
  expires_at?: string | null;
  finished_at: string | null;
  result: Record<string, unknown>;
  score: number | null;
  time_ms: number | null;
}

export interface PlayRestoreMove {
  moveNumber: number;
  type: string;
  payload?: Record<string, unknown>;
  occurredAt?: string | null;
}

export interface PlayResumeState {
  lastAcceptedMoveNumber: number;
  serverElapsedMs: number;
  moves: PlayRestoreMove[];
}

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

export interface PlayAnalyticsTimelinePoint {
  date: string;
  sessions: number;
  finished_sessions: number;
  abandoned_sessions: number;
  unique_players: number;
  total_moves: number;
  average_score: number | null;
  best_score: number | null;
}

export interface PlayAdminSession extends PlayGameSession {
  game: {
    id: number;
    uuid: string;
    title: string;
    slug: string;
    game_type_key: string | null;
    game_type_name: string | null;
  } | null;
  move_count: number | null;
  moves_reported: number | null;
  mistakes: number | null;
  accuracy: number | null;
  completed: boolean;
}

export interface PlayAnalyticsGameItem {
  game: PlayEventGame;
  analytics: PlayGameAnalytics;
}

export interface PlayAnalyticsResponse {
  filters: {
    play_game_id: number | null;
    date_from: string | null;
    date_to: string | null;
    status: string | null;
    search: string | null;
    session_limit: number;
  };
  summary: PlayGameAnalytics;
  timeline: PlayAnalyticsTimelinePoint[];
  games: PlayAnalyticsGameItem[];
  recent_sessions: PlayAdminSession[];
}

export interface PublicPlayGameResponse {
  game: PlayEventGame;
  runtime: {
    assets: PlayRuntimeAsset[];
    ranking: PlayRankingEntry[];
    last_plays: PlayGameSession[];
    analytics: PlayGameAnalytics;
    realtime: {
      channel: string;
      events: {
        leaderboard_updated: string;
      };
    };
  };
}

export interface StartPlaySessionPayload {
  player_identifier?: string;
  player_name?: string;
  playerIdentifier?: string;
  displayName?: string | null;
  device?: {
    platform?: string;
    viewportWidth?: number;
    viewportHeight?: number;
    pixelRatio?: number;
    connection?: {
      saveData?: boolean;
      effectiveType?: string;
      downlink?: number;
    };
  };
}

export interface StartPlaySessionResponse {
  sessionUuid: string;
  eventGameId: number;
  gameKey: string;
  sessionSeed?: string;
  resumeToken?: string;
  status?: string;
  startedAt?: string | null;
  lastActivityAt?: string | null;
  expiresAt?: string | null;
  authoritativeScoring?: boolean;
  session?: {
    uuid: string;
    resumeToken: string | null;
    status: string;
    startedAt: string | null;
    lastActivityAt: string | null;
    expiresAt: string | null;
    authoritativeScoring: boolean;
    seed: string;
  };
  player: {
    identifier: string;
    name: string | null;
  };
  settings: Record<string, unknown>;
  assets: PlayRuntimeAsset[];
  analytics: PlaySessionAnalytics;
  restore?: PlayResumeState | null;
}

export interface FinishPlaySessionPayload {
  score?: number;
  completed?: boolean;
  time_ms?: number;
  moves?: number;
  mistakes?: number;
  accuracy?: number;
  metadata?: Record<string, unknown>;
  clientResult?: {
    score?: number;
    completed?: boolean;
    timeMs?: number;
    moves?: number;
    mistakes?: number;
    accuracy?: number;
    metadata?: Record<string, unknown>;
  };
}

export interface StorePlayMovesPayload {
  batchNumber?: number;
  moves: Array<{
    move_number: number;
    move_type: string;
    payload?: Record<string, unknown>;
    occurred_at?: string;
  }>;
}

export interface HeartbeatPlaySessionPayload {
  state: 'visible' | 'hidden' | 'backgrounded';
  reason?: string;
  elapsedMs?: number;
}

export interface HeartbeatPlaySessionResponse {
  session: PlayGameSession;
  analytics: PlaySessionAnalytics;
}

export interface ResumePlaySessionPayload {
  resumeToken: string;
}

export interface ResumePlaySessionResponse extends StartPlaySessionResponse {}

export interface StorePlayMovesResponse {
  session: PlayGameSession;
  accepted_moves: number;
  analytics: PlaySessionAnalytics;
}

export interface PlaySessionAnalyticsResponse {
  session: PlayGameSession;
  analytics: PlaySessionAnalytics;
}

export interface FinishPlaySessionResponse {
  session: PlayGameSession;
  status: string;
  result: Record<string, unknown>;
  authoritative_result?: Record<string, unknown>;
  analytics: PlaySessionAnalytics;
  leaderboard: PlayRankingEntry[];
  last_plays: PlayGameSession[];
  game_analytics: PlayGameAnalytics;
}

// ─── Pagination ────────────────────────────────────────────

export interface PaginatedMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id: string;
}

export interface ModerationStatsMeta {
  total: number;
  pending: number;
  approved: number;
  rejected: number;
  featured: number;
  pinned: number;
}

export interface MediaCatalogStatsMeta {
  total: number;
  images: number;
  videos: number;
  pending: number;
  published: number;
  featured: number;
  pinned: number;
  duplicates: number;
  face_indexed: number;
}

export interface CursorPaginatedMeta {
  per_page: number;
  next_cursor: string | null;
  prev_cursor: string | null;
  has_more: boolean;
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
    accepts_video: boolean;
    video_single_only: boolean;
    video_max_duration_seconds: number | null;
    max_files: number;
    max_file_size_mb: number;
    accept_hint: string;
    moderation_mode: 'none' | 'manual' | 'ai' | string;
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

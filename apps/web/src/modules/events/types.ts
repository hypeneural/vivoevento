import type { EventIntakeBlacklist, EventIntakeChannels, EventIntakeDefaults } from './intake';

export type ApiEventStatus = 'draft' | 'scheduled' | 'active' | 'paused' | 'ended' | 'archived';
export type ApiEventType = 'wedding' | 'birthday' | 'fifteen' | 'corporate' | 'fair' | 'graduation' | 'other';
export type EventModuleKey = 'live' | 'wall' | 'play' | 'hub';
export type EventSortBy = 'starts_at' | 'created_at' | 'title' | 'status';
export type SortDirection = 'asc' | 'desc';
export type EventVisibility = 'public' | 'private' | 'unlisted';
export type EventModerationMode = 'none' | 'manual' | 'ai';
export type EventBrandingAssetKind = 'cover' | 'logo';
export type EventCommercialMode = 'none' | 'subscription_covered' | 'trial' | 'single_purchase' | 'bonus' | 'manual_override';

export interface EventFaceSearchSettings {
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
  created_at: string | null;
  updated_at: string | null;
}

export interface EventContentModerationSettings {
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

export interface EventMediaIntelligenceSettings {
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
  created_at: string | null;
  updated_at: string | null;
}

export interface ApiPaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id?: string;
}

export interface EventWallSummary {
  id: number;
  wall_code: string | null;
  is_enabled: boolean;
  status: string | null;
  public_url: string | null;
}

export interface EventListItem {
  id: number;
  uuid: string;
  organization_id: number;
  client_id: number | null;
  title: string;
  slug: string;
  upload_slug: string;
  event_type: ApiEventType;
  status: ApiEventStatus;
  commercial_mode?: EventCommercialMode | null;
  visibility: EventVisibility | null;
  moderation_mode: EventModerationMode | null;
  starts_at: string | null;
  ends_at: string | null;
  location_name: string | null;
  cover_image_path: string | null;
  cover_image_url: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  public_url: string | null;
  upload_url: string | null;
  created_at: string | null;
  organization_name: string | null;
  client_name: string | null;
  enabled_modules: EventModuleKey[];
  media_count: number;
  wall?: EventWallSummary | null;
}

export interface EventDetailItem extends EventListItem {
  description: string | null;
  logo_path: string | null;
  logo_url: string | null;
  qr_code_path: string | null;
  upload_api_url: string | null;
  retention_days: number | null;
  created_by: number | null;
  updated_at: string | null;
  organization_slug: string | null;
  module_count: number;
  current_entitlements?: Record<string, unknown> | null;
  intake_defaults?: EventIntakeDefaults | null;
  intake_channels?: EventIntakeChannels | null;
  intake_blacklist?: EventIntakeBlacklist | null;
  content_moderation?: EventContentModerationSettings | null;
  face_search?: EventFaceSearchSettings | null;
  media_intelligence?: EventMediaIntelligenceSettings | null;
}

export interface EventShareLinks {
  public_url: string | null;
  upload_url: string | null;
  upload_api_url: string | null;
  gallery_url: string | null;
  find_me_url?: string | null;
  wall_url: string | null;
  hub_url: string | null;
  play_url?: string | null;
  upload_slug: string;
}

export interface EventTelegramOperationalSignal {
  id: number;
  provider_update_id?: string | null;
  chat_external_id: string | null;
  sender_external_id: string | null;
  sender_name?: string | null;
  signal: string | null;
  old_status?: string | null;
  new_status?: string | null;
  occurred_at?: string | null;
  created_at?: string | null;
}

export interface EventTelegramOperationalStatus {
  enabled: boolean;
  configured: boolean;
  healthy: boolean;
  error_message: string | null;
  channel: {
    id: number;
    status: string;
    bot_username: string | null;
    media_inbox_code: string | null;
    session_ttl_minutes: number | null;
    allow_private: boolean;
    v1_allowed_updates: string[];
  } | null;
  bot: {
    ok: boolean;
    id: string | null;
    username: string | null;
    is_bot: boolean;
    can_join_groups: boolean;
    can_read_all_group_messages: boolean;
  };
  webhook: {
    ok: boolean;
    url: string | null;
    pending_update_count: number;
    has_custom_certificate: boolean;
    ip_address: string | null;
    last_error_at: string | null;
    last_error_message: string | null;
    max_connections: number | null;
    allowed_updates: string[];
    expected_allowed_updates: string[];
    matches_expected_allowed_updates: boolean;
  };
  recent_operational_signals: EventTelegramOperationalSignal[];
}

export interface PaginatedEventsResponse {
  success: boolean;
  data: EventListItem[];
  meta: ApiPaginationMeta;
}

export interface EventFormPayload {
  organization_id?: number;
  client_id?: number | null;
  title: string;
  event_type: ApiEventType;
  slug?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  location_name?: string | null;
  description?: string | null;
  branding?: {
    primary_color?: string | null;
    secondary_color?: string | null;
    cover_image_path?: string | null;
    logo_path?: string | null;
  };
  modules?: Partial<Record<EventModuleKey, boolean>>;
  privacy?: {
    visibility?: EventVisibility;
    moderation_mode?: EventModerationMode;
    retention_days?: number;
  };
  face_search?: {
    enabled?: boolean;
    allow_public_selfie_search?: boolean;
    selfie_retention_hours?: number;
  };
  intake_defaults?: EventIntakeDefaults;
  intake_channels?: EventIntakeChannels;
  intake_blacklist?: Pick<EventIntakeBlacklist, 'entries'>;
}

export interface EventCreateResponse {
  id: number;
  uuid: string;
  title: string;
  status: ApiEventStatus;
  moderation_mode?: EventModerationMode | null;
  face_search?: EventFaceSearchSettings | null;
  slug: string;
  upload_slug: string;
  public_url: string | null;
  upload_url: string | null;
  upload_api_url: string | null;
  intake_defaults?: EventIntakeDefaults | null;
  intake_channels?: EventIntakeChannels | null;
  intake_blacklist?: EventIntakeBlacklist | null;
  modules: Record<EventModuleKey, boolean>;
  links: {
    public_hub: string | null;
    upload: string | null;
    upload_api: string | null;
    wall: string | null;
  };
  qr: {
    status: string;
    image_url: string | null;
  };
}

export interface EventBrandingUploadResponse {
  kind: EventBrandingAssetKind;
  path: string;
  url: string;
}

export interface EventClientOption {
  id: number;
  organization_id: number;
  type: string | null;
  name: string;
  email: string | null;
  phone: string | null;
  document_number: string | null;
  notes: string | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
  events_count?: number;
}

export interface ListEventsFilters {
  organization_id?: number;
  search?: string;
  status?: ApiEventStatus;
  event_type?: ApiEventType;
  module?: EventModuleKey;
  date_from?: string;
  date_to?: string;
  sort_by?: EventSortBy;
  sort_direction?: SortDirection;
  page?: number;
  per_page?: number;
}

export const EVENT_STATUS_LABELS: Record<ApiEventStatus, string> = {
  draft: 'Rascunho',
  scheduled: 'Agendado',
  active: 'Ativo',
  paused: 'Pausado',
  ended: 'Encerrado',
  archived: 'Arquivado',
};

export const EVENT_TYPE_LABELS: Record<ApiEventType, string> = {
  wedding: 'Casamento',
  birthday: 'Aniversário',
  fifteen: '15 anos',
  corporate: 'Corporativo',
  fair: 'Feira',
  graduation: 'Formatura',
  other: 'Outro',
};

export const EVENT_MODULE_LABELS: Record<EventModuleKey, string> = {
  live: 'Galeria ao vivo',
  wall: 'Telao',
  play: 'Jogos',
  hub: 'Links',
};

export const EVENT_VISIBILITY_LABELS: Record<EventVisibility, string> = {
  public: 'Público',
  private: 'Privado',
  unlisted: 'Não listado',
};

export const EVENT_MODERATION_LABELS: Record<EventModerationMode, string> = {
  none: 'Sem moderacao',
  manual: 'Manual',
  ai: 'IA',
};

export const EVENT_COMMERCIAL_MODE_LABELS: Record<EventCommercialMode, string> = {
  none: 'Sem ativacao',
  subscription_covered: 'Assinatura da conta',
  trial: 'Trial do evento',
  single_purchase: 'Pacote do evento',
  bonus: 'Bonificacao do evento',
  manual_override: 'Override do evento',
};

export const EVENT_COMMERCIAL_MODE_HINTS: Record<EventCommercialMode, string> = {
  none: 'O evento ainda nao possui ativacao comercial definida.',
  subscription_covered: 'Este evento depende da assinatura recorrente da organizacao.',
  trial: 'Este evento esta ativo por grant de trial, sem depender da assinatura da conta.',
  single_purchase: 'Este evento tem ativacao propria por compra avulsa ou pacote dedicado.',
  bonus: 'Este evento foi liberado por concessao operacional, sem cobranca direta.',
  manual_override: 'Este evento esta ativo por override manual auditavel.',
};

export const EVENT_COMMERCIAL_SCOPE_LABELS: Record<EventCommercialMode, string> = {
  none: 'Sem origem',
  subscription_covered: 'Conta',
  trial: 'Evento',
  single_purchase: 'Evento',
  bonus: 'Evento',
  manual_override: 'Evento',
};

export const EVENT_STATUS_OPTIONS = Object.entries(EVENT_STATUS_LABELS).map(([value, label]) => ({
  value: value as ApiEventStatus,
  label,
}));

export const EVENT_TYPE_OPTIONS = Object.entries(EVENT_TYPE_LABELS).map(([value, label]) => ({
  value: value as ApiEventType,
  label,
}));

export const EVENT_MODULE_OPTIONS = Object.entries(EVENT_MODULE_LABELS).map(([value, label]) => ({
  value: value as EventModuleKey,
  label,
}));

export const EVENT_VISIBILITY_OPTIONS = Object.entries(EVENT_VISIBILITY_LABELS).map(([value, label]) => ({
  value: value as EventVisibility,
  label,
}));

export const EVENT_MODERATION_OPTIONS = Object.entries(EVENT_MODERATION_LABELS).map(([value, label]) => ({
  value: value as EventModerationMode,
  label,
}));

export const EVENT_SORT_OPTIONS: Array<{ value: EventSortBy; label: string }> = [
  { value: 'starts_at', label: 'Data do evento' },
  { value: 'created_at', label: 'Data de cadastro' },
  { value: 'title', label: 'Nome do evento' },
  { value: 'status', label: 'Status' },
];

export const EVENT_RETENTION_OPTIONS = [
  { value: 7, label: '7 dias' },
  { value: 15, label: '15 dias' },
  { value: 30, label: '30 dias' },
  { value: 90, label: '90 dias' },
  { value: 180, label: '180 dias' },
  { value: 365, label: '365 dias' },
];

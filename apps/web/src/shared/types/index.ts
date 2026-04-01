// ═══════════════════════════════════════════════════════════
// CORE TYPES — Evento Vivo Frontend
// Aligned with backend Laravel models
// ═══════════════════════════════════════════════════════════

// ─── Roles & Auth ──────────────────────────────────────────

export type UserRole =
  | 'super_admin'
  | 'platform_admin'
  | 'partner_owner'
  | 'partner_manager'
  | 'event_operator'
  | 'financial'
  | 'partner'
  | 'viewer';

export type ThemeMode = 'light' | 'dark' | 'system';

export type OrganizationType = 'partner' | 'host' | 'agency' | 'brand' | 'internal';

export type BrandingMode = 'default' | 'co-branding' | 'white-label';

// ─── User ──────────────────────────────────────────────────

export interface User {
  id: string;
  name: string;
  email: string;
  phone?: string;
  whatsapp?: string;
  avatarUrl?: string;
  role: UserRole;
  organizationId: string;
}

/** Full session returned after login — includes permissions and org context */
export interface UserSession {
  id: string;
  name: string;
  email: string;
  phone?: string;
  avatarUrl?: string;
  role: UserRole;
  organizationId: string;
  organizationName: string;
  permissions: string[];
  enabledModules: string[];
  themePreference?: ThemeMode;
}

// ─── Organization ──────────────────────────────────────────

export interface OrganizationBranding {
  primaryColor: string;
  secondaryColor: string;
  accentColor?: string;
  logoUrl?: string;
  faviconUrl?: string;
  mode: BrandingMode;
}

export interface Organization {
  id: string;
  name: string;
  tradeName?: string;
  slug: string;
  type: OrganizationType;
  plan: string;
  logoUrl?: string;
  logoEmoji?: string;
  branding: OrganizationBranding;
  enabledModules: string[];
  status: 'active' | 'inactive' | 'suspended';
}

// ─── Events ────────────────────────────────────────────────

export type EventStatus = 'draft' | 'active' | 'paused' | 'finished' | 'archived';
export type EventType = 'wedding' | 'corporate' | 'birthday' | 'conference' | 'party' | 'festival' | 'other';

export interface EventItem {
  id: string;
  name: string;
  type: EventType;
  date: string;
  location: string;
  organizationId: string;
  organizationName: string;
  status: EventStatus;
  photosReceived: number;
  photosApproved: number;
  modulesActive: string[];
  plan: string;
  coverUrl: string;
  slug: string;
  description: string;
  responsible: string;
}

// ─── Media ─────────────────────────────────────────────────

export type MediaStatus = 'received' | 'processing' | 'pending_moderation' | 'approved' | 'rejected' | 'published' | 'error';
export type MediaChannel = 'qrcode' | 'link' | 'whatsapp' | 'upload' | 'telegram';

export interface MediaItem {
  id: string;
  eventId: string;
  eventName: string;
  thumbnailUrl: string;
  senderName: string;
  channel: MediaChannel;
  status: MediaStatus;
  createdAt: string;
  fileType: 'image' | 'video';
}

// ─── Partners & Clients ───────────────────────────────────

export interface Partner {
  id: string;
  name: string;
  type: string;
  plan: string;
  activeEvents: number;
  revenue: number;
  status: 'active' | 'inactive' | 'trial';
  teamSize: number;
  logo: string;
}

export interface Client {
  id: string;
  name: string;
  type: string;
  partnerId: string;
  partnerName: string;
  eventsCount: number;
  plan: string;
  status: 'active' | 'inactive';
}

// ─── Plans & Billing ──────────────────────────────────────

export interface PlanItem {
  id: string;
  name: string;
  price: number;
  cycle: 'monthly' | 'yearly' | 'per_event';
  features: string[];
  limits: Record<string, number | string>;
  modules: string[];
  popular?: boolean;
}

// ─── Audit & Notifications ────────────────────────────────

export interface AuditEntry {
  id: string;
  userId: string;
  userName: string;
  action: string;
  entityType: string;
  entityName: string;
  eventName?: string;
  createdAt: string;
}

export interface Notification {
  id: string;
  title: string;
  message: string;
  type: 'info' | 'warning' | 'success' | 'error';
  read: boolean;
  createdAt: string;
}

// ─── Dashboard ─────────────────────────────────────────────

export interface DashboardStats {
  activeEvents: number;
  photosToday: number;
  photosApproved: number;
  moderationRate: number;
  gamesPlayed: number;
  hubAccesses: number;
  estimatedRevenue: number;
  activePartners: number;
}

// ─── Labels ────────────────────────────────────────────────

export const ROLE_LABELS: Record<UserRole, string> = {
  super_admin: 'Super Admin',
  platform_admin: 'Admin Plataforma',
  partner_owner: 'Dono da Organização',
  partner_manager: 'Gerente',
  event_operator: 'Operador de Evento',
  financial: 'Financeiro',
  partner: 'Parceiro',
  viewer: 'Visualizador',
};

export const EVENT_STATUS_LABELS: Record<EventStatus, string> = {
  draft: 'Rascunho',
  active: 'Ativo',
  paused: 'Pausado',
  finished: 'Encerrado',
  archived: 'Arquivado',
};

export const MEDIA_STATUS_LABELS: Record<MediaStatus, string> = {
  received: 'Recebido',
  processing: 'Processando',
  pending_moderation: 'Aguardando Moderação',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
  published: 'Publicado',
  error: 'Erro',
};

export const CHANNEL_LABELS: Record<MediaChannel, string> = {
  qrcode: 'QR Code',
  link: 'Link',
  whatsapp: 'WhatsApp',
  upload: 'Upload',
  telegram: 'Telegram',
};

export const EVENT_TYPE_LABELS: Record<EventType, string> = {
  wedding: 'Casamento',
  corporate: 'Corporativo',
  birthday: 'Aniversário',
  conference: 'Conferência',
  party: 'Festa',
  festival: 'Festival',
  other: 'Outro',
};

export const ORG_TYPE_LABELS: Record<OrganizationType, string> = {
  partner: 'Parceiro',
  host: 'Anfitrião',
  agency: 'Agência',
  brand: 'Marca',
  internal: 'Interno',
};

export const BRANDING_MODE_LABELS: Record<BrandingMode, string> = {
  default: 'Padrão Evento Vivo',
  'co-branding': 'Co-branding',
  'white-label': 'White-label',
};

// ─── Types ─────────────────────────────────────────────────
// All TypeScript types for the wall player module.

export type WallMediaType = 'image' | 'video';
export type WallLayout = 'auto' | 'polaroid' | 'fullscreen' | 'split' | 'cinematic';
export type WallTransition = 'fade' | 'slide' | 'zoom' | 'flip' | 'none';
export type WallStatus = 'draft' | 'live' | 'paused' | 'stopped' | 'expired';

export type WallConnectionStatus =
  | 'idle'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'disconnected'
  | 'error';

export type WallPlayerStatus =
  | 'booting'
  | 'idle'
  | 'playing'
  | 'paused'
  | 'stopped'
  | 'expired'
  | 'error';

// ─── API Payloads ──────────────────────────────────────────

export interface WallEventSummary {
  id: number;
  title: string;
  slug?: string | null;
  wall_code: string;
  status: WallStatus;
}

export interface WallMediaItem {
  id: string;
  url: string;
  type: WallMediaType;
  sender_name?: string | null;
  caption?: string | null;
  is_featured: boolean;
  created_at?: string | null;
}

export interface WallSettings {
  interval_ms: number;
  queue_limit: number;
  layout: WallLayout;
  transition_effect: WallTransition;
  background_url?: string | null;
  partner_logo_url?: string | null;
  show_qr: boolean;
  show_branding: boolean;
  show_neon: boolean;
  neon_text?: string | null;
  neon_color?: string | null;
  show_sender_credit: boolean;
  instructions_text?: string | null;
}

export interface WallBootData {
  event: WallEventSummary;
  files: WallMediaItem[];
  settings: WallSettings;
}

// ─── WebSocket Event Payloads ──────────────────────────────

export interface WallMediaDeletedPayload {
  id: string;
}

export interface WallStatusChangedPayload {
  status: WallStatus;
  reason?: string | null;
  updated_at?: string | null;
}

export interface WallExpiredPayload {
  reason?: string;
  expired_at?: string | null;
}

// ─── Runtime ───────────────────────────────────────────────

export type MediaOrientation = 'vertical' | 'horizontal' | 'squareish';

export interface WallRuntimeItem extends WallMediaItem {
  orientation?: MediaOrientation | null;
  width?: number | null;
  height?: number | null;
}

export interface WallPlayerState {
  code: string;
  status: WallPlayerStatus;
  event: WallEventSummary | null;
  settings: WallSettings | null;
  items: WallRuntimeItem[];
  currentIndex: number;
}

export const WALL_EVENT_NAMES = {
  mediaPublished: 'wall.media.published',
  mediaUpdated: 'wall.media.updated',
  mediaDeleted: 'wall.media.deleted',
  settingsUpdated: 'wall.settings.updated',
  statusChanged: 'wall.status.changed',
  expired: 'wall.expired',
  diagnosticsUpdated: 'wall.diagnostics.updated',
  liveSnapshotUpdated: 'wall.runtime.snapshot.updated',
  playerCommand: 'wall.player.command',
  adsUpdated: 'wall.ads.updated',
} as const;

export type WallMediaType = 'image' | 'video';
export type WallLayout = 'auto' | 'polaroid' | 'fullscreen' | 'split' | 'cinematic' | 'kenburns' | 'spotlight' | 'gallery' | 'carousel' | 'mosaic' | 'grid' | 'puzzle';
export type WallTransition =
  | 'fade'
  | 'slide'
  | 'zoom'
  | 'flip'
  | 'lift-fade'
  | 'cross-zoom'
  | 'swipe-up'
  | 'none';
export type WallTransitionMode = 'fixed' | 'random';
export type WallTransitionFallbackReason = 'reduced_motion' | 'capability_tier' | 'effect_unavailable';
export type WallLifecycleStatus = 'draft' | 'live' | 'paused' | 'stopped' | 'expired';
export type WallPublicStatus = WallLifecycleStatus | 'disabled';
export type WallSelectionMode = 'balanced' | 'live' | 'inclusive' | 'editorial' | 'custom';
export type WallEventPhase = 'reception' | 'flow' | 'party' | 'closing';
export type WallAcceptedOrientation = 'all' | 'landscape' | 'portrait';
export type WallAdMode = 'disabled' | 'by_photos' | 'by_minutes';
export type WallVideoPlaybackMode = 'fixed_interval' | 'play_to_end' | 'play_to_end_if_short_else_cap';
export type WallVideoResumeMode = 'resume_if_same_item' | 'restart_from_zero' | 'resume_if_same_item_else_restart';
export type WallVideoAudioPolicy = 'muted';
export type WallVideoMultiLayoutPolicy = 'disallow' | 'one' | 'all';
export type WallVideoPreferredVariant = 'wall_video_720p' | 'wall_video_1080p' | 'original';
export type WallVideoAdmissionState = 'eligible' | 'eligible_with_fallback' | 'blocked';
export type WallThemePreset = 'compact' | 'standard';
export type WallThemeAnchorMode = 'event_brand' | 'qr_prompt' | 'none';
export type WallThemeBurstIntensity = 'gentle' | 'normal';
export type WallThemeVideoBehavior = 'fallback_single_item';
export type WallPersistentStorage =
  | 'none'
  | 'localstorage'
  | 'indexeddb'
  | 'cache_api'
  | 'unavailable'
  | 'unknown';

export interface WallEventSummary {
  id: number;
  title: string;
  slug?: string | null;
  upload_url?: string | null;
  wall_code: string;
  status: WallPublicStatus;
}

export type MediaOrientation = 'vertical' | 'horizontal' | 'squareish';

export interface WallMediaItem {
  id: string;
  url: string | null;
  served_variant_key?: string | null;
  preview_url?: string | null;
  preview_variant_key?: string | null;
  original_url?: string | null;
  type: WallMediaType;
  sender_name?: string | null;
  sender_key?: string | null;
  source_type?: string | null;
  caption?: string | null;
  duplicate_cluster_key?: string | null;
  is_featured: boolean;
  width?: number | null;
  height?: number | null;
  duration_seconds?: number | null;
  has_audio?: boolean | null;
  video_codec?: string | null;
  audio_codec?: string | null;
  bitrate?: number | null;
  container?: string | null;
  video_admission?: WallVideoAdmission | null;
  orientation?: MediaOrientation | null;
  created_at?: string | null;
}

export interface WallVideoAdmission {
  state: WallVideoAdmissionState;
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

export interface WallThemeConfig {
  preset?: WallThemePreset;
  anchor_mode?: WallThemeAnchorMode;
  burst_intensity?: WallThemeBurstIntensity;
  hero_enabled?: boolean;
  video_behavior?: WallThemeVideoBehavior;
}

export interface WallLayoutCapabilities {
  supports_video_playback: boolean;
  supports_video_poster_only: boolean;
  supports_multi_video: boolean;
  max_simultaneous_videos: number;
  fallback_video_layout?: WallLayout | null;
  supports_side_thumbnails: boolean;
  supports_floating_caption: boolean;
  supports_theme_config: boolean;
}

export interface WallSettings {
  interval_ms: number;
  queue_limit: number;
  selection_mode: WallSelectionMode;
  event_phase: WallEventPhase;
  selection_policy: WallSelectionPolicy;
  theme_config: WallThemeConfig;
  layout: WallLayout;
  transition_effect: WallTransition;
  transition_mode?: WallTransitionMode;
  transition_pool?: WallTransition[] | null;
  background_url?: string | null;
  partner_logo_url?: string | null;
  show_qr: boolean;
  show_branding: boolean;
  show_neon: boolean;
  neon_text?: string | null;
  neon_color?: string | null;
  show_sender_credit: boolean;
  show_side_thumbnails: boolean;
  accepted_orientation: WallAcceptedOrientation;
  video_enabled: boolean;
  public_upload_video_enabled?: boolean;
  private_inbound_video_enabled?: boolean;
  video_playback_mode: WallVideoPlaybackMode;
  video_max_seconds: number;
  video_resume_mode: WallVideoResumeMode;
  video_audio_policy: WallVideoAudioPolicy;
  video_multi_layout_policy: WallVideoMultiLayoutPolicy;
  video_preferred_variant: WallVideoPreferredVariant;
  ad_mode: WallAdMode;
  ad_frequency: number;
  ad_interval_minutes: number;
  instructions_text?: string | null;
}

export interface WallAdItem {
  id: number;
  url: string;
  media_type: 'image' | 'video';
  duration_seconds: number;
  position: number;
}

export interface WallSelectionPolicy {
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

export interface WallSelectionModeOption {
  value: WallSelectionMode;
  label: string;
  description: string;
  selection_policy: WallSelectionPolicy;
}

export interface WallEventPhaseOption {
  value: WallEventPhase;
  label: string;
  description: string;
}

export type WallPlayerCommand = 'clear-cache' | 'revalidate-assets' | 'reinitialize-engine';

export interface WallPlayerCommandPayload {
  command: WallPlayerCommand;
  reason?: string | null;
  issued_at?: string | null;
}

export interface WallHeartbeatPayload {
  player_instance_id: string;
  runtime_status: 'booting' | 'idle' | 'playing' | 'paused' | 'stopped' | 'expired' | 'error';
  connection_status: 'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected' | 'error';
  current_item_id?: string | null;
  current_item_started_at?: string | null;
  active_transition_effect?: WallTransition | null;
  transition_mode?: WallTransitionMode | null;
  transition_random_pick_count?: number;
  transition_fallback_count?: number;
  transition_last_fallback_reason?: WallTransitionFallbackReason | null;
  current_sender_key?: string | null;
  current_media_type?: WallMediaType | null;
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
  persistent_storage: WallPersistentStorage;
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

export interface WallDiagnosticsSummary {
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

export interface WallDiagnosticsPlayer {
  player_instance_id: string;
  health_status: 'healthy' | 'degraded' | 'offline';
  is_online: boolean;
  runtime_status: WallHeartbeatPayload['runtime_status'];
  connection_status: WallHeartbeatPayload['connection_status'];
  current_item_id?: string | null;
  active_transition_effect?: WallTransition | null;
  transition_mode?: WallTransitionMode | null;
  transition_random_pick_count: number;
  transition_fallback_count: number;
  transition_last_fallback_reason?: WallTransitionFallbackReason | null;
  current_media_type?: WallMediaType | null;
  current_video_phase?: WallHeartbeatPayload['current_video_phase'];
  current_video_exit_reason?: WallHeartbeatPayload['current_video_exit_reason'];
  current_video_failure_reason?: WallHeartbeatPayload['current_video_failure_reason'];
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
  network_effective_type?: WallHeartbeatPayload['network_effective_type'];
  network_save_data?: boolean | null;
  network_downlink_mbps?: number | null;
  network_rtt_ms?: number | null;
  prefers_reduced_motion?: boolean | null;
  document_visibility_state?: WallHeartbeatPayload['document_visibility_state'];
  current_sender_key?: string | null;
  ready_count: number;
  loading_count: number;
  error_count: number;
  stale_count: number;
  cache_enabled: boolean;
  persistent_storage: WallPersistentStorage;
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
  board_budget_downgrade_reason?: WallHeartbeatPayload['board_budget_downgrade_reason'];
  last_sync_at?: string | null;
  last_seen_at?: string | null;
  last_fallback_reason?: string | null;
  updated_at?: string | null;
}

export interface WallDiagnosticsResponse {
  summary: WallDiagnosticsSummary;
  players: WallDiagnosticsPlayer[];
  updated_at?: string | null;
}

export interface WallSimulationRequest extends Partial<WallSettings> {}

export interface WallSimulationSummary {
  selection_mode: WallSelectionMode;
  selection_mode_label: string;
  event_phase: WallEventPhase;
  event_phase_label: string;
  queue_items: number;
  active_senders: number;
  estimated_first_appearance_seconds?: number | null;
  monopolization_risk: 'low' | 'medium' | 'high';
  freshness_intensity: 'low' | 'medium' | 'high';
  fairness_level: 'low' | 'medium' | 'high';
}

export interface WallSimulationPreviewItem {
  position: number;
  eta_seconds: number;
  item_id: string;
  preview_url?: string | null;
  sender_name: string;
  sender_key: string;
  source_type?: 'whatsapp' | 'telegram' | 'upload' | 'manual' | 'gallery' | null;
  caption?: string | null;
  layout_hint?: WallLayout | null;
  duplicate_cluster_key?: string | null;
  is_featured: boolean;
  is_video?: boolean;
  duration_seconds?: number | null;
  video_policy_label?: string | null;
  video_admission?: WallVideoAdmission | null;
  served_variant_key?: string | null;
  preview_variant_key?: string | null;
  is_replay: boolean;
  created_at?: string | null;
}

export interface WallSimulationResponse {
  summary: WallSimulationSummary;
  sequence_preview: WallSimulationPreviewItem[];
  explanation: string[];
}

export interface WallBootData {
  event: WallEventSummary;
  files: WallMediaItem[];
  settings: WallSettings;
  ads: WallAdItem[];
}

export interface WallMediaDeletedPayload {
  id: string;
}

export interface WallStatusChangedPayload {
  status: WallPublicStatus;
  reason?: string | null;
  updated_at?: string | null;
}

export interface WallExpiredPayload {
  reason?: string | null;
  expired_at?: string | null;
}

export interface WallStateData {
  status: WallPublicStatus;
  is_live: boolean;
  wall_code: string;
}
